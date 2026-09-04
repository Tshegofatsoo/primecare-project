<?php
/**
 * PrimeCare — List Appointments Endpoint
 * ---------------------------------------------------------
 * GET /api/appointments/list.php
 * Requires an active patient session (see includes/auth-check.php).
 *
 * Returns the logged-in patient's upcoming appointments only —
 * $patientId comes from the session, never from client input,
 * so a patient can never query another patient's appointments.
 */

require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// scope=upcoming (default, used by the dashboard) or scope=all (used by
// the full "My Appointments" page, which shows history too).
$scope = ($_GET['scope'] ?? 'upcoming') === 'all' ? 'all' : 'upcoming';

try {
    if ($scope === 'upcoming') {
        $stmt = $pdo->prepare(
            "SELECT
                a.appointment_id, a.appointment_date, a.appointment_time,
                a.reason, a.status,
                d.first_name AS doctor_first_name, d.last_name AS doctor_last_name,
                dep.department_name
             FROM appointments a
             LEFT JOIN doctors d       ON a.doctor_id = d.doctor_id
             LEFT JOIN departments dep ON a.department_id = dep.department_id
             WHERE a.patient_id = :patient_id
               AND a.status IN ('Booked', 'CheckedIn')
               AND a.appointment_date >= CURDATE()
             ORDER BY a.appointment_date ASC, a.appointment_time ASC"
        );
        $stmt->execute(['patient_id' => $patientId]);
        $rows = $stmt->fetchAll();

        echo json_encode([
            'success'  => true,
            'count'    => count($rows),
            'next'     => $rows[0] ?? null,
            'upcoming' => $rows,
        ]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT
                a.appointment_id, a.appointment_date, a.appointment_time,
                a.reason, a.status,
                d.first_name AS doctor_first_name, d.last_name AS doctor_last_name,
                dep.department_name
             FROM appointments a
             LEFT JOIN doctors d       ON a.doctor_id = d.doctor_id
             LEFT JOIN departments dep ON a.department_id = dep.department_id
             WHERE a.patient_id = :patient_id
             ORDER BY a.appointment_date DESC, a.appointment_time DESC"
        );
        $stmt->execute(['patient_id' => $patientId]);
        $rows = $stmt->fetchAll();

        echo json_encode([
            'success'      => true,
            'count'        => count($rows),
            'appointments' => $rows,
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load appointments.']);
}
