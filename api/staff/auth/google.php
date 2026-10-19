<?php
/**
 * POST /api/staff/auth/google.php  — single sign-on with Google
 * Body: { idToken }  (the Google ID token the Android app obtained)
 *
 * The token is verified with Google's tokeninfo endpoint; its audience must
 * be OUR client ID and the email must be verified. Only people who already
 * have an active staff account (matched by email) can sign in this way, so
 * SSO never creates accounts.
 */
require_once __DIR__ . '/../../../includes/staff-auth.php';
require_once __DIR__ . '/../../../includes/rate-limit.php';

require_method('POST');
check_rate_limit($pdo, 'login', 10, 15);

if (GOOGLE_CLIENT_ID === '') {
    fail('Google sign-in is not configured on the server yet.', 503);
}

$in = read_json();
$idToken = trim((string) ($in['idToken'] ?? ''));
if ($idToken === '' || strlen($idToken) > 4096) { fail('Invalid Google token.', 422); }

$url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
$context = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
$raw = @file_get_contents($url, false, $context);
$info = $raw ? json_decode($raw, true) : null;

if (!is_array($info) || empty($info['email'])
    || ($info['aud'] ?? '') !== GOOGLE_CLIENT_ID
    || !in_array($info['email_verified'] ?? '', [true, 'true'], true)
    || (int) ($info['exp'] ?? 0) < time()) {
    record_attempt($pdo, 'login');
    fail('Google sign-in could not be verified.', 401);
}

try {
    $stmt = $pdo->prepare("SELECT staff_id, role, first_name, last_name, doctor_id
                           FROM staff WHERE email = :e AND status = 'Active' LIMIT 1");
    $stmt->execute(['e' => strtolower($info['email'])]);
    $staff = $stmt->fetch();
    if (!$staff) {
        record_attempt($pdo, 'login');
        fail('No PrimeCare staff account uses that Google email. Register first, using the same email.', 403);
    }
    json_out(issue_login_response($pdo, $staff));
} catch (PDOException $e) {
    fail('Something went wrong. Please try again.', 500);
}
