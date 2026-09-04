<?php
/**
 * PrimeCare — List Departments Endpoint
 * ---------------------------------------------------------
 * GET /api/departments/list.php
 *
 * Intentionally NOT auth-protected: department names are
 * non-sensitive reference data (e.g. "General Practice",
 * "Pediatrics") needed to populate the booking form dropdown.
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query('SELECT department_id, department_name FROM departments ORDER BY department_name ASC');
    $departments = $stmt->fetchAll();

    echo json_encode(['success' => true, 'departments' => $departments]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load departments.']);
}
