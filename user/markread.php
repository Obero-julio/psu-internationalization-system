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
            'message' => 'Unauthorized'
        ]);

        exit;
    }


    /* =========================
       GET REQUEST DATA
    ========================= */

    $data = json_decode(
        file_get_contents('php://input'),
        true
    );


    $id = $data['id'] ?? null;


    /* =========================
       VALIDATE ID
    ========================= */

    if (
        $id === null ||
        !filter_var($id, FILTER_VALIDATE_INT) ||
        (int)$id <= 0
    ) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid notification ID.'
        ]);

        exit;
    }


    $notificationId = (int)$id;
    $userId = (int)$_SESSION['user_id'];


    /* =========================
       DATABASE
    ========================= */

    $pdo = db();


    /* =========================
       MARK AS READ
    ========================= */

    $stmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE id = ?
          AND user_id = ?
          AND is_read = 0
    ");

    $stmt->execute([
        $notificationId,
        $userId
    ]);


    /* =========================
       CHECK RESULT
    ========================= */

    if ($stmt->rowCount() > 0) {

        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as read.'
        ]);

    } else {

        /*
         * Could mean:
         * - notification doesn't exist
         * - notification belongs to another user
         * - notification was already read
         */

        echo json_encode([
            'success' => true,
            'message' => 'Notification is already read.'
        ]);
    }


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