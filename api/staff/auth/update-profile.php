<?php
/** POST /api/staff/auth/update-profile.php — Body: { firstName, lastName } */
require_once __DIR__ . '/../../../includes/staff-auth.php';
require_method('POST');
$staff = require_staff($pdo);

$in    = read_json();
$first = trim((string) ($in['firstName'] ?? ''));
$last  = trim((string) ($in['lastName'] ?? ''));
if ($first === '' || $last === '' || mb_strlen($first) > 50 || mb_strlen($last) > 50) {
    fail('Please enter your first and last name (max 50 characters each).', 422);
}

try {
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE staff SET first_name = :f, last_name = :l WHERE staff_id = :id')
        ->execute(['f' => $first, 'l' => $last, 'id' => $staff['staff_id']]);
    if ($staff['doctor_id'] !== null) {   // keep the doctor listing in sync
        $pdo->prepare('UPDATE doctors SET first_name = :f, last_name = :l WHERE doctor_id = :d')
            ->execute(['f' => $first, 'l' => $last, 'd' => $staff['doctor_id']]);
    }
    $pdo->commit();
    json_out(['success' => true, 'message' => 'Profile updated.', 'firstName' => $first, 'lastName' => $last]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fail('Could not update your profile.', 500);
}
