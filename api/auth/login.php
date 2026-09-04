<?php
/**
 * PrimeCare — Patient Login Endpoint
 * ---------------------------------------------------------
 * POST /api/auth/login.php
 * Body (JSON): { email, password, rememberMe }
 *
 * On success, starts a PHP session and stores the patient's
 * identity server-side. This session is what every other
 * protected endpoint (appointments, records, prescriptions,
 * profile) will check via includes/auth-check.php.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../../includes/rate-limit.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Max 5 login attempts per IP per 15 minutes.
check_rate_limit($pdo, 'login', 5, 15);

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request format.']);
    exit;
}

$email      = trim($input['email'] ?? '');
$password   = (string)($input['password'] ?? '');
$rememberMe = !empty($input['rememberMe']);

// ---- Basic presence/format validation ----
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email and password.']);
    exit;
}

// A syntactically valid bcrypt hash that doesn't correspond to any real
// password. Used below to keep response timing constant whether or not
// the email exists — see the timing-attack note further down.
const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

try {
    $stmt = $pdo->prepare(
        'SELECT patient_id, first_name, last_name, email, password_hash
         FROM patients WHERE email = :email LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $patient = $stmt->fetch();

    // IMPORTANT: use one identical generic message whether the email
    // doesn't exist or the password is wrong. Being specific here
    // ("email not found" vs "wrong password") lets an attacker
    // enumerate which emails have accounts.
    $invalidMessage = 'Invalid email or password.';

    // IMPORTANT: always run password_verify(), even when no matching
    // patient was found, against a dummy hash. bcrypt is deliberately
    // slow — if we skip this call whenever $patient is null (as
    // `!$patient || !password_verify(...)` would via short-circuiting),
    // a non-existent email responds measurably faster than a real one
    // with a wrong password. That timing difference alone is enough to
    // enumerate valid emails, even with an identical error message.
    $hashToCheck   = $patient['password_hash'] ?? DUMMY_HASH;
    $passwordValid = password_verify($password, $hashToCheck);

    if (!$patient || !$passwordValid) {
        record_attempt($pdo, 'login');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => $invalidMessage]);
        exit;
    }

    // ---- Success: configure the session cookie BEFORE starting the session ----
    // Remember Me = persistent cookie (30 days). Otherwise, the session
    // cookie expires when the browser closes (lifetime = 0).
    $lifetime = $rememberMe ? (60 * 60 * 24 * 30) : 0;

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'httponly' => true,     // JavaScript can't read the session cookie (XSS protection)
        'samesite' => 'Lax',    // CSRF mitigation for cross-site requests
        // 'secure' => true,    // uncomment once served over HTTPS in production
    ]);

    session_start();

    // Regenerate the session ID on login to prevent session fixation attacks
    session_regenerate_id(true);

    $_SESSION['patient_id']   = $patient['patient_id'];
    $_SESSION['email']        = $patient['email'];
    $_SESSION['first_name']   = $patient['first_name'];
    $_SESSION['last_name']    = $patient['last_name'];
    $_SESSION['role']         = 'patient';
    $_SESSION['logged_in_at'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token']    = bin2hex(random_bytes(32));

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'patient' => [
            'firstName' => $patient['first_name'],
            'lastName'  => $patient['last_name'],
            'email'     => $patient['email'],
        ],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
}
