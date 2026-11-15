<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/db.php';               // provides $pdo
require_once __DIR__ . '/php.php';              // your existing helpers (sendPasswordResetOTP)
require_once __DIR__ . '/password_reset_core.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'request':
            echo json_encode(requestPasswordReset($pdo, (string)($_POST['email'] ?? '')));
            break;

        case 'verify':
            echo json_encode(verifyResetOTP(
                $pdo,
                (string)($_POST['email'] ?? ''),
                (string)($_POST['otp']   ?? '')
            ));
            break;

        case 'reset':
            echo json_encode(resetPassword(
                $pdo,
                (string)($_POST['token']    ?? ''),
                (string)($_POST['password'] ?? '')
            ));
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Unknown action.']);
    }
} catch (Throwable $e) {
    error_log('[reset_api] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Server error.']);
}