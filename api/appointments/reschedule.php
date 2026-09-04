<?php
/**
 * PrimeCare — Reschedule Appointment Endpoint
 * ---------------------------------------------------------
 * POST /api/appointments/reschedule.php
 * Body (JSON): { appointmentId, date, time }
 * Requires an active patient session.
 *
 * Only appointments that are:
 *   - owned by the logged-in patient
 *   - status = 'Booked' (not CheckedIn — once a patient has checked
 *     in at the clinic, rescheduling no longer makes sense)
 *   - still in the future
 * may be rescheduled. Every date/time rule from booking (past dates,
 * operating hours, double-booking) is re-validated here exactly as
 * it is in create.php — the client-side picker can be bypassed.
 *
 * On success, doctor_id is reset to NULL. A doctor assigned for the
 * original slot may not be available for the new one, so the visit
 * goes back to "to be assigned" for the Android app's admin workflow
 * to reassign.
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

$appointmentId = (int) ($input['appointmentId'] ?? 0);
$date          = trim($input['date'] ?? '');
$time          = trim($input['time'] ?? '');

$errors = [];

// ---- Date/time validation — identical rules to create.php ----
$dateObj = DateTime::createFromFormat('Y-m-d', $date);
$today   = new DateTime('today');
$now     = new DateTime();
$dayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

if ($appointmentId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid appointment.']);
    exit;
}

if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
    $errors['apptDate'] = 'Please select a valid date.';
} elseif ($dateObj < $today) {
    $errors['apptDate'] = 'Appointment date cannot be in the past.';
} else {
    $dayOfWeek = (int) $dateObj->format('N');
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
            $slotDateTime = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
            if ($slotDateTime <= $now) {
                $errors['apptTime'] = 'This time has already passed today. Please choose a later time.';
            }
        }
    }
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $errors]);
    exit;
}

try {
    // ---- Confirm this appointment belongs to the patient and is eligible ----
    $checkStmt = $pdo->prepare(
        "SELECT appointment_id FROM appointments
         WHERE appointment_id = :id
           AND patient_id = :patient_id
           AND status = 'Booked'
           AND appointment_date >= CURDATE()
         LIMIT 1"
    );
    $checkStmt->execute(['id' => $appointmentId, 'patient_id' => $patientId]);

    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'This appointment can no longer be rescheduled (it may be in the past, already checked in, or cancelled).'
        ]);
        exit;
    }

    // ---- Proactive double-booking check, excluding this appointment itself ----
    $dupStmt = $pdo->prepare(
        "SELECT appointment_id FROM appointments
         WHERE patient_id = :patient_id
           AND appointment_date = :date
           AND appointment_time = :time
           AND status IN ('Booked', 'CheckedIn')
           AND appointment_id != :id
         LIMIT 1"
    );
    $dupStmt->execute(['patient_id' => $patientId, 'date' => $date, 'time' => $time . ':00', 'id' => $appointmentId]);

    if ($dupStmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'You already have another appointment booked at this exact date and time.',
            'errors'  => ['apptTime' => 'You already have a booking in this slot.']
        ]);
        exit;
    }

    $updateStmt = $pdo->prepare(
        "UPDATE appointments
         SET appointment_date = :date, appointment_time = :time, doctor_id = NULL
         WHERE appointment_id = :id AND patient_id = :patient_id"
    );
    $updateStmt->execute([
        'date'       => $date,
        'time'       => $time . ':00',
        'id'         => $appointmentId,
        'patient_id' => $patientId,
    ]);

    echo json_encode(['success' => true, 'message' => 'Your appointment has been rescheduled successfully.']);

} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'You already have another appointment booked at this exact date and time.',
            'errors'  => ['apptTime' => 'You already have a booking in this slot.']
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Something went wrong while rescheduling. Please try again.']);
    }
}
