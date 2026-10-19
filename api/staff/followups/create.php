<?php
/**
 * POST /api/staff/followups/create.php  (DOCTOR)
 * Body: { appointmentId, date (yyyy-MM-dd), reason }
 * Books a real follow-up appointment (visible on the patient's website).
 */
require_once __DIR__ . '/../../../includes/staff-auth.php';
require_method('POST');
$staff = require_staff($pdo, ['DOCTOR']);

$in = read_json();
$id     = (int) ($in['appointmentId'] ?? 0);
$date   = trim((string) ($in['date'] ?? ''));
$reason = trim((string) ($in['reason'] ?? ''));
if (mb_strlen($reason) > 200) { fail('The reason is too long (max 200 characters).', 422); }

try {
    $appt = load_own_appointment($pdo, $staff, $id, false);
    if ($appt['doctor_id'] !== $staff['doctor_id']) {
        fail('You can only schedule follow-ups for your own patients.', 403);
    }
    $res = create_followup($pdo, $appt, $staff['doctor_id'], $date, $reason);
    if (!$res['created']) {
        fail($res['message'], 422);
    }
    json_out(['success' => true, 'message' => 'Follow-up booked for ' . $date . ' at ' . $res['time'] . '.'], 201);
} catch (PDOException $e) {
    fail('Could not schedule the follow-up.', 500);
}
