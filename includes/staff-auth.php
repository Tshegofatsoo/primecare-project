<?php
/**
 * PrimeCare — Staff API shared include
 * ---------------------------------------------------------
 * Every api/staff/* endpoint (except auth/login.php) starts with:
 *
 *   require_once __DIR__ . '/../../../includes/staff-auth.php';
 *   $staff = require_staff($pdo, ['ADMIN']);   // or ['DOCTOR'] / ['ADMIN','DOCTOR']
 *
 * Staff authenticate with "Authorization: Bearer <token>" (issued by
 * api/staff/auth/login.php). Only the SHA-256 hash of a token is stored.
 * Unlike the patient website there are no cookies, so no CSRF token is
 * needed — a browser can't be tricked into attaching a bearer header.
 */

require_once __DIR__ . '/../api/config/db.php';

date_default_timezone_set('Africa/Johannesburg');
header('Content-Type: application/json');

const STAFF_TOKEN_TTL_HOURS = 12;

// ---------- small response helpers ----------

function json_out(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function fail(string $message, int $status = 400, array $extra = []): void {
    json_out(array_merge(['success' => false, 'message' => $message], $extra), $status);
}

function require_method(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        fail('Method not allowed.', 405);
    }
}

function read_json(): array {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        fail('Invalid request format.', 400);
    }
    return $input;
}

function today(): string {
    return date('Y-m-d');
}

function now_dt(): string {
    return date('Y-m-d H:i:s');
}

// ---------- authentication ----------

function bearer_token(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $header = $value;
                break;
            }
        }
    }
    if (preg_match('/^Bearer\s+([A-Fa-f0-9]{64})$/', trim($header), $m)) {
        return $m[1];
    }
    return null;
}

/**
 * Returns the authenticated staff row (staff_id, role, first_name, last_name,
 * email, doctor_id) or sends 401/403 and exits.
 */
function require_staff(PDO $pdo, array $roles = []): array {
    $token = bearer_token();
    if ($token === null) {
        fail('You must be logged in to do that.', 401);
    }

    $stmt = $pdo->prepare(
        "SELECT s.staff_id, s.email, s.role, s.first_name, s.last_name, s.doctor_id
         FROM staff_tokens t
         JOIN staff s ON s.staff_id = t.staff_id
         WHERE t.token_hash = :hash AND t.expires_at > :now AND s.status = 'Active'
         LIMIT 1"
    );
    $stmt->execute(['hash' => hash('sha256', $token), 'now' => now_dt()]);
    $staff = $stmt->fetch();

    if (!$staff) {
        fail('Your session has expired. Please log in again.', 401);
    }
    if (!empty($roles) && !in_array($staff['role'], $roles, true)) {
        fail('You do not have permission to do that.', 403);
    }
    if ($staff['role'] === 'DOCTOR' && empty($staff['doctor_id'])) {
        fail('This doctor account is not linked to a doctor profile.', 403);
    }
    $staff['staff_id'] = (int) $staff['staff_id'];
    $staff['doctor_id'] = $staff['doctor_id'] !== null ? (int) $staff['doctor_id'] : null;
    return $staff;
}

// ---------- appointment queries ----------

/** Column list + joins shared by every endpoint that returns appointments. */
const APPT_SELECT =
    "SELECT
        a.appointment_id, a.appointment_date, a.appointment_time, a.reason, a.status,
        p.first_name AS patient_first_name, p.last_name AS patient_last_name,
        d.first_name AS doctor_first_name,  d.last_name  AS doctor_last_name,
        dep.department_name,
        a.patient_id, a.doctor_id, a.queue_number, a.is_walkin, a.is_followup,
        a.consultation_started_at
     FROM appointments a
     JOIN patients p            ON p.patient_id = a.patient_id
     LEFT JOIN doctors d        ON d.doctor_id = a.doctor_id
     LEFT JOIN departments dep  ON dep.department_id = a.department_id ";

