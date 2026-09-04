<?php
/**
 * PrimeCare — Get Profile Endpoint
 * ---------------------------------------------------------
 * GET /api/profile/get.php
 * Requires an active patient session. Returns the logged-in
 * patient's own details only.
 */

require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare(
        'SELECT first_name, last_name, email, phone, date_of_birth, address,
                emergency_contact_name, emergency_contact_phone
         FROM patients WHERE patient_id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $patientId]);
    $patient = $stmt->fetch();

    if (!$patient) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Profile not found.']);
        exit;
    }

    echo json_encode(['success' => true, 'patient' => $patient]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load your profile.']);
}
