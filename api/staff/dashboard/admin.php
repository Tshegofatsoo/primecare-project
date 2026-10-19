<?php
/** GET /api/staff/dashboard/admin.php (ADMIN) — dashboard counters. */
require_once __DIR__ . '/../../../includes/staff-auth.php';
require_staff($pdo, ['ADMIN']);

try {
    $count = function (string $sql, array $p = []) use ($pdo): int {
        $s = $pdo->prepare($sql);
        $s->execute($p);
        return (int) $s->fetchColumn();
    };
    $t = ['d' => today()];

    json_out([
        'success'            => true,
        'todaysAppointments' => $count("SELECT COUNT(*) FROM appointments WHERE appointment_date = :d AND status <> 'Cancelled'", $t),
        'patientsWaiting'    => $count("SELECT COUNT(*) FROM appointments WHERE appointment_date = :d AND status = 'CheckedIn' AND consultation_started_at IS NULL", $t),
        'walkinsToday'       => $count("SELECT COUNT(*) FROM appointments WHERE appointment_date = :d AND is_walkin = 1", $t),
        'availableDoctors'   => $count("SELECT COUNT(*) FROM doctors WHERE status = 'Active'"),
    ]);
} catch (PDOException $e) {
    fail('Could not load dashboard figures.', 500);
}
