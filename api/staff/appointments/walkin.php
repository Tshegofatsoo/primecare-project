<?php
/**
 * POST /api/staff/appointments/walkin.php  (ADMIN)
 * Body: { fullName, phone, reason }
 *
 * The patients table requires email/username/password (the website's login
 * fields). Walk-ins get a placeholder identity that cannot be logged into;
 * if the person later registers on the website they get their own account.
 * A previous walk-in with the same name + phone is reused.
 */
require_once __DIR__ . '/../../../includes/staff-auth.php';
require_method('POST');
require_staff($pdo, ['ADMIN']);

$input    = read_json();
$fullName = trim(preg_replace('/\s+/', ' ', (string) ($input['fullName'] ?? '')));
$phone    = trim((string) ($input['phone'] ?? ''));
$reason   = trim((string) ($input['reason'] ?? ''));

if ($fullName === '') { fail('Please enter the patient\'s name.', 422); }
if (mb_strlen($fullName) > 100) { fail('That name is too long.', 422); }
if ($phone !== '' && !preg_match('/^[0-9+()\-\s]{7,20}$/', $phone)) { fail('Please enter a valid phone number.', 422); }
if ($reason === '') { $reason = 'Walk-in consultation'; }
$reason = mb_substr($reason, 0, 255);
if ($phone === '') { $phone = 'N/A'; }

$pos   = strrpos($fullName, ' ');
$first = $pos === false ? $fullName : substr($fullName, 0, $pos);
$last  = $pos === false ? '-'       : substr($fullName, $pos + 1);   // last_name is NOT NULL

try {
    $pdo->beginTransaction();

    $find = $pdo->prepare(
        "SELECT patient_id FROM patients
         WHERE username LIKE 'walkin\\_%' AND first_name = :f AND last_name = :l AND phone = :p LIMIT 1"
    );
    $find->execute(['f' => $first, 'l' => $last, 'p' => $phone]);
    $patientId = $find->fetchColumn();

    if (!$patientId) {
        $uniq = bin2hex(random_bytes(6));
        $pdo->prepare(
            'INSERT INTO patients (first_name, last_name, email, phone, username, password_hash)
             VALUES (:f, :l, :e, :p, :u, :h)'
        )->execute([
            'f' => $first, 'l' => $last,
            'e' => "walkin-$uniq@walkin.primecare.invalid",
            'p' => $phone,
            'u' => "walkin_$uniq",
            'h' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        ]);
        $patientId = (int) $pdo->lastInsertId();
    }

    $dep = $pdo->query("SELECT department_id FROM departments WHERE department_name = 'General Practice' LIMIT 1")->fetchColumn();

    $queue = next_queue_number($pdo, today());
    $pdo->prepare(
        "INSERT INTO appointments
            (patient_id, department_id, appointment_date, appointment_time, reason, status,
             queue_number, is_walkin, checked_in_at)
         VALUES (:p, :dep, :d, :t, :r, 'CheckedIn', :q, 1, :now)"
    )->execute([
        'p' => $patientId, 'dep' => $dep ?: null, 'd' => today(), 't' => date('H:i:s'),
        'r' => $reason, 'q' => $queue, 'now' => now_dt(),
    ]);
    $apptId = (int) $pdo->lastInsertId();

    $pdo->commit();
    json_out([
        'success' => true, 'message' => 'Walk-in registered.',
        'queueNumber' => $queue, 'appointmentId' => $apptId,
    ], 201);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fail('Could not register the walk-in.', 500);
}
