<?php
/**
 * PrimeCare — Create Appointment Endpoint
 * ---------------------------------------------------------
 * POST /api/appointments/create.php
 * Body (JSON): { departmentId, date, time, reason }
 * Requires an active patient session.
 *
 * Re-validates every booking rule server-side — date not in the
 * past, within operating hours for that day, department valid —
 * because the client-side date/slot picker can be bypassed by
 * anyone calling this endpoint directly.
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

$departmentId = (int) ($input['departmentId'] ?? 0);
$date         = trim($input['date'] ?? '');
$time         = trim($input['time'] ?? '');
$reason       = trim($input['reason'] ?? '');

$errors = [];

// ---- Date: valid, not in the past ----
$dateObj = DateTime::createFromFormat('Y-m-d', $date);
$today = new DateTime('today');
$now = new DateTime();

$dayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
    $errors['apptDate'] = 'Please select a valid date.';
} elseif ($dateObj < $today) {
    $errors['apptDate'] = 'Appointment date cannot be in the past.';
} else {
    // ---- Operating hours by day of week ----
    // Mon-Fri: 08:00-17:00, Sat: 08:00-13:00, Sun: closed
    $dayOfWeek = (int) $dateObj->format('N'); // 1 = Monday ... 7 = Sunday
    $timeObj = DateTime::createFromFormat('H:i', $time);

    if (!$timeObj) {
        $errors['apptTime'] = 'Please select a valid time.';
    } elseif ($dayOfWeek === 7) {
        $errors['apptDate'] = 'PrimeCare is closed on Sundays. Please choose Monday–Saturday.';
    } else {
        $minTime = DateTime::createFromFormat('H:i', '08:00');
        $maxTime = $dayOfWeek === 6
            ? DateTime::createFromFormat('H:i', '13:00')
            : DateTime::createFromFormat('H:i', '17:00');

        if ($timeObj < $minTime || $timeObj >= $maxTime) {
            $hoursLabel = $dayOfWeek === 6 ? '08:00–13:00' : '08:00–17:00';
            $errors['apptTime'] = $dayNames[$dayOfWeek] . ' hours are ' . $hoursLabel . '. Please choose a time within operating hours.';
        } elseif ($dateObj->format('Y-m-d') === $today->format('Y-m-d')) {
            // Same-day booking: the date is valid and within hours, but this
            // specific time may already have passed — check the full datetime.
            $slotDateTime = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
            if ($slotDateTime <= $now) {
                $errors['apptTime'] = 'This time has already passed today. Please choose a later time.';
            }
        }
    }
}

// ---- Department ----
if ($departmentId <= 0) {
    $errors['departmentId'] = 'Please select a department.';
}

// ---- Reason ----
if ($reason === '' || mb_strlen($reason) > 255) {
    $errors['reason'] = 'Please briefly describe the reason for your visit (max 255 characters).';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please correct the highlighted fields before booking.', 'errors' => $errors]);
    exit;
}

try {
    // ---- Confirm the department actually exists ----
    $deptStmt = $pdo->prepare('SELECT department_id FROM departments WHERE department_id = :id LIMIT 1');
    $deptStmt->execute(['id' => $departmentId]);
    if (!$deptStmt->fetch()) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'The selected department is no longer available. Please choose another.']);
        exit;
    }

    // ---- Proactive double-booking check ----
    // Gives a clean, immediate error instead of only relying on the
    // database's UNIQUE constraint to reject it after the fact.
    $dupStmt = $pdo->prepare(
        "SELECT appointment_id FROM appointments
         WHERE patient_id = :patient_id
           AND appointment_date = :date
           AND appointment_time = :time
           AND status IN ('Booked', 'CheckedIn')
         LIMIT 1"
    );
    $dupStmt->execute(['patient_id' => $patientId, 'date' => $date, 'time' => $time . ':00']);

    if ($dupStmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'You already have an appointment booked at this exact date and time.',
            'errors'  => ['apptTime' => 'You already have a booking in this slot.']
        ]);
        exit;
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO appointments (patient_id, department_id, appointment_date, appointment_time, reason, status)
         VALUES (:patient_id, :department_id, :date, :time, :reason, "Booked")'
    );
    $insertStmt->execute([
        'patient_id'    => $patientId,
        'department_id' => $departmentId,
        'date'          => $date,
        'time'          => $time . ':00',
        'reason'        => $reason,
    ]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Your appointment has been booked successfully. A doctor will be assigned before your visit.'
    ]);

} catch (PDOException $e) {
    // Defense-in-depth: even though we pre-check for duplicates above,
    // a race condition (e.g. two rapid submissions) could still hit the
    // database's UNIQUE constraint (error code 23000) directly.
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'You already have an appointment booked at this exact date and time.',
            'errors'  => ['apptTime' => 'You already have a booking in this slot.']
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Something went wrong while booking your appointment. Please try again.']);
    }
}
