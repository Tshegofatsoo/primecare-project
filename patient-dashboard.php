<?php
/**
 * PrimeCare — Patient Dashboard
 * ---------------------------------------------------------
 * This page is session-protected: require-patient-login.php
 * redirects to login.html immediately if there's no valid
 * patient session, before any HTML below is ever sent.
 */
require_once __DIR__ . '/includes/require-patient-login.php';

$firstName = $_SESSION['first_name'] ?? 'Patient';
$lastName  = $_SESSION['last_name'] ?? '';
$email     = $_SESSION['email'] ?? '';
$initials  = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));

$hour = (int) date('H');
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | PrimeCare Medical Centre</title>
<link rel="icon" type="image/png" href="assets/images/favicon.png">
<meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php require_once __DIR__ . '/includes/dash-header.php'; ?>

<main class="dash-main">
    <div class="container">

        <!-- ============ WELCOME CARD ============ -->
        <div class="card welcome-card dash-section">
            <div>
                <h1><?php echo htmlspecialchars($greeting . ', ' . $firstName); ?></h1>
                <p>Here's what's happening with your care today.</p>
            </div>
            <div class="stat-chips">
                <div class="stat-chip">
                    <strong id="statUpcoming">—</strong>
                    <span>Upcoming</span>
                </div>
                <div class="stat-chip">
                    <strong id="statUnread">—</strong>
                    <span>Unread</span>
                </div>
            </div>
        </div>

        <!-- ============ UPCOMING APPOINTMENT ============ -->
        <div class="dash-section">
            <div class="dash-section-title">
                <h2>Upcoming appointment</h2>
                <a href="appointments.php">View all</a>
            </div>
            <div class="card" id="upcomingCard">
                <div style="display:flex;gap:16px;align-items:center;">
                    <div class="skeleton" style="width:64px;height:64px;"></div>
                    <div style="flex:1;">
                        <div class="skeleton" style="width:60%;height:16px;margin-bottom:8px;"></div>
                        <div class="skeleton" style="width:40%;height:12px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ QUICK ACTIONS ============ -->
        <div class="dash-section">
            <div class="dash-section-title">
                <h2>Quick actions</h2>
            </div>
            <div class="quick-actions-grid">
                <a href="book-appointment.php" class="card quick-action-card">
                    <div class="icon-box">
                        <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 2v4M16 2v4M12 14v4M10 16h4"/></svg>
                    </div>
                    <span>Book Appointment</span>
                </a>
                <a href="appointments.php" class="card quick-action-card">
                    <div class="icon-box">
                        <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 2v4M16 2v4M8 14h.01M12 14h4M8 18h.01M12 18h4"/></svg>
                    </div>
                    <span>My Appointments</span>
                </a>
                <a href="medical-records.php" class="card quick-action-card">
                    <div class="icon-box">
                        <svg class="icon" viewBox="0 0 24 24"><path d="M9 12h6M9 16h6M9 8h2"/><path d="M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/></svg>
                    </div>
                    <span>Medical Records</span>
                </a>
                <a href="prescriptions.php" class="card quick-action-card">
                    <div class="icon-box">
                        <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="9" width="18" height="6" rx="3" transform="rotate(-45 12 12)"/><path d="M8.5 15.5l7-7" stroke-opacity="0.4"/></svg>
                    </div>
                    <span>My Prescriptions</span>
                </a>
                <a href="profile.php" class="card quick-action-card">
                    <div class="icon-box">
                        <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.5-7 8-7s8 3 8 7"/></svg>
                    </div>
                    <span>Profile</span>
                </a>
            </div>
        </div>

        <!-- ============ NOTIFICATIONS ============ -->
        <div class="dash-section">
            <div class="dash-section-title">
                <h2>Notifications</h2>
            </div>
            <div class="card" id="notificationsCard">
                <div class="skeleton" style="width:100%;height:16px;margin-bottom:14px;"></div>
                <div class="skeleton" style="width:80%;height:16px;margin-bottom:14px;"></div>
                <div class="skeleton" style="width:90%;height:16px;"></div>
            </div>
        </div>

    </div>
</main>

<script src="assets/js/app.js"></script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
