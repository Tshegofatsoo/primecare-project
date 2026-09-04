<?php
/**
 * PrimeCare — Update Profile Endpoint
 * ---------------------------------------------------------
 * POST /api/profile/update.php
 * Body (JSON): { firstName, lastName, phone, address, emergencyContactName, emergencyContactPhone }
 * Requires an active patient session.
 *
 * Deliberately does NOT accept email here — changing a login
 * identifier safely needs its own re-verification flow, which
 * is out of scope for this update form.
 */

require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request format.']);
    exit;
}

$firstName             = trim($input['firstName'] ?? '');
$lastName              = trim($input['lastName'] ?? '');
$phone                 = trim($input['phone'] ?? '');
$address               = trim($input['address'] ?? '');
$emergencyContactName  = trim($input['emergencyContactName'] ?? '');
$emergencyContactPhone = trim($input['emergencyContactPhone'] ?? '');

$errors = [];

if ($firstName === '' || mb_strlen($firstName) > 50) {
    $errors['firstName'] = 'Please enter a valid first name.';
}
if ($lastName === '' || mb_strlen($lastName) > 50) {
    $errors['lastName'] = 'Please enter a valid last name.';
}

$phoneDigits = preg_replace('/[\s\-()]/', '', $phone);
if (!preg_match('/^\+?\d{7,15}$/', $phoneDigits)) {
    $errors['phone'] = 'Please enter a valid phone number.';
}

if (mb_strlen($address) > 255) {
    $errors['address'] = 'Address is too long.';
}

// Emergency contact is optional — only validate fields that were actually
// filled in, rather than forcing every patient to supply one.
if ($emergencyContactName !== '' && mb_strlen($emergencyContactName) > 100) {
    $errors['emergencyContactName'] = 'Emergency contact name is too long.';
}

$emergencyPhoneDigits = preg_replace('/[\s\-()]/', '', $emergencyContactPhone);
if ($emergencyContactPhone !== '' && !preg_match('/^\+?\d{7,15}$/', $emergencyPhoneDigits)) {
    $errors['emergencyContactPhone'] = 'Please enter a valid emergency contact phone number.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $errors]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'UPDATE patients
         SET first_name = :first_name, last_name = :last_name, phone = :phone, address = :address,
             emergency_contact_name = :ec_name, emergency_contact_phone = :ec_phone
         WHERE patient_id = :patient_id'
    );
    $stmt->execute([
        'first_name' => $firstName,
        'last_name'  => $lastName,
        'phone'      => $phoneDigits,
        'address'    => $address !== '' ? $address : null,
        'ec_name'    => $emergencyContactName !== '' ? $emergencyContactName : null,
        'ec_phone'   => $emergencyContactPhone !== '' ? $emergencyPhoneDigits : null,
        'patient_id' => $patientId,
    ]);

    // Keep the session in sync with the updated name so the header
    // reflects the change immediately without requiring re-login.
    $_SESSION['first_name'] = $firstName;
    $_SESSION['last_name']  = $lastName;

    echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not update your profile. Please try again.']);
}