/** Casts a raw row into the exact shape the Android AppointmentDto expects. */
function shape_appointment(array $r): array {
    return [
        'appointment_id'     => (int) $r['appointment_id'],
        'appointment_date'   => $r['appointment_date'],
        'appointment_time'   => $r['appointment_time'],
        'reason'             => $r['reason'],
        'status'             => $r['status'],
        'patient_first_name' => $r['patient_first_name'],
        'patient_last_name'  => $r['patient_last_name'],
        'doctor_first_name'  => $r['doctor_first_name'],
        'doctor_last_name'   => $r['doctor_last_name'],
        'department_name'    => $r['department_name'],
        'patient_id'         => (int) $r['patient_id'],
        'doctor_id'          => $r['doctor_id'] !== null ? (int) $r['doctor_id'] : null,
        'queue_number'       => $r['queue_number'] !== null ? (int) $r['queue_number'] : null,
        'is_walkin'          => (bool) $r['is_walkin'],
        'is_followup'        => (bool) $r['is_followup'],
        'in_consultation'    => $r['status'] === 'CheckedIn' && $r['consultation_started_at'] !== null,
    ];
}

function fetch_appointment(PDO $pdo, int $appointmentId): ?array {
    $stmt = $pdo->prepare(APPT_SELECT . ' WHERE a.appointment_id = :id LIMIT 1');
    $stmt->execute(['id' => $appointmentId]);
    $row = $stmt->fetch();
    return $row ? shape_appointment($row) : null;
}

/** Next queue number for a date: 1, 2, 3 ... resetting each day. */
function next_queue_number(PDO $pdo, string $date): int {
    $stmt = $pdo->prepare('SELECT COALESCE(MAX(queue_number), 0) + 1 FROM appointments WHERE appointment_date = :d FOR UPDATE');
    $stmt->execute(['d' => $date]);
    return (int) $stmt->fetchColumn();
}

function notify_patient(PDO $pdo, int $patientId, string $title, string $message, string $type): void {
    $stmt = $pdo->prepare(
        'INSERT INTO notifications (patient_id, title, message, type) VALUES (:p, :t, :m, :ty)'
    );
    $stmt->execute(['p' => $patientId, 't' => $title, 'm' => mb_substr($message, 0, 255), 'ty' => $type]);
}

// ---------- clinical helpers ----------

/**
 * Returns the record_id of the medical_records row for this appointment,
 * creating a DRAFT row if none exists yet (prescriptions need a record_id
 * even before the consultation is finalised).
 */
function ensure_record(PDO $pdo, array $appt, int $doctorId): int {
    $stmt = $pdo->prepare('SELECT record_id FROM medical_records WHERE appointment_id = :a LIMIT 1');
    $stmt->execute(['a' => $appt['appointment_id']]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return (int) $existing;
    }
    $ins = $pdo->prepare(
        "INSERT INTO medical_records
            (patient_id, doctor_id, appointment_id, consultation_date, diagnosis, is_draft)
         VALUES (:p, :d, :a, :date, 'Consultation in progress', 1)"
    );
    $ins->execute([
        'p' => $appt['patient_id'], 'd' => $doctorId,
        'a' => $appt['appointment_id'], 'date' => $appt['appointment_date'],
    ]);
    return (int) $pdo->lastInsertId();
}

/** Validates a follow-up date the same way the website validates bookings. */
function followup_date_error(string $date): ?string {
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
        return 'Please choose a valid follow-up date.';
    }
    if ($date < today()) {
        return 'The follow-up date cannot be in the past.';
    }
    if ((int) $dateObj->format('N') === 7) {
        return 'PrimeCare is closed on Sundays. Please choose Monday–Saturday.';
    }
    return null;
}

/**
 * Books a follow-up appointment for the same patient + doctor, mirroring the
 * website's booking rules (no Sundays, Mon–Fri 08:00–17:00, Sat 08:00–13:00,
 * not in the past). Picks the first free half-hour slot (starting 09:00) so
 * the website's (patient, date, time) uniqueness constraint is never hit.
 *
 * Returns ['created' => bool, 'message' => string, 'time' => ?string].
 */
