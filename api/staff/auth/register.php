<?php
/**
 * POST /api/staff/auth/register.php
 * Body: { firstName, lastName, email, password, role (ADMIN|DOCTOR), registrationCode }
 *
 * - The password is stored as a bcrypt hash (password_hash) — never plain text.
 * - A DOCTOR registration also creates the matching doctors row, so the new
 *   doctor immediately appears in Assign Doctor and on the website.
 */
require_once __DIR__ . '/../../../includes/staff-auth.php';
require_once __DIR__ . '/../../../includes/rate-limit.php';

require_method('POST');
check_rate_limit($pdo, 'register', 10, 15);

$in    = read_json();
$first = trim((string) ($in['firstName'] ?? ''));
$last  = trim((string) ($in['lastName'] ?? ''));
$email = strtolower(trim((string) ($in['email'] ?? '')));
$pass  = (string) ($in['password'] ?? '');
$role  = strtoupper(trim((string) ($in['role'] ?? '')));
$code  = (string) ($in['registrationCode'] ?? '');

record_attempt($pdo, 'register');   // every attempt counts, successful or not

if ($first === '' || $last === '' || mb_strlen($first) > 50 || mb_strlen($last) > 50) {
    fail('Please enter your first and last name (max 50 characters each).', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
    fail('Please enter a valid email address.', 422);
}
if (($err = password_error($pass)) !== null) { fail($err, 422); }
if (!in_array($role, ['ADMIN', 'DOCTOR'], true)) { fail('Please choose a role.', 422); }
if (!hash_equals(STAFF_REGISTRATION_CODE, $code)) {
    fail('That clinic registration code is not correct.', 403);
}

try {
    $pdo->beginTransaction();

    $exists = $pdo->prepare('SELECT 1 FROM staff WHERE email = :e UNION SELECT 1 FROM doctors WHERE email = :e2');
    $exists->execute(['e' => $email, 'e2' => $email]);
    if ($exists->fetch()) {
        $pdo->rollBack();
        fail('An account with that email already exists.', 409);
    }

    $doctorId = null;
    if ($role === 'DOCTOR') {
        $dep = $pdo->query("SELECT department_id FROM departments WHERE department_name = 'General Practice' LIMIT 1")->fetchColumn();
        $pdo->prepare("INSERT INTO doctors (first_name, last_name, specialty, department_id, email, status)
                       VALUES (:f, :l, 'General Practice', :d, :e, 'Active')")
            ->execute(['f' => $first, 'l' => $last, 'd' => $dep ?: null, 'e' => $email]);
        $doctorId = (int) $pdo->lastInsertId();
    }

    $pdo->prepare('INSERT INTO staff (email, password_hash, role, first_name, last_name, doctor_id)
                   VALUES (:e, :h, :r, :f, :l, :d)')
        ->execute(['e' => $email, 'h' => password_hash($pass, PASSWORD_DEFAULT), 'r' => $role,
                   'f' => $first, 'l' => $last, 'd' => $doctorId]);

    $pdo->commit();
    json_out(['success' => true, 'message' => 'Account created. You can now log in.'], 201);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fail('Could not create the account. Please try again.', 500);
}
