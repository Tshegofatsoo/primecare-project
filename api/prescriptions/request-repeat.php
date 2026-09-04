<?php
/**
 * PrimeCare — Request Repeat Prescription Endpoint
 * ---------------------------------------------------------
 * POST /api/prescriptions/request-repeat.php
 * Body (JSON): { prescriptionId }
 * Requires an active patient session.
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
$prescriptionId = (int) ($input['prescriptionId'] ?? 0);

if ($prescriptionId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid prescription.']);
    exit;
}

try {
    // Confirm this prescription actually belongs to the logged-in patient
    $checkStmt = $pdo->prepare(
        'SELECT prescription_id FROM prescriptions WHERE prescription_id = :id AND patient_id = :patient_id LIMIT 1'
    );
    $checkStmt->execute(['id' => $prescriptionId, 'patient_id' => $patientId]);

    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Prescription not found.']);
        exit;
    }

    // Prevent duplicate pending requests for the same prescription
    $pendingStmt = $pdo->prepare(
        "SELECT request_id FROM prescription_requests
         WHERE prescription_id = :id AND status = 'Requested' LIMIT 1"
    );
    $pendingStmt->execute(['id' => $prescriptionId]);

    if ($pendingStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'You already have a pending request for this prescription.']);
        exit;
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO prescription_requests (prescription_id, patient_id, request_date, status)
         VALUES (:prescription_id, :patient_id, CURDATE(), "Requested")'
    );
    $insertStmt->execute(['prescription_id' => $prescriptionId, 'patient_id' => $patientId]);

    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Repeat prescription requested.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
}
