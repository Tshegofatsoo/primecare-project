<?php
/** POST /api/staff/auth/logout.php — revokes the presented token. */
require_once __DIR__ . '/../../../includes/staff-auth.php';

require_method('POST');
$token = bearer_token();
if ($token !== null) {
    $pdo->prepare('DELETE FROM staff_tokens WHERE token_hash = :h')
        ->execute(['h' => hash('sha256', $token)]);
}
json_out(['success' => true, 'message' => 'Logged out successfully.']);
