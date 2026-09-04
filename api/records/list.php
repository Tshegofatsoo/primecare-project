<?php
/**
 * PrimeCare — List Medical Records Endpoint
 * ---------------------------------------------------------
 * GET /api/records/list.php
 * Requires an active patient session.
 *
 * Returns the logged-in patient's full medical record history.
 * $patientId comes from the session — a patient can never fetch
 * another patient's records by manipulating a request parameter.
 */

require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare(
        "SELECT
            r.record_id, r.consultation_date, r.diagnosis, r.treatment,
            r.follow_up_date, r.notes,
            d.first_name AS doctor_first_name, d.last_name AS doctor_last_name,
            GROUP_CONCAT(
                DISTINCT CONCAT(p.medication_name, ' — ', p.dosage)
                ORDER BY p.date_issued DESC
                SEPARATOR '; '
            ) AS prescriptions_summary
         FROM medical_records r
         LEFT JOIN doctors d ON r.doctor_id = d.doctor_id
         LEFT JOIN prescriptions p ON p.record_id = r.record_id
         WHERE r.patient_id = :patient_id
         GROUP BY r.record_id
         ORDER BY r.consultation_date DESC"
    );
    $stmt->execute(['patient_id' => $patientId]);
    $records = $stmt->fetchAll();

    echo json_encode(['success' => true, 'records' => $records]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load medical records.']);
}
