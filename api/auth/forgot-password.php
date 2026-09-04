<?php
/**
 * PrimeCare — Forgot Password Endpoint
 * ---------------------------------------------------------
 * POST /api/auth/forgot-password.php
 * Body (JSON): { email }
 *
 * Generates a password reset token and stores its HASH (never
 * the raw token) against the patient record, valid for 30 minutes.
 *
 * IMPORTANT — NOT YET COMPLETE FOR PRODUCTION:
 * This endpoint does not actually send an email. Doing so requires
 * an SMTP mail service (e.g. PHPMailer + a real mail provider),
 * which is outside the scope of a local capstone environment.
 * The TODO below marks exactly where that integration belongs.
 * Until then, this endpoint proves out the token-generation and
 * storage logic that the real email flow will plug into.
 *
 * Regardless of whether the email exists, this endpoint always
 * returns the same generic success message — this is deliberate,
 * to prevent attackers from using it to discover which emails
 * are registered patients (user enumeration protection).
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

$genericResponse = [
    'success' => true,
    'message' => "If that email is registered, we've sent a password reset link."
];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Still return the generic message — don't reveal that the format was invalid
    // vs. the account not existing.
    echo json_encode($genericResponse);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT patient_id FROM patients WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $patient = $stmt->fetch();

    if ($patient) {
        // Generate a cryptographically secure random token
        $rawToken   = bin2hex(random_bytes(32));
        $tokenHash  = hash('sha256', $rawToken);
        $expiresAt  = date('Y-m-d H:i:s', time() + (30 * 60)); // valid for 30 minutes

        $update = $pdo->prepare(
            'UPDATE patients
             SET reset_token_hash = :token_hash, reset_token_expires = :expires
             WHERE patient_id = :id'
        );
        $update->execute([
            'token_hash' => $tokenHash,
            'expires'    => $expiresAt,
            'id'         => $patient['patient_id'],
        ]);

        // TODO: send $rawToken to the patient's email as a link, e.g.
        //   https://yourdomain.com/reset-password.html?token=$rawToken
        // using PHPMailer/SMTP. The raw token must only ever be sent via
        // email — never returned in this API response.
        //
        // reset-password.html + api/auth/reset-password.php now fully
        // exist and correctly consume this token — only the email
        // DELIVERY step is still missing, not the reset flow itself.
    }

    // Same response whether or not the account existed
    echo json_encode($genericResponse);

} catch (PDOException $e) {
    // Even on a server error, avoid leaking whether the account exists.
    // Log $e->getMessage() server-side in a real deployment.
    echo json_encode($genericResponse);
}
