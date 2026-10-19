<?php
/**
 * POST /api/staff/consultations/start.php  (DOCTOR)
 * Body: { appointmentId }
 * Marks the patient as "in consultation" (idempotent) and returns the
 * appointment, any saved draft, and prescriptions issued so far.
 * For a first visit, allergies/history are pre-filled from the patient's
 * most recent consultation.
 */
require_once __DIR__ . '/../../../includes/staff-auth.php';
require_method('POST');
$staff = require_staff($pdo, ['DOCTOR']);

$input = read_json();
$id = (int) ($input['appointmentId'] ?? 0);

try {
    $appt = load_own_appointment($pdo, $staff, $id);
    if ($appt['status'] !== 'CheckedIn') {
        fail($appt['status'] === 'Completed'
            ? 'This consultation has already been completed.'
            : 'The patient must be checked in before the consultation can start.', 409);
    }

    $pdo->prepare('UPDATE appointments SET consultation_started_at = COALESCE(consultation_started_at, :now) WHERE appointment_id = :id')
        ->execute(['now' => now_dt(), 'id' => $id]);
    $appt = fetch_appointment($pdo, $id);

    $stmt = $pdo->prepare('SELECT * FROM consultations WHERE appointment_id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch() ?: null;

    if ($row === null) {
        $prev = $pdo->prepare(
            "SELECT allergies, medical_history FROM consultations
             WHERE patient_id = :p AND status = 'Final' ORDER BY consultation_id DESC LIMIT 1"
        );
        $prev->execute(['p' => $appt['patient_id']]);
        $row = $prev->fetch() ?: null;
    }

    json_out([
        'success'       => true,
        'appointment'   => $appt,
        'consultation'  => shape_consultation($row),
        'prescriptions' => appointment_prescriptions($pdo, $id),
    ]);
} catch (PDOException $e) {
    fail('Could not start the consultation.', 500);
}
