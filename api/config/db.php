<?php
/**
 * PrimeCare — Database Connection
 * ---------------------------------------------------------
 * Single, shared PDO connection used by every endpoint in api/.
 * Update the constants below to match your local MySQL setup
 * (e.g. XAMPP/WAMP default is host=localhost, user=root, pass="").
 *
 * IMPORTANT: In a real deployment, these values should come from
 * environment variables or a config file OUTSIDE the web root —
 * never committed to version control with real credentials.
 */

// ---- Connection settings (edit these for your environment) ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'primecare_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // throw exceptions on SQL errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // return rows as associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                   // use real prepared statements (SQL injection protection)
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Never leak raw DB error details (hostnames, credentials) to the client.
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed. Please try again later.'
    ]);
    exit;
}
