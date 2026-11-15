
<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['user_role'] ?? '', ['admin', 'super admin'], true)
) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid application ID.']);
    exit;
}

try {
    $pdo = db();

    $stmt = $pdo->prepare("
        DELETE FROM applications
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Application not found or already deleted.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Application deleted successfully.'
    ]);

} catch (Throwable $e) {
    error_log($e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to delete application.'
    ]);
}
