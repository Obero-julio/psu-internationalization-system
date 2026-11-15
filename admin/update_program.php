<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$programId = (int)($_POST['id'] ?? 0);
$programName = trim((string)($_POST['program_name'] ?? ''));
$programType = trim((string)($_POST['program_type'] ?? ''));
$status = trim((string)($_POST['status'] ?? ''));
$countryId = (int)($_POST['country_id'] ?? 0);
$partnerId = (int)($_POST['partner_id'] ?? 0);
$startDate = trim((string)($_POST['start_date'] ?? ''));
$endDate = trim((string)($_POST['end_date'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));

if (
    $programId <= 0 ||
    $programName === '' ||
    $programType === '' ||
    $status === '' ||
    $countryId <= 0 ||
    $partnerId <= 0 ||
    $startDate === '' ||
    $endDate === '' ||
    $description === ''
) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if ($endDate < $startDate) {
    echo json_encode(['success' => false, 'message' => 'End date cannot be before start date.']);
    exit;
}

try {
    $pdo = db();

    $partnerStmt = $pdo->prepare("
        SELECT institution_name
        FROM partners
        WHERE id = ? AND country_id = ? AND status = 'Active'
    ");
    $partnerStmt->execute([$partnerId, $countryId]);
    $partnerInstitution = $partnerStmt->fetchColumn();

    if ($partnerInstitution === false) {
        echo json_encode([
            'success' => false,
            'message' => 'The selected partner is not available for this country.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE programs SET
            program_name = ?,
            program_type = ?,
            status = ?,
            country_id = ?,
            partner_id = ?,
            partner_institution = ?,
            start_date = ?,
            end_date = ?,
            description = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $programName,
        $programType,
        $status,
        $countryId,
        $partnerId,
        $partnerInstitution,
        $startDate,
        $endDate,
        $description,
        $programId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Program updated successfully.'
    ]);
} catch (PDOException $e) {
    error_log('update_program.php error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Unable to update the program. Please try again.'
    ]);
}
