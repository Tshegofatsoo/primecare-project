<?php
/**
 * POST /api/staff/consultations/save.php  (DOCTOR)
 * Body: { appointmentId, symptoms, bloodPressure, temperature, pulse, spo2,
 *         medicalHistory, allergies, diagnosis, treatment, investigations,
 *         notes, sickLeaveDays, followUpDate (yyyy-MM-dd or ""), finalize }
 *
 * finalize=false : saves a draft (consultations table only).
 * finalize=true  : requires a diagnosis; writes the patient-visible
 *                  medical_records row, completes the appointment and books
 *                  the follow-up (if a date was chosen) in ONE transaction.
 */
require_once __DIR__ . '/../../../includes/staff-auth.php';
require_method('POST');
$staff = require_staff($pdo, ['DOCTOR']);

$in = read_json();
$id = (int) ($in['appointmentId'] ?? 0);
$finalize = !empty($in['finalize']);

$str = function (string $key, int $max) use ($in): string {
    $v = trim((string) ($in[$key] ?? ''));
    if (mb_strlen($v) > $max) { fail("'$key' is too long (max $max characters).", 422); }
    return $v;
};

$symptoms       = $str('symptoms', 500);
$bp             = $str('bloodPressure', 20);
$temperature    = $str('temperature', 10);
$pulse          = $str('pulse', 10);
$spo2           = $str('spo2', 10);
$history        = $str('medicalHistory', 2000);
$allergies      = $str('allergies', 255);
$diagnosis      = $str('diagnosis', 255);
$treatment      = $str('treatment', 2000);
$investigations = $str('investigations', 500);
$notes          = $str('notes', 2000);
$followUpDate   = $str('followUpDate', 10);
$sickRaw        = trim((string) ($in['sickLeaveDays'] ?? ''));

// ---- field validation ----
if ($temperature !== '' && (!is_numeric($temperature) || $temperature < 30 || $temperature > 45)) {
    fail('Temperature must be between 30 and 45 °C.', 422);
}
if ($pulse !== '' && (!ctype_digit($pulse) || $pulse < 20 || $pulse > 300)) {
    fail('Pulse must be between 20 and 300 bpm.', 422);
}
if ($spo2 !== '' && (!ctype_digit($spo2) || $spo2 < 50 || $spo2 > 100)) {
    fail('SpO2 must be between 50 and 100 %.', 422);
}
if ($bp !== '' && !preg_match('/^\d{2,3}\s*\/\s*\d{2,3}$/', $bp)) {
    fail('Blood pressure should look like 120/80.', 422);
}
$sickDays = 0;
if ($sickRaw !== '') {
    if (!ctype_digit($sickRaw) || (int) $sickRaw > 365) { fail('Sick leave must be between 0 and 365 days.', 422); }
    $sickDays = (int) $sickRaw;
}
if ($followUpDate !== '') {
    $err = followup_date_error($followUpDate);
    if ($err !== null) { fail($err, 422); }
}
if ($finalize && $diagnosis === '') {
    fail('Please enter a diagnosis before finalizing.', 422);
}

try {
    $appt = load_own_appointment($pdo, $staff, $id);
    if ($appt['status'] === 'Completed') { fail('This consultation has already been finalized.', 409); }
    if ($appt['status'] !== 'CheckedIn' || !$appt['in_consultation']) {
        fail('Start the consultation before saving it.', 409);
    }

    $pdo->beginTransaction();

    $pdo->prepare(
        "INSERT INTO consultations
            (appointment_id, patient_id, doctor_id, symptoms, blood_pressure, temperature, pulse, spo2,
             medical_history, allergies, diagnosis, treatment, investigations, notes, sick_leave_days,
             follow_up_date, status)
         VALUES (:a, :p, :d, :sym, :bp, :temp, :pulse, :spo2, :hist, :all, :diag, :treat, :inv, :notes,
                 :sick, :fu, 'Draft')
         ON DUPLICATE KEY UPDATE
            symptoms = VALUES(symptoms), blood_pressure = VALUES(blood_pressure),
            temperature = VALUES(temperature), pulse = VALUES(pulse), spo2 = VALUES(spo2),
            medical_history = VALUES(medical_history), allergies = VALUES(allergies),
            diagnosis = VALUES(diagnosis), treatment = VALUES(treatment),
            investigations = VALUES(investigations), notes = VALUES(notes),
            sick_leave_days = VALUES(sick_leave_days), follow_up_date = VALUES(follow_up_date)"
    )->execute([
        'a' => $id, 'p' => $appt['patient_id'], 'd' => $staff['doctor_id'],
        'sym' => $symptoms, 'bp' => $bp, 'temp' => $temperature, 'pulse' => $pulse, 'spo2' => $spo2,
        'hist' => $history, 'all' => $allergies, 'diag' => $diagnosis, 'treat' => $treatment,
        'inv' => $investigations, 'notes' => $notes, 'sick' => $sickDays,
        'fu' => $followUpDate !== '' ? $followUpDate : null,
    ]);

    $followUpMessage = null;

    if ($finalize) {
        $recordId = ensure_record($pdo, $appt, $staff['doctor_id']);

        // What the patient sees on the website: notes + the extras that matter to them.
        $parts = [];
        if ($notes !== '')          { $parts[] = $notes; }
        if ($investigations !== '') { $parts[] = 'Investigations & procedures: ' . $investigations; }
        if ($sickDays > 0)          { $parts[] = 'Sick leave: ' . $sickDays . ' day' . ($sickDays === 1 ? '' : 's') . '.'; }
        $visibleNotes = implode("\n", $parts);

        $pdo->prepare(
            'UPDATE medical_records
             SET diagnosis = :diag, treatment = :treat, follow_up_date = :fu, notes = :notes, is_draft = 0
             WHERE record_id = :r'
        )->execute([
            'diag' => $diagnosis, 'treat' => $treatment !== '' ? $treatment : null,
            'fu' => $followUpDate !== '' ? $followUpDate : null,
            'notes' => $visibleNotes !== '' ? $visibleNotes : null, 'r' => $recordId,
        ]);

        $pdo->prepare("UPDATE consultations SET status = 'Final', record_id = :r WHERE appointment_id = :a")
            ->execute(['r' => $recordId, 'a' => $id]);

        $pdo->prepare("UPDATE appointments SET status = 'Completed' WHERE appointment_id = :a")
            ->execute(['a' => $id]);

        notify_patient($pdo, (int) $appt['patient_id'], 'Consultation complete',
            'Your consultation summary is now available under Medical Records.', 'General');

        if ($followUpDate !== '') {
            $res = create_followup($pdo, $appt, $staff['doctor_id'], $followUpDate, $diagnosis);
            $followUpMessage = $res['created']
                ? 'Follow-up booked for ' . $followUpDate . ' at ' . $res['time'] . '.'
                : $res['message'];
        }
    }

    $pdo->commit();
    json_out([
        'success' => true,
        'message' => $finalize ? 'Consultation finalized.' : 'Draft saved.',
        'followUpMessage' => $followUpMessage,
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fail('Could not save the consultation.', 500);
}
