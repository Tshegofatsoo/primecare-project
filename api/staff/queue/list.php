<?php
/**
 * GET /api/staff/queue/list.php
 * Today's live queue: who is being seen now, and who is waiting.
 * ADMIN sees the whole clinic; a DOCTOR sees only their own patients.
 */
require_once __DIR__ . '/../../../includes/staff-auth.php';
$staff = require_staff($pdo, ['ADMIN', 'DOCTOR']);

$sql = APPT_SELECT . " WHERE a.appointment_date = :today AND a.status = 'CheckedIn'";
$params = ['today' => today()];
if ($staff['role'] === 'DOCTOR') {
    $sql .= ' AND a.doctor_id = :doctor';
    $params['doctor'] = $staff['doctor_id'];
}
$sql .= ' ORDER BY a.queue_number ASC, a.appointment_id ASC';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $nowServing = [];
    $waiting = [];
    foreach ($stmt->fetchAll() as $row) {
        $shaped = shape_appointment($row);
        if ($shaped['in_consultation']) { $nowServing[] = $shaped; } else { $waiting[] = $shaped; }
    }
    json_out(['success' => true, 'nowServing' => $nowServing, 'waiting' => $waiting]);
} catch (PDOException $e) {
    fail('Could not load the queue.', 500);
}
