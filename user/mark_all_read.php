<?php

require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

try {

    /* =========================
       CHECK LOGIN
    ========================= */

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'You are not logged in.'
        ]);

        exit;
    }


    /* =========================
       DATABASE
    ========================= */

    $pdo = db();

    $userId = (int) $_SESSION['user_id'];


    /* =========================
       MARK ALL AS READ
    ========================= */

    $stmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE user_id = ?
          AND is_read = 0
    ");

    $stmt->execute([$userId]);


    /* =========================
       RESPONSE
    ========================= */

    echo json_encode([
        'success' => true,
        'message' => 'All notifications marked as read.',
        'updated' => $stmt->rowCount()
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred.'
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.'
    ]);
}