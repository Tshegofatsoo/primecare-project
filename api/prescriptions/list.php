<?php
/**
 * PrimeCare — List Prescriptions Endpoint
 * ---------------------------------------------------------
 * GET /api/prescriptions/list.php
 * Requires an active patient session.
 *
 * Includes has_pending_request per prescription so the frontend
 * can disable "Request repeat" when one is already in progress,
 * without needing a second round-trip.
 */

require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare(
        "SELECT
            p.prescription_id, p.medication_name, p.dosage, p.instructions,
            p.date_issued, p.status,
            d.first_name AS doctor_first_name, d.last_name AS doctor_last_name,
            EXISTS (
                SELECT 1 FROM prescription_requests pr
                WHERE pr.prescription_id = p.prescription_id
                  AND pr.status = 'Requested'
            ) AS has_pending_request
         FROM prescriptions p
         LEFT JOIN doctors d ON p.doctor_id = d.doctor_id
         WHERE p.patient_id = :patient_id
         ORDER BY p.date_issued DESC"
    );
    $stmt->execute(['patient_id' => $patientId]);
    $prescriptions = $stmt->fetchAll();

    echo json_encode(['success' => true, 'prescriptions' => $prescriptions]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load prescriptions.']);
}
