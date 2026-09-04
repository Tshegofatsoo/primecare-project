<?php
/**
 * PrimeCare — Reset Password Endpoint
 * ---------------------------------------------------------
 * POST /api/auth/reset-password.php
 * Body (JSON): { token, newPassword }
 *
 * This is the endpoint forgot-password.php's token was always
 * meant to lead to. It hashes the incoming raw token the same
 * way it was hashed when stored (SHA-256), looks up a patient
 * whose stored hash matches AND whose token hasn't expired, then
 * updates their password and immediately invalidates the token
 * so it can't be reused.
 */

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

$token       = trim($input['token'] ?? '');
$newPassword = (string) ($input['newPassword'] ?? '');

if ($token === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing reset token.']);
    exit;
}

if (strlen($newPassword) < 8 || !preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/\d/', $newPassword)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Password must be at least 8 characters and include a letter and a number.'
    ]);
    exit;
}

try {
    $tokenHash = hash('sha256', $token);

    $stmt = $pdo->prepare(
        "SELECT patient_id FROM patients
         WHERE reset_token_hash = :token_hash
           AND reset_token_expires > NOW()
         LIMIT 1"
    );
    $stmt->execute(['token_hash' => $tokenHash]);
    $patient = $stmt->fetch();

    if (!$patient) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'This reset link is invalid or has expired. Please request a new one.'
        ]);
        exit;
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update the password AND clear the token in the same operation, so
    // this exact link can never be used a second time (even if the new
    // password is somehow guessed later).
    $updateStmt = $pdo->prepare(
        "UPDATE patients
         SET password_hash = :hash, reset_token_hash = NULL, reset_token_expires = NULL
         WHERE patient_id = :id"
    );
    $updateStmt->execute(['hash' => $newHash, 'id' => $patient['patient_id']]);

    echo json_encode(['success' => true, 'message' => 'Your password has been reset. You can now log in.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
}
