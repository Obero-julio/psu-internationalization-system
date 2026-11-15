<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$pdo = db();

$id           = (int)($_POST['id'] ?? 0);
$title        = trim($_POST['title'] ?? '');
$announcement = trim($_POST['announcement'] ?? '');
$publish_date = trim($_POST['publish_date'] ?? '');
$status       = trim($_POST['status'] ?? 'draft');

if ($title === '' || $announcement === '' || $publish_date === '') {
    echo json_encode(['ok' => false, 'message' => 'Title, content, and date are required.']);
    exit();
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE announcements SET title = ?, announcement = ?, publish_date = ?, status = ? WHERE id = ?");
        $stmt->execute([$title, $announcement, $publish_date, $status, $id]);
        echo json_encode(['ok' => true, 'message' => 'Announcement updated.']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO announcements (title, announcement, publish_date, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $announcement, $publish_date, $status]);
        echo json_encode(['ok' => true, 'message' => 'Announcement created.']);
    }
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'message' => 'Database error.']);
}