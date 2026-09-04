<?php
/**
 * PrimeCare — Logout Endpoint
 * ---------------------------------------------------------
 * POST /api/auth/logout.php
 * Destroys the current PHP session completely, including the
 * session cookie, so a Remember Me session can't be reused
 * after the patient explicitly logs out.
 */

header('Content-Type: application/json');

session_start();

// Clear all session data
$_SESSION = [];

// Remove the session cookie itself from the browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
