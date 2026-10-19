<?php
/** GET /api/staff/doctors/list.php — all doctors (ADMIN + DOCTOR). */
require_once __DIR__ . '/../../../includes/staff-auth.php';
require_staff($pdo, ['ADMIN', 'DOCTOR']);

try {
    $rows = $pdo->query(
        "SELECT d.doctor_id, d.first_name, d.last_name, d.specialty, d.status,
                dep.department_name
         FROM doctors d
         LEFT JOIN departments dep ON dep.department_id = d.department_id
         ORDER BY d.status ASC, d.first_name ASC"
    )->fetchAll();

    $active = 0;
    foreach ($rows as &$r) {
        $r['doctor_id'] = (int) $r['doctor_id'];
        if ($r['status'] === 'Active') { $active++; }
    }
    unset($r);

    json_out(['success' => true, 'count' => count($rows), 'activeCount' => $active, 'doctors' => $rows]);
} catch (PDOException $e) {
    fail('Could not load doctors.', 500);
}
