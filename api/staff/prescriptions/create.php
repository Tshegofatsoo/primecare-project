<?php
/**
 * POST /api/staff/prescriptions/create.php  (DOCTOR)
 * Body: { appointmentId, medication, dosage, frequency, duration, instructions }
 *
 * The website's prescriptions table has no frequency/duration columns, so
 * frequency is appended to `dosage` and duration is prefixed onto
 * `instructions` — both then show up automatically on the patient's
 * My Prescriptions page without changing the website.
 */
require_once __DIR__ . '/../../../includes/staff-auth.php';
require_method('POST');
$staff = require_staff($pdo, ['DOCTOR']);

$in = read_json();
$id           = (int) ($in['appointmentId'] ?? 0);
$medication   = trim((string) ($in['medication'] ?? ''));
$dosage       = trim((string) ($in['dosage'] ?? ''));
$frequency    = trim((string) ($in['frequency'] ?? ''));
$duration     = trim((string) ($in['duration'] ?? ''));
$instructions = trim((string) ($in['instructions'] ?? ''));

if ($medication === '' || mb_strlen($medication) > 150) { fail('Please enter the medication name (max 150 characters).', 422); }
if ($dosage === '')    { fail('Please enter the dosage.', 422); }
if ($frequency === '') { fail('Please enter how often it should be taken.', 422); }

$dosageText = mb_substr($dosage . ' · ' . $frequency, 0, 100);
$instrParts = [];
if ($duration !== '')     { $instrParts[] = 'Duration: ' . $duration . '.'; }
if ($instructions !== '') { $instrParts[] = $instructions; }
$instrText = mb_substr(implode(' ', $instrParts), 0, 255);

try {
    $appt = load_own_appointment($pdo, $staff, $id);
    if ($appt['status'] !== 'CheckedIn' || !$appt['in_consultation']) {
        fail('Prescriptions can only be added during an active consultation.', 409);
    }

    $pdo->beginTransaction();
    $recordId = ensure_record($pdo, $appt, $staff['doctor_id']);
    $pdo->prepare(
        "INSERT INTO prescriptions (record_id, patient_id, doctor_id, medication_name, dosage, instructions, date_issued, status)
         VALUES (:r, :p, :d, :m, :dos, :ins, :date, 'Active')"
    )->execute([
        'r' => $recordId, 'p' => $appt['patient_id'], 'd' => $staff['doctor_id'],
        'm' => $medication, 'dos' => $dosageText,
        'ins' => $instrText !== '' ? $instrText : null, 'date' => today(),
    ]);
    $pdo->commit();

    json_out(['success' => true, 'message' => 'Prescription saved.'], 201);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fail('Could not save the prescription.', 500);
}