function create_followup(PDO $pdo, array $parent, int $doctorId, string $date, string $reason): array {
    $error = followup_date_error($date);
    if ($error !== null) {
        return ['created' => false, 'message' => $error, 'time' => null];
    }
    $dow = (int) DateTime::createFromFormat('Y-m-d', $date)->format('N');

    // Already booked a follow-up from this appointment on that date?
    $dup = $pdo->prepare(
        "SELECT appointment_id FROM appointments
         WHERE patient_id = :p AND appointment_date = :d AND is_followup = 1
           AND status IN ('Booked','CheckedIn') LIMIT 1"
    );
    $dup->execute(['p' => $parent['patient_id'], 'd' => $date]);
    if ($dup->fetch()) {
        return ['created' => false, 'message' => 'A follow-up is already booked for that date.', 'time' => null];
    }

    $lastHour = $dow === 6 ? 13 : 17;            // exclusive upper bound
    $depStmt = $pdo->prepare('SELECT department_id FROM doctors WHERE doctor_id = :d');
    $depStmt->execute(['d' => $doctorId]);
    $departmentId = $depStmt->fetchColumn();
    if ($departmentId === false || $departmentId === null) {
        $depStmt = $pdo->prepare('SELECT department_id FROM appointments WHERE appointment_id = :a');
        $depStmt->execute(['a' => $parent['appointment_id']]);
        $departmentId = $depStmt->fetchColumn();
        if ($departmentId === false) { $departmentId = null; }
    }

    $busy = $pdo->prepare(
        "SELECT 1 FROM appointments
         WHERE patient_id = :p AND appointment_date = :d AND appointment_time = :t LIMIT 1"
    );
    $insert = $pdo->prepare(
        "INSERT INTO appointments
            (patient_id, doctor_id, department_id, appointment_date, appointment_time, reason, status, is_followup)
         VALUES (:p, :doc, :dep, :d, :t, :r, 'Booked', 1)"
    );

    $reasonText = trim($reason) !== '' ? 'Follow-up: ' . trim($reason) : 'Follow-up consultation';
    $reasonText = mb_substr($reasonText, 0, 255);

    for ($minutes = 9 * 60; $minutes < $lastHour * 60; $minutes += 30) {
        $time = sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
        if ($date === today() && $time <= date('H:i:s')) {
            continue;                              // slot already passed today
        }
        $busy->execute(['p' => $parent['patient_id'], 'd' => $date, 't' => $time]);
        if ($busy->fetch()) {
            continue;
        }
        $insert->execute([
            'p' => $parent['patient_id'], 'doc' => $doctorId, 'dep' => $departmentId,
            'd' => $date, 't' => $time, 'r' => $reasonText,
        ]);
        notify_patient(
            $pdo, (int) $parent['patient_id'], 'Follow-up booked',
            'Your doctor booked a follow-up for ' . $date . ' at ' . substr($time, 0, 5) . '.',
            'Appointment'
        );
        return ['created' => true, 'message' => 'Follow-up booked.', 'time' => substr($time, 0, 5)];
    }

    return ['created' => false, 'message' => 'No free slots remain on that date. Please choose another day.', 'time' => null];
}

/**
 * Loads an appointment and checks that $staff (a DOCTOR) may work on it.
 * An unassigned appointment is claimed by the doctor who opens it.
 * Sends an error response and exits if not allowed.
 */
function load_own_appointment(PDO $pdo, array $staff, int $appointmentId, bool $claim = true): array {
    if ($appointmentId <= 0) { fail('Invalid appointment.', 422); }
    $appt = fetch_appointment($pdo, $appointmentId);
    if (!$appt) { fail('Appointment not found.', 404); }
    if ($appt['doctor_id'] !== null && $appt['doctor_id'] !== $staff['doctor_id']) {
        fail('This appointment is assigned to another doctor.', 403);
    }
    if ($appt['doctor_id'] === null && $claim) {
        $pdo->prepare('UPDATE appointments SET doctor_id = :d WHERE appointment_id = :id AND doctor_id IS NULL')
            ->execute(['d' => $staff['doctor_id'], 'id' => $appointmentId]);
        $appt = fetch_appointment($pdo, $appointmentId);
    }
    return $appt;
}

