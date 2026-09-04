<?php
/**
 * PrimeCare — Change Password Endpoint
 * ---------------------------------------------------------
 * POST /api/profile/change-password.php
 * Body (JSON): { currentPassword, newPassword }
 * Requires an active patient session.
 *
 * Requires the CURRENT password to be re-entered and verified
 * before allowing a change — this stops someone who has hijacked
 * an already-open session (e.g. an unlocked shared computer)
 * from silently locking the real owner out.
 */

require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request format.']);
    exit;
}

$currentPassword = (string) ($input['currentPassword'] ?? '');
$newPassword     = (string) ($input['newPassword'] ?? '');

if (strlen($newPassword) < 8 || !preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/\d/', $newPassword)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'New password must be at least 8 characters and include a letter and a number.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT password_hash FROM patients WHERE patient_id = :id LIMIT 1');
    $stmt->execute(['id' => $patientId]);
    $patient = $stmt->fetch();

    if (!$patient || !password_verify($currentPassword, $patient['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $updateStmt = $pdo->prepare('UPDATE patients SET password_hash = :hash WHERE patient_id = :id');
    $updateStmt->execute(['hash' => $newHash, 'id' => $patientId]);

    echo json_encode(['success' => true, 'message' => 'Password changed successfully.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not change your password. Please try again.']);
}
