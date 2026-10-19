<?php
/** GET /api/staff/auth/me.php — validates the token and returns the profile. */
require_once __DIR__ . '/../../../includes/staff-auth.php';

$staff = require_staff($pdo);

json_out([
    'success'   => true,
    'role'      => $staff['role'],
    'staffId'   => $staff['staff_id'],
    'firstName' => $staff['first_name'],
    'lastName'  => $staff['last_name'],
    'email'     => $staff['email'],
]);
