<?php
/** GET /api/staff/dashboard/doctor.php (DOCTOR) — the doctor's own counters. */
require_once __DIR__ . '/../../../includes/staff-auth.php';
$staff = require_staff($pdo, ['DOCTOR']);

try {
    $count = function (string $sql, array $p) use ($pdo): int {
        $s = $pdo->prepare($sql);
        $s->execute($p);
        return (int) $s->fetchColumn();
    };
    $p = ['d' => today(), 'doc' => $staff['doctor_id']];

    json_out([
        'success'          => true,
        'assignedPatients' => $count("SELECT COUNT(*) FROM appointments WHERE appointment_date = :d AND doctor_id = :doc AND status IN ('Booked','CheckedIn')", $p),
        'waitingPatients'  => $count("SELECT COUNT(*) FROM appointments WHERE appointment_date = :d AND doctor_id = :doc AND status = 'CheckedIn' AND consultation_started_at IS NULL", $p),
        'completedToday'   => $count("SELECT COUNT(*) FROM appointments WHERE appointment_date = :d AND doctor_id = :doc AND status = 'Completed'", $p),
        'followups'        => $count("SELECT COUNT(*) FROM appointments WHERE appointment_date >= :d AND doctor_id = :doc AND is_followup = 1 AND status = 'Booked'", $p),
    ]);
} catch (PDOException $e) {
    fail('Could not load dashboard figures.', 500);
}
