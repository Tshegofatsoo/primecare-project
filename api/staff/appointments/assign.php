<?php
/**
 * POST /api/staff/appointments/assign.php  (ADMIN)
 * Body: { appointmentId, doctorId }
 */
require_once __DIR__ . '/../../../includes/staff-auth.php';
require_method('POST');
require_staff($pdo, ['ADMIN']);

$input    = read_json();
$apptId   = (int) ($input['appointmentId'] ?? 0);
$doctorId = (int) ($input['doctorId'] ?? 0);
if ($apptId <= 0 || $doctorId <= 0) { fail('Please choose an appointment and a doctor.', 422); }

try {
    $doc = $pdo->prepare("SELECT first_name, last_name, status FROM doctors WHERE doctor_id = :id");
    $doc->execute(['id' => $doctorId]);
    $doctor = $doc->fetch();
    if (!$doctor) { fail('Doctor not found.', 404); }
    if ($doctor['status'] !== 'Active') { fail('That doctor is not currently active.', 409); }

    $stmt = $pdo->prepare('SELECT appointment_id, patient_id, status, appointment_date, appointment_time FROM appointments WHERE appointment_id = :id');
    $stmt->execute(['id' => $apptId]);
    $appt = $stmt->fetch();
    if (!$appt) { fail('Appointment not found.', 404); }
    if (!in_array($appt['status'], ['Booked', 'CheckedIn'], true)) {
        fail('Only open appointments can be assigned a doctor.', 409);
    }

    $pdo->prepare('UPDATE appointments SET doctor_id = :d WHERE appointment_id = :id')
        ->execute(['d' => $doctorId, 'id' => $apptId]);

    notify_patient(
        $pdo, (int) $appt['patient_id'], 'Doctor assigned',
        'Dr. ' . $doctor['first_name'] . ' ' . $doctor['last_name'] . ' will see you on ' . $appt['appointment_date'] . ' at ' . substr($appt['appointment_time'], 0, 5) . '.',
        'Appointment'
    );

    json_out(['success' => true, 'message' => 'Doctor assigned.']);
} catch (PDOException $e) {
    fail('Could not assign the doctor.', 500);
}
