<?php
/**
 * POST /api/staff/auth/change-password.php
 * Body: { currentPassword, newPassword }
 * Signs out every OTHER device by revoking their tokens.
 */
require_once __DIR__ . '/../../../includes/staff-auth.php';
require_method('POST');
$staff = require_staff($pdo);

$in      = read_json();
$current = (string) ($in['currentPassword'] ?? '');
$new     = (string) ($in['newPassword'] ?? '');

if (($err = password_error($new)) !== null) { fail($err, 422); }
if ($current === $new) { fail('Your new password must be different from the current one.', 422); }

try {
    $stmt = $pdo->prepare('SELECT password_hash FROM staff WHERE staff_id = :id');
    $stmt->execute(['id' => $staff['staff_id']]);
    $hash = $stmt->fetchColumn();
    if (!$hash || !password_verify($current, $hash)) {
        fail('Your current password is incorrect.', 422);   // 422, not 401: the session is still valid
    }

    $pdo->prepare('UPDATE staff SET password_hash = :h WHERE staff_id = :id')
        ->execute(['h' => password_hash($new, PASSWORD_DEFAULT), 'id' => $staff['staff_id']]);
    $pdo->prepare('DELETE FROM staff_tokens WHERE staff_id = :id AND token_hash <> :keep')
        ->execute(['id' => $staff['staff_id'], 'keep' => hash('sha256', (string) bearer_token())]);

    json_out(['success' => true, 'message' => 'Password updated.']);
} catch (PDOException $e) {
    fail('Could not update the password.', 500);
}
