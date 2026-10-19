<?php
/**
 * POST /api/staff/auth/login.php
 * Body (JSON): { email, password }
 * Returns a bearer token the Android app sends on every later request.
 */
require_once __DIR__ . '/../../../includes/staff-auth.php';
require_once __DIR__ . '/../../../includes/rate-limit.php';

require_method('POST');
check_rate_limit($pdo, 'login', 10, 15);

$input    = read_json();
$email    = trim((string) ($input['email'] ?? ''));
$password = (string) ($input['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    fail('Please enter a valid email and password.', 422);
}

// Same constant-time trick as the patient login: always run password_verify().
const STAFF_DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

try {
    $stmt = $pdo->prepare(
        "SELECT staff_id, email, password_hash, role, first_name, last_name, doctor_id
         FROM staff WHERE email = :email AND status = 'Active' LIMIT 1"
    );
    $stmt->execute(['email' => $email]);
    $staff = $stmt->fetch();

    $valid = password_verify($password, $staff['password_hash'] ?? STAFF_DUMMY_HASH);
    if (!$staff || !$valid) {
        record_attempt($pdo, 'login');
        fail('Invalid email or password.', 401);
    }

    json_out(issue_login_response($pdo, $staff));
} catch (PDOException $e) {
    fail('Something went wrong. Please try again.', 500);
}
