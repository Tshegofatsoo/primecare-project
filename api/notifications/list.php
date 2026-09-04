<?php
/**
 * PrimeCare — List Notifications Endpoint
 * ---------------------------------------------------------
 * GET /api/notifications/list.php
 * Requires an active patient session (see includes/auth-check.php).
 *
 * Returns the logged-in patient's 5 most recent notifications
 * plus their total unread count.
 */

require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare(
        "SELECT notification_id, title, message, type, is_read, created_at
         FROM notifications
         WHERE patient_id = :patient_id
         ORDER BY created_at DESC
         LIMIT 5"
    );
    $stmt->execute(['patient_id' => $patientId]);
    $notifications = $stmt->fetchAll();

    $unreadStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM notifications WHERE patient_id = :patient_id AND is_read = 0"
    );
    $unreadStmt->execute(['patient_id' => $patientId]);
    $unreadCount = (int) $unreadStmt->fetchColumn();

    echo json_encode([
        'success'       => true,
        'notifications' => $notifications,
        'unreadCount'   => $unreadCount,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load notifications.']);
}
