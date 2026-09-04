<?php
require_once __DIR__ . '/includes/require-patient-login.php';
$activePage = 'appointments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Appointments | PrimeCare Medical Centre</title>
<link rel="icon" type="image/png" href="assets/images/favicon.png">
<meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php require_once __DIR__ . '/includes/dash-header.php'; ?>

<main class="dash-main">
    <div class="container">
        <div class="dash-section">
            <h1 style="font-size:22px;margin-bottom:6px;">My appointments</h1>
            <p style="font-size:14px;">View your appointment history and manage upcoming visits.</p>
        </div>

        <div class="tabs">
            <button class="tab-btn active" data-tab="upcoming">Upcoming</button>
            <button class="tab-btn" data-tab="past">Past &amp; cancelled</button>
        </div>

        <div class="card" id="appointmentsCard">
            <div class="skeleton" style="width:100%;height:20px;margin-bottom:14px;"></div>
            <div class="skeleton" style="width:80%;height:20px;margin-bottom:14px;"></div>
            <div class="skeleton" style="width:90%;height:20px;"></div>
        </div>
    </div>
</main>

<!-- ============ RESCHEDULE MODAL ============ -->
<div class="modal-overlay" id="rescheduleModal">
    <div class="card modal-panel">
        <div class="modal-header">
            <h3>Reschedule appointment</h3>
            <button class="modal-close" id="rescheduleCloseBtn" aria-label="Close">
                <svg class="icon" style="width:20px;height:20px;" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <p class="form-banner" id="rescheduleBanner"></p>

        <div class="form-group">
            <label for="rescheduleDate">New date</label>
            <input type="date" id="rescheduleDate">
            <p class="field-hint">Mon–Fri 08:00–17:00 · Sat 08:00–13:00 · Closed Sundays.</p>
            <p class="field-error" id="err-rescheduleDate">Please select a valid date.</p>
        </div>

        <div class="form-group">
            <label>New time</label>
            <div class="slot-grid" id="rescheduleSlotGrid">
                <p class="field-hint" style="grid-column:1/-1;">Select a date to see available times.</p>
            </div>
            <input type="hidden" id="rescheduleTime">
            <p class="field-error" id="err-rescheduleTime">Please select a time slot.</p>
        </div>

        <div class="modal-actions">
            <button class="btn btn-secondary" id="rescheduleCancelBtn" type="button">Cancel</button>
            <button class="btn btn-primary" id="rescheduleConfirmBtn" type="button">Confirm reschedule</button>
        </div>
    </div>
</div>

<script src="assets/js/app.js"></script>
<script src="assets/js/appointments.js"></script>
</body>
</html>
