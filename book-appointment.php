<?php
require_once __DIR__ . '/includes/require-patient-login.php';
$activePage = 'book';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book Appointment | PrimeCare Medical Centre</title>
<link rel="icon" type="image/png" href="assets/images/favicon.png">
<meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php require_once __DIR__ . '/includes/dash-header.php'; ?>

<main class="dash-main">
    <div class="container">
        <div class="dash-section">
            <h1 style="font-size:22px;margin-bottom:6px;">Book an appointment</h1>
            <p style="font-size:14px;">Choose a department, date, and time that works for you.</p>
        </div>

        <div class="contact-inner">
            <!-- ============ BOOKING FORM ============ -->
            <div class="card">
                <p class="form-banner" id="formBanner"></p>

                <form id="bookingForm" novalidate>
                    <div class="form-group">
                        <label for="departmentId">Department</label>
                        <select id="departmentId" name="departmentId">
                            <option value="">Loading departments...</option>
                        </select>
                        <p class="field-error" id="err-departmentId">Please select a department.</p>
                    </div>

                    <div class="form-group">
                        <label for="apptDate">Date</label>
                        <input type="date" id="apptDate" name="apptDate">
                        <p class="field-hint">Mon–Fri 08:00–17:00 · Sat 08:00–13:00 · Closed Sundays.</p>
                        <p class="field-error" id="err-apptDate">Please select a valid date.</p>
                    </div>

                    <div class="form-group">
                        <label>Time</label>
                        <div class="slot-grid" id="slotGrid">
                            <p class="field-hint" style="grid-column:1/-1;">Select a date to see available times.</p>
                        </div>
                        <input type="hidden" id="apptTime" name="apptTime">
                        <p class="field-error" id="err-apptTime">Please select a time slot.</p>
                    </div>

                    <div class="form-group">
                        <label for="reason">Reason for visit</label>
                        <textarea id="reason" name="reason" placeholder="Briefly describe why you'd like to be seen"></textarea>
                        <p class="field-error" id="err-reason">Please describe the reason for your visit.</p>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" id="bookBtn">Book appointment</button>
                </form>
            </div>

            <!-- ============ SIDEBAR: UPCOMING ============ -->
            <div>
                <div class="dash-section-title">
                    <h2 style="font-size:16px;">Your upcoming appointments</h2>
                </div>
                <div class="card" id="sidebarUpcoming">
                    <div class="skeleton" style="width:100%;height:16px;margin-bottom:10px;"></div>
                    <div class="skeleton" style="width:70%;height:16px;"></div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="assets/js/app.js"></script>
<script src="assets/js/booking.js"></script>
</body>
</html>
