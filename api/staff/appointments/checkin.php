<?php
/**
 * POST /api/staff/appointments/checkin.php  (ADMIN)
 * Body: { appointmentId }
 * Booked -> CheckedIn and assigns today's next queue number.
 */
require_once __DIR__ . '/../../../includes/staff-auth.php';
require_method('POST');
require_staff($pdo, ['ADMIN']);

$input = read_json();
$id = (int) ($input['appointmentId'] ?? 0);
if ($id <= 0) { fail('Invalid appointment.', 422); }

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT appointment_id, patient_id, status, appointment_date FROM appointments WHERE appointment_id = :id FOR UPDATE');
    $stmt->execute(['id' => $id]);
    $appt = $stmt->fetch();

    if (!$appt) {
        $pdo->rollBack();
        fail('Appointment not found.', 404);
    }
    if ($appt['appointment_date'] !== today()) {
        $pdo->rollBack();
        fail('Only today\'s appointments can be checked in.', 409);
    }
    if ($appt['status'] !== 'Booked') {
        $pdo->rollBack();
        fail($appt['status'] === 'CheckedIn' ? 'This patient is already checked in.' : 'This appointment cannot be checked in (' . $appt['status'] . ').', 409);
    }

    $queue = next_queue_number($pdo, $appt['appointment_date']);
    $pdo->prepare(
        "UPDATE appointments SET status = 'CheckedIn', queue_number = :q, checked_in_at = :now WHERE appointment_id = :id"
    )->execute(['q' => $queue, 'now' => now_dt(), 'id' => $id]);

    notify_patient($pdo, (int) $appt['patient_id'], 'Checked in', 'You are checked in. Your queue number is Q-' . sprintf('%02d', $queue) . '.', 'Appointment');

    $pdo->commit();
    json_out(['success' => true, 'message' => 'Patient checked in.', 'queueNumber' => $queue]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fail('Could not check the patient in.', 500);
}
