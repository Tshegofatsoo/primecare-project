<?php
require_once __DIR__ . '/includes/require-patient-login.php';
$activePage = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile | PrimeCare Medical Centre</title>
<link rel="icon" type="image/png" href="assets/images/favicon.png">
<meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php require_once __DIR__ . '/includes/dash-header.php'; ?>

<main class="dash-main">
    <div class="container">
        <div class="dash-section">
            <h1 style="font-size:22px;margin-bottom:6px;">My profile</h1>
            <p style="font-size:14px;">Manage your personal details and account security.</p>
        </div>

        <div class="contact-inner">
            <!-- ============ PERSONAL INFORMATION ============ -->
            <div class="card">
                <h3 style="font-size:16px;margin-bottom:18px;">Personal information</h3>
                <p class="form-banner" id="profileBanner"></p>

                <form id="profileForm" novalidate>
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="firstName">First name</label>
                            <input type="text" id="firstName" name="firstName">
                            <p class="field-error" id="err-firstName">Please enter your first name.</p>
                        </div>
                        <div class="form-group">
                            <label for="lastName">Last name</label>
                            <input type="text" id="lastName" name="lastName">
                            <p class="field-error" id="err-lastName">Please enter your last name.</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email address</label>
                        <div class="field-static" id="profileEmail">—</div>
                        <p class="field-hint">Contact PrimeCare reception to change your registered email.</p>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone number</label>
                        <input type="tel" id="phone" name="phone">
                        <p class="field-error" id="err-phone">Please enter a valid phone number.</p>
                    </div>

                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" placeholder="Street, suburb, city"></textarea>
                        <p class="field-error" id="err-address">Address is too long.</p>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="emergencyContactName">Emergency contact name</label>
                            <input type="text" id="emergencyContactName" name="emergencyContactName" placeholder="e.g. Naledi Dube">
                            <p class="field-error" id="err-emergencyContactName">Emergency contact name is too long.</p>
                        </div>
                        <div class="form-group">
                            <label for="emergencyContactPhone">Emergency contact phone</label>
                            <input type="tel" id="emergencyContactPhone" name="emergencyContactPhone" placeholder="082 123 4567">
                            <p class="field-error" id="err-emergencyContactPhone">Please enter a valid phone number.</p>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" id="profileBtn">Save changes</button>
                </form>
            </div>

            <!-- ============ CHANGE PASSWORD ============ -->
            <div class="card">
                <h3 style="font-size:16px;margin-bottom:18px;">Change password</h3>
                <p class="form-banner" id="passwordBanner"></p>

                <form id="passwordForm" novalidate>
                    <div class="form-group">
                        <label for="currentPassword">Current password</label>
                        <div class="password-input-wrap">
                            <input type="password" id="currentPassword" name="currentPassword" autocomplete="current-password">
                            <button type="button" class="password-toggle" id="toggleCurrentPassword" aria-label="Show password">
                                <svg class="icon" style="width:18px;height:18px;" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <p class="field-error" id="err-currentPassword">Please enter your current password.</p>
                    </div>

                    <div class="form-group">
                        <label for="newPassword">New password</label>
                        <div class="password-input-wrap">
                            <input type="password" id="newPassword" name="newPassword" autocomplete="new-password">
                            <button type="button" class="password-toggle" id="toggleNewPassword" aria-label="Show password">
                                <svg class="icon" style="width:18px;height:18px;" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <p class="field-hint">At least 8 characters, with a letter and a number.</p>
                        <p class="field-error" id="err-newPassword">Password does not meet the requirements.</p>
                    </div>

                    <div class="form-group">
                        <label for="confirmNewPassword">Confirm new password</label>
                        <div class="password-input-wrap">
                            <input type="password" id="confirmNewPassword" name="confirmNewPassword" autocomplete="new-password">
                            <button type="button" class="password-toggle" id="toggleConfirmNewPassword" aria-label="Show password">
                                <svg class="icon" style="width:18px;height:18px;" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <p class="field-error" id="err-confirmNewPassword">Passwords do not match.</p>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" id="passwordBtn">Change password</button>
                </form>
            </div>
        </div>
    </div>
</main>

<script src="assets/js/app.js"></script>
<script src="assets/js/profile.js"></script>
</body>
</html>