/** Shapes a consultations row (or defaults) into the JSON the app expects. */
function shape_consultation(?array $c): array {
    $c = $c ?? [];
    $sick = (int) ($c['sick_leave_days'] ?? 0);
    return [
        'symptoms'       => (string) ($c['symptoms'] ?? ''),
        'bloodPressure'  => (string) ($c['blood_pressure'] ?? ''),
        'temperature'    => (string) ($c['temperature'] ?? ''),
        'pulse'          => (string) ($c['pulse'] ?? ''),
        'spo2'           => (string) ($c['spo2'] ?? ''),
        'medicalHistory' => (string) ($c['medical_history'] ?? ''),
        'allergies'      => (string) ($c['allergies'] ?? ''),
        'diagnosis'      => (string) ($c['diagnosis'] ?? ''),
        'treatment'      => (string) ($c['treatment'] ?? ''),
        'investigations' => (string) ($c['investigations'] ?? ''),
        'notes'          => (string) ($c['notes'] ?? ''),
        'sickLeaveDays'  => $sick > 0 ? (string) $sick : '',
        'followUpDate'   => (string) ($c['follow_up_date'] ?? ''),
        'status'         => (string) ($c['status'] ?? 'Draft'),
    ];
}

/** Prescriptions issued during one appointment's consultation. */
function appointment_prescriptions(PDO $pdo, int $appointmentId): array {
    $stmt = $pdo->prepare(
        "SELECT pr.prescription_id, pr.medication_name, pr.dosage, pr.instructions
         FROM prescriptions pr
         JOIN medical_records r ON r.record_id = pr.record_id
         WHERE r.appointment_id = :a
         ORDER BY pr.prescription_id ASC"
    );
    $stmt->execute(['a' => $appointmentId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['prescription_id'] = (int) $r['prescription_id'];
        $r['instructions'] = (string) ($r['instructions'] ?? '');
    }
    return $rows;
}

// ---------- registration / SSO support ----------

/**
 * Staff self-registration is gated by this clinic code so strangers cannot
 * create ADMIN/DOCTOR accounts. Change it (or load it from the environment).
 */
define('STAFF_REGISTRATION_CODE', getenv('PRIMECARE_STAFF_CODE') ?: 'PRIMECARE-2026');

/**
 * Google OAuth *Web* client ID used to verify "Sign in with Google" tokens.
 * Must be the same ID the Android app uses (AppConfig.GOOGLE_WEB_CLIENT_ID).
 */
define('GOOGLE_CLIENT_ID', getenv('PRIMECARE_GOOGLE_CLIENT_ID') ?: '');

/** Issues a bearer token for a staff row and returns the login JSON payload. */
function issue_login_response(PDO $pdo, array $staff): array {
    $pdo->prepare('DELETE FROM staff_tokens WHERE expires_at < :now')->execute(['now' => now_dt()]);

    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + STAFF_TOKEN_TTL_HOURS * 3600);
    $pdo->prepare('INSERT INTO staff_tokens (token_hash, staff_id, expires_at) VALUES (:h, :s, :e)')
        ->execute(['h' => hash('sha256', $token), 's' => $staff['staff_id'], 'e' => $expires]);

    return [
        'success'   => true,
        'message'   => 'Login successful.',
        'token'     => $token,
        'role'      => $staff['role'],
        'staffId'   => (int) $staff['staff_id'],
        'firstName' => $staff['first_name'],
        'lastName'  => $staff['last_name'],
    ];
}

/** At least 8 characters with a letter and a number. Returns an error message or null. */
function password_error(string $password): ?string {
    if (strlen($password) < 8) { return 'Password must be at least 8 characters.'; }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password must contain at least one letter and one number.';
    }
    return null;
}
