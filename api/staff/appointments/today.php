<?php
/**
 * GET /api/staff/appointments/today.php
 * Optional query params:
 *   q=<text>          patient-name search
 *   status=Booked,CheckedIn   comma-separated status filter
 *   unassigned=1      only appointments with no doctor
 * ADMIN sees every appointment; a DOCTOR sees only their own.
 */
require_once __DIR__ . '/../../../includes/staff-auth.php';
$staff = require_staff($pdo, ['ADMIN', 'DOCTOR']);

$where  = ['a.appointment_date = :today'];
$params = ['today' => today()];

if ($staff['role'] === 'DOCTOR') {
    $where[] = 'a.doctor_id = :doctor';
    $params['doctor'] = $staff['doctor_id'];
}

$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    $where[] = "CONCAT(p.first_name, ' ', p.last_name) LIKE :q";
    $params['q'] = '%' . $q . '%';
}

$allowed = ['Booked', 'CheckedIn', 'Completed', 'Cancelled'];
$statuses = array_values(array_intersect($allowed, array_filter(explode(',', (string) ($_GET['status'] ?? '')))));
if (!empty($statuses)) {
    $names = [];
    foreach ($statuses as $i => $s) {
        $names[] = ':s' . $i;
        $params['s' . $i] = $s;
    }
    $where[] = 'a.status IN (' . implode(',', $names) . ')';
}

if (($_GET['unassigned'] ?? '') === '1') {
    $where[] = 'a.doctor_id IS NULL';
}

try {
    $stmt = $pdo->prepare(APPT_SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY a.appointment_time ASC, a.appointment_id ASC');
    $stmt->execute($params);
    $rows = array_map('shape_appointment', $stmt->fetchAll());

    json_out(['success' => true, 'count' => count($rows), 'appointments' => $rows]);
} catch (PDOException $e) {
    fail('Could not load appointments.', 500);
}
