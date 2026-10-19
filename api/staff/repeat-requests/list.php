<?php
/** GET /api/staff/repeat-requests/list.php (DOCTOR) — pending repeat requests. */
require_once __DIR__ . '/../../../includes/staff-auth.php';
require_staff($pdo, ['DOCTOR']);

try {
    $rows = $pdo->query(
        "SELECT r.request_id, pr.medication_name, pr.dosage, r.request_date,
                p.first_name AS patient_first_name, p.last_name AS patient_last_name
         FROM prescription_requests r
         JOIN prescriptions pr ON pr.prescription_id = r.prescription_id
         JOIN patients p       ON p.patient_id = r.patient_id
         WHERE r.status = 'Requested'
         ORDER BY r.request_date ASC, r.request_id ASC"
    )->fetchAll();
    foreach ($rows as &$r) { $r['request_id'] = (int) $r['request_id']; }
    unset($r);

    json_out(['success' => true, 'count' => count($rows), 'requests' => $rows]);
} catch (PDOException $e) {
    fail('Could not load repeat requests.', 500);
}
