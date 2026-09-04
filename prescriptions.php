<?php
require_once __DIR__ . '/includes/require-patient-login.php';
$activePage = 'prescriptions';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Prescriptions | PrimeCare Medical Centre</title>
<link rel="icon" type="image/png" href="assets/images/favicon.png">
<meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php require_once __DIR__ . '/includes/dash-header.php'; ?>

<main class="dash-main">
    <div class="container">
        <div class="dash-section">
            <h1 style="font-size:22px;margin-bottom:6px;">My prescriptions</h1>
            <p style="font-size:14px;">View your prescriptions and request repeats when you're due a refill.</p>
        </div>

        <div class="card" id="prescriptionsCard">
            <div class="skeleton" style="width:100%;height:20px;margin-bottom:14px;"></div>
            <div class="skeleton" style="width:80%;height:20px;margin-bottom:14px;"></div>
            <div class="skeleton" style="width:90%;height:20px;"></div>
        </div>
    </div>
</main>

<script src="assets/js/app.js"></script>
<script src="assets/js/prescriptions.js"></script>
</body>
</html>
