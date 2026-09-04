<?php
/**
 * PrimeCare — Auth Check (shared include)
 * ---------------------------------------------------------
 * Every protected API endpoint (appointments, records,
 * prescriptions, profile) should require_once this file
 * FIRST, before doing anything else.
 *
 * This is the single place that enforces "you must be logged
 * in as a patient to call this endpoint" — never rely on the
 * frontend redirecting an unauthenticated user, since that
 * check can be bypassed by calling the API directly.
 *
 * Usage in a protected endpoint:
 *   require_once __DIR__ . '/../../includes/auth-check.php';
 *   // $patientId is now available below this line
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['patient_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to do that.'
    ]);
    exit;
}

// ---- Session inactivity timeout (30 minutes) ----
// Independent of Remember Me, which controls cookie persistence across
// browser restarts, not how long an open tab stays valid while idle.
$inactivityLimit = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $inactivityLimit) {
    session_unset();
    session_destroy();
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Your session has expired due to inactivity. Please log in again.'
    ]);
    exit;
}
$_SESSION['last_activity'] = time();

// ---- CSRF protection ----
// Only state-changing requests need this check — GET requests (like the
// various list.php endpoints) don't modify anything, so they're exempt.
// The token is issued at login (see api/auth/login.php) and echoed into
// every protected page via includes/dash-header.php, where app.js reads
// it and attaches it to every POST request automatically.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Your session could not be verified. Please refresh the page and try again.'
        ]);
        exit;
    }
}

// Convenience variable every protected endpoint can use directly.
// This also guarantees endpoints only ever query data belonging
// to the logged-in patient, not an ID supplied by the client.
$patientId = (int) $_SESSION['patient_id'];
