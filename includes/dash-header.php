<?php
/**
 * PrimeCare — Shared Dashboard Header + Sub-navigation
 * ---------------------------------------------------------
 * Include on every protected patient page AFTER require-patient-login.php
 * has already run (so $_SESSION is guaranteed populated).
 *
 * Set $activePage before including this file to highlight the
 * current section, e.g.:
 *   $activePage = 'appointments';
 *   require_once __DIR__ . '/includes/dash-header.php';
 */

$firstName  = $_SESSION['first_name'] ?? 'Patient';
$lastName   = $_SESSION['last_name'] ?? '';
$email      = $_SESSION['email'] ?? '';
$initials   = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
$activePage = $activePage ?? '';

function navClass($page, $active) {
    return $page === $active ? 'active' : '';
}
?>
<header class="dash-topbar">
    <div class="dash-topbar-inner">
        <a href="index.html" class="logo">
            <img src="assets/images/logo-icon.png" alt="PrimeCare Medical Centre" class="logo-icon-img">
            PrimeCare Medical Centre
        </a>
        <div class="user-chip">
            <div class="avatar-circle"><?php echo htmlspecialchars($initials); ?></div>
            <div>
                <div class="user-name"><?php echo htmlspecialchars($firstName . ' ' . $lastName); ?></div>
                <div class="user-email"><?php echo htmlspecialchars($email); ?></div>
            </div>
            <button class="btn btn-ghost" id="logoutBtn" style="height:38px;padding:0 16px;">
                <svg class="icon" style="width:16px;height:16px;" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                Logout
            </button>
        </div>
    </div>
    <nav class="dash-subnav">
        <div class="dash-subnav-inner">
            <a href="patient-dashboard.php" class="<?php echo navClass('dashboard', $activePage); ?>">Dashboard</a>
            <a href="book-appointment.php" class="<?php echo navClass('book', $activePage); ?>">Book Appointment</a>
            <a href="appointments.php" class="<?php echo navClass('appointments', $activePage); ?>">My Appointments</a>
            <a href="medical-records.php" class="<?php echo navClass('records', $activePage); ?>">Medical Records</a>
            <a href="prescriptions.php" class="<?php echo navClass('prescriptions', $activePage); ?>">Prescriptions</a>
            <a href="profile.php" class="<?php echo navClass('profile', $activePage); ?>">Profile</a>
        </div>
    </nav>
</header>
