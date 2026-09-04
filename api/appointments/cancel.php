<?php
/**
 * PrimeCare — Cancel Appointment Endpoint
 * ---------------------------------------------------------
 * POST /api/appointments/cancel.php
 * Body (JSON): { appointmentId }
 * Requires an active patient session.
 *
 * The WHERE clause includes patient_id = the SESSION's patient,
 * never a value from the client — this is what stops a patient
 * from cancelling someone else's appointment by guessing an ID.
 */

require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request format.']);
    exit;
}
$appointmentId = (int) ($input['appointmentId'] ?? 0);

if ($appointmentId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid appointment.']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "UPDATE appointments
         SET status = 'Cancelled'
         WHERE appointment_id = :id
           AND patient_id = :patient_id
           AND status IN ('Booked', 'CheckedIn')"
    );
    $stmt->execute(['id' => $appointmentId, 'patient_id' => $patientId]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Appointment not found, already cancelled, or not yours to cancel.'
        ]);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Appointment cancelled.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not cancel the appointment. Please try again.']);
}
