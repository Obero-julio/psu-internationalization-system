
<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();

    $country_id = filter_input(
        INPUT_GET,
        'country_id',
        FILTER_VALIDATE_INT
    );

    if (!$country_id || $country_id < 1) {
        http_response_code(422);
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, institution_name
        FROM partners
        WHERE country_id = ?
          AND LOWER(status) = 'active'
        ORDER BY institution_name ASC
    ");

    $stmt->execute([$country_id]);

    echo json_encode(
        $stmt->fetchAll(PDO::FETCH_ASSOC),
        JSON_UNESCAPED_UNICODE
    );

} catch (PDOException $e) {
    error_log('get_institution.php: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([]);
}
