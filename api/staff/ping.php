<?php
/** GET /api/staff/ping.php — lets the app's Settings screen test the server address. */
require_once __DIR__ . '/../../includes/staff-auth.php';
json_out(['success' => true, 'message' => 'PrimeCare staff API is reachable.']);
