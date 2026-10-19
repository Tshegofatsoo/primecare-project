<?php
/** GET /api/staff/prescriptions/list.php?appointmentId=N (DOCTOR) */
require_once __DIR__ . '/../../../includes/staff-auth.php';
$staff = require_staff($pdo, ['DOCTOR']);

try {
    $appt = load_own_appointment($pdo, $staff, (int) ($_GET['appointmentId'] ?? 0), false);
    json_out(['success' => true, 'prescriptions' => appointment_prescriptions($pdo, $appt['appointment_id'])]);
} catch (PDOException $e) {
    fail('Could not load prescriptions.', 500);
}
