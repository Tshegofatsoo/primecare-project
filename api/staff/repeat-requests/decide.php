<?php
/**
 * POST /api/staff/repeat-requests/decide.php  (DOCTOR)
 * Body: { requestId, approve (bool) }
 */
require_once __DIR__ . '/../../../includes/staff-auth.php';
require_method('POST');
$staff = require_staff($pdo, ['DOCTOR']);

$in = read_json();
$id = (int) ($in['requestId'] ?? 0);
$approve = !empty($in['approve']);
if ($id <= 0) { fail('Invalid request.', 422); }

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        "SELECT r.request_id, r.patient_id, pr.medication_name
         FROM prescription_requests r JOIN prescriptions pr ON pr.prescription_id = r.prescription_id
         WHERE r.request_id = :id AND r.status = 'Requested' FOR UPDATE"
    );
    $stmt->execute(['id' => $id]);
    $req = $stmt->fetch();
    if (!$req) {
        $pdo->rollBack();
        fail('That request was already handled or no longer exists.', 409);
    }

    $pdo->prepare(
        'UPDATE prescription_requests SET status = :s, reviewed_by = :d, reviewed_at = :now WHERE request_id = :id'
    )->execute(['s' => $approve ? 'Approved' : 'Declined', 'd' => $staff['doctor_id'], 'now' => now_dt(), 'id' => $id]);

    notify_patient(
        $pdo, (int) $req['patient_id'],
        $approve ? 'Repeat prescription approved' : 'Repeat prescription declined',
        $approve
            ? 'Your repeat request for ' . $req['medication_name'] . ' was approved.'
            : 'Your repeat request for ' . $req['medication_name'] . ' was declined. Please contact the clinic.',
        'Prescription'
    );
    $pdo->commit();

    json_out(['success' => true, 'message' => $approve ? 'Repeat request approved.' : 'Repeat request declined.']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fail('Could not update the request.', 500);
}
