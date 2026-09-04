<?php
/**
 * PrimeCare — Page-Level Auth Guard
 * ---------------------------------------------------------
 * Include this at the very top of any protected PAGE (not API
 * endpoint) — e.g. patient-dashboard.php, appointments.php —
 * before any HTML is output.
 *
 * Unlike includes/auth-check.php (which returns a JSON 401 for
 * API calls), this redirects the browser straight to the login
 * page, since a human loading a page expects a page back, not JSON.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['patient_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header('Location: login.html');
    exit;
}

// ---- Session inactivity timeout (30 minutes) ----
$inactivityLimit = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $inactivityLimit) {
    session_unset();
    session_destroy();
    header('Location: login.html?expired=1');
    exit;
}
$_SESSION['last_activity'] = time();
