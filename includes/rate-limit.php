<?php
/**
 * PrimeCare — Rate Limiting Helper
 * ---------------------------------------------------------
 * Simple database-backed rate limiter: max N attempts per
 * identifier (IP address) + type, within a rolling time window.
 *
 * Usage in an endpoint (after including db.php):
 *   require_once __DIR__ . '/../../includes/rate-limit.php';
 *   check_rate_limit($pdo, 'login', 5, 15); // 5 attempts / 15 min
 *   // ... on failure, also call:
 *   record_attempt($pdo, 'login');
 */

function client_ip() {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Checks whether the current IP has exceeded the allowed number of
 * attempts for this type within the given window. If exceeded,
 * sends a 429 response and exits immediately.
 */
function check_rate_limit(PDO $pdo, string $type, int $maxAttempts, int $windowMinutes) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE identifier = :identifier
           AND attempt_type = :type
           AND attempted_at > (NOW() - INTERVAL :minutes MINUTE)"
    );
    $stmt->execute([
        'identifier' => client_ip(),
        'type'       => $type,
        'minutes'    => $windowMinutes,
    ]);
    $count = (int) $stmt->fetchColumn();

    if ($count >= $maxAttempts) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Too many attempts. Please wait a few minutes before trying again.'
        ]);
        exit;
    }
}

/** Records one attempt against this IP for this type. */
function record_attempt(PDO $pdo, string $type) {
    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts (identifier, attempt_type) VALUES (:identifier, :type)'
    );
    $stmt->execute(['identifier' => client_ip(), 'type' => $type]);
}
