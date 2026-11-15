
<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function respond(int $status, bool $success, string $message, $data = null): void
{
    http_response_code($status);

    echo json_encode([
        'status'  => $status,
        'success' => $success,
        'message' => $message,
        'data'    => $data
    ], JSON_UNESCAPED_UNICODE);

    exit();
}

// AUTH
if (!isset($_SESSION['user_id'])) {
    respond(401, false, 'Unauthorized. Please log in.');
}

// METHOD
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(405, false, 'Method not allowed.');
}

// INPUT
$countryId = filter_input(INPUT_GET, 'country_id', FILTER_VALIDATE_INT);

if ($countryId === false || $countryId === null || $countryId < 1) {
    respond(422, false, 'Invalid country_id.');
}

try {
    $pdo = db();

    // Check selected country
    $chk = $pdo->prepare("
        SELECT id
        FROM countries_addresses
        WHERE id = ?
        LIMIT 1
    ");

    $chk->execute([$countryId]);

    if (!$chk->fetchColumn()) {
        respond(404, false, 'Country not found.');
    }

    // Fetch partner institutions
    $stmt = $pdo->prepare("
        SELECT id, institution_name
        FROM partners
        WHERE country_id = ?
          AND LOWER(status) = 'active'
        ORDER BY institution_name ASC
    ");

    $stmt->execute([$countryId]);

    $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respond(
        200,
        true,
        empty($partners) ? 'No partners in this country.' : 'Partners fetched successfully.',
        $partners
    );

} catch (PDOException $e) {
    error_log('get_partners_by_country.php: ' . $e->getMessage());

    respond(500, false, 'Database error occurred.');

} catch (Throwable $e) {
    error_log('get_partners_by_country.php: ' . $e->getMessage());

    respond(500, false, 'Unexpected error occurred.');
}
