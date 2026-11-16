<?php
declare(strict_types=1);
ob_start();
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

$pdo    = db();
$userId = (int)$_SESSION['user_id'];

/* Load full user row for the header + profile pages */
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    session_destroy();
    header('Location: ../public/login.php');
    exit;
}

/* Whitelist of pages — prevents arbitrary include */
$routes = [
    'home'                  => 'home.php',
    'available_programs'    => 'available_programs.php',
    'program_detail'        => 'program_detail.php',
    'apply'                 => 'apply.php',
    'my_applications'       => 'my_applications.php',
    'my_application_detail' => 'my_application_detail.php',
    'documents'             => 'documents.php',
    'notifications'         => 'notifications.php',
    'profile'               => 'profile.php',
    'settings'              => 'settings.php',
];

$page  = is_string($_GET['page'] ?? null) ? $_GET['page'] : 'home';
$route = $routes[$page] ?? $routes['home'];
$page  = array_search($route, $routes, true) ?: 'home'; // normalise

/* CSRF token */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* Shared helpers */
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function checkCsrf(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token']);
}

/* Stream authorized document downloads before rendering the portal shell. */
$downloadType = is_string($_GET['download_type'] ?? null) ? $_GET['download_type'] : '';
if ($page === 'documents' && $downloadType !== '') {
    $itemId = (int)($_GET['id'] ?? $_GET['req'] ?? 0);
    $storedPath = false;
    $disposition = 'inline';

    if ($downloadType === 'user_doc') {
        $stmt = $pdo->prepare('SELECT file_path FROM user_documents WHERE user_id = ? AND requirement_id = ? LIMIT 1');
        $stmt->execute([$userId, $itemId]);
        $storedPath = $stmt->fetchColumn();
    } elseif ($downloadType === 'template') {
        $stmt = $pdo->prepare("
            SELECT pr.requirement_file
            FROM program_requirements pr
            INNER JOIN users u ON u.program_id = pr.program_id
            WHERE pr.id = ? AND u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$itemId, $userId]);
        $storedPath = $stmt->fetchColumn();
        $disposition = 'attachment';
    } else {
        http_response_code(400);
        exit('Invalid download request.');
    }

    $relativePath = is_string($storedPath) ? str_replace('\\', '/', $storedPath) : '';
    if (
        $relativePath === ''
        || str_starts_with($relativePath, '/')
        || preg_match('~(^|/)\.\.(/|$)~', $relativePath)
    ) {
        http_response_code(404);
        exit('File not found.');
    }

    $uploadsRoot = realpath(__DIR__ . '/../uploads');
    $fullPath = realpath(__DIR__ . '/../' . $relativePath);
    if (
        $uploadsRoot === false
        || $fullPath === false
        || !str_starts_with($fullPath, $uploadsRoot . DIRECTORY_SEPARATOR)
        || !is_file($fullPath)
    ) {
        http_response_code(404);
        exit('File not found.');
    }

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($fullPath) ?: 'application/octet-stream';
    ob_clean();
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: ' . $disposition . '; filename="' . basename($fullPath) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($fullPath);
    exit;
}

$userName = trim((string)($user['first_name'] ?? 'Applicant'));
$pageStyles = [
    'available_programs' => ['available_programs.css'],
    'program_detail' => ['apply.css'],
    'apply' => ['apply.css'],
    'my_applications' => ['my_applications.css'],
    'my_application_detail' => ['my_applications.css'],
    'profile' => ['profile.css'],
    'settings' => ['apply.css'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PSU Internalization — Applicant Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../asset/css/dashboard.css">
    <link rel="stylesheet" href="../asset/css/user_accessibility.css">
    <link rel="stylesheet" href="../asset/css/upload_docs.css">
    <link rel="stylesheet" href="../asset/css/notifications.css">
    <?php foreach ($pageStyles[$page] ?? [] as $stylesheet): ?>
        <link rel="stylesheet" href="../asset/css/<?= h($stylesheet) ?>">
    <?php endforeach; ?>
</head>
<body>

<a class="skip-link" href="#mainContent">Skip to main content</a>
<?php include __DIR__ . '/sidebar.php'; ?>

<main class="container" id="mainContent">
    <header class="topbar">
        <div class="topbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Open menu" aria-controls="mainSidebar" aria-expanded="false">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="topbar-title"><?= h(ucwords(str_replace('_', ' ', $page))) ?></div>
        </div>
        <a class="user" href="?page=profile" aria-label="View profile for <?= h($userName) ?>">
            <span class="user-avatar" aria-hidden="true"><?= h(strtoupper(substr($userName, 0, 1))) ?></span>
            <span class="user-info">
                <span class="user-name"><?= h($userName) ?></span>
                <span class="user-role">Applicant</span>
            </span>
        </a>
    </header>

    <div class="page-body">
        <?php include __DIR__ . '/' . $route; ?>
    </div>
</main>

<script src="../asset/js/sidebar.js"></script>
</body>
</html>