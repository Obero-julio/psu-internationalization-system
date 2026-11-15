<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../index.php?page=login');
    exit();
}

$role = strtolower(trim($_SESSION['user_role'] ?? ''));
if (in_array($role, ['admin', 'super admin'], true)) {
    // Admin session should never land in the user portal
    session_unset();
    session_destroy();
    header('Location: ../admin/dashboard.php'); // or ../index.php?page=login
    exit();
}

$page = trim((string) ($_GET['page'] ?? 'home'));

$validPages = [
    'home', 'available_programs', 'apply_program', 'my_applications',
    'reports', 'upload_documents', 'download_forms', 'submitted_forms',
    'travel_info', 'activity_reports', 'notifications', 'profile',
    'change_password', 'delete_account',
    'announcements', 'mobility_schedule' // <-- ADD THESE
];

if (!in_array($page, $validPages, true)) {
    $page = 'home';
}

$userId    = (int) $_SESSION['user_id'];
$firstName = (string) ($_SESSION['first_name'] ?? 'Applicant');
$initial   = strtoupper(substr($firstName, 0, 1));

// Unread notification count
$unreadCount = 0;
try {
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0');
    $stmt->execute([':uid' => $userId]);
    $unreadCount = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log('[home] unread count: ' . $e->getMessage());
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Portal | PSU Internationalization</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- Core Layout -->
    <link rel="stylesheet" href="../asset/css/home.css">
    <!-- Page Specific Styles -->
    <link rel="stylesheet" href="../asset/css/user_dashboard.css">
    <link rel="stylesheet" href="../asset/css/reports.css">
</head>
<body>

<div class="portal-shell">

    <?php include 'ap_sidebar.php'; ?>

    <!-- MAIN -->
    <main class="main-content">
        <header class="topbar">
            <button class="mobile-menu" type="button" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="topbar-spacer"></div>

            <div class="topbar-actions">
                <button class="icon-button notification-button" type="button" aria-label="Notifications" onclick="window.location.href='?page=notifications'">
                    <i class="fa-regular fa-bell"></i>
                    <?php if ($unreadCount > 0): ?><span><?= $unreadCount ?></span><?php endif; ?>
                </button>

                <div class="account-menu">
                    <button class="account-trigger" type="button" aria-expanded="false" aria-haspopup="true">
                        <div class="account-avatar"><?= htmlspecialchars($initial) ?></div>
                        <div>
                            <b><?= htmlspecialchars($firstName) ?></b>
                            <small>Applicant</small>
                        </div>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>

                    <div class="account-dropdown">
                        <div class="account-dropdown-header">
                            <b><?= htmlspecialchars($firstName) ?></b>
                            <small>Applicant Account</small>
                        </div>
                        <a href="?page=profile"><i class="fa-regular fa-user"></i><span>Profile</span></a>
                        <a href="?page=change_password"><i class="fa-solid fa-key"></i><span>Change Password</span></a>
                        <a href="?page=delete_account" class="danger"><i class="fa-regular fa-trash-can"></i><span>Delete Account</span></a>
                        <div class="dropdown-divider"></div>
                        <a class="logout" href="../public/user_logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i><span>Log out</span></a>
                    </div>
                </div>
            </div>
        </header>

        <?php
        $pageFiles = [
            'home'                => 'dashboard.php',
            'reports'             => 'reports.php',
            'available_programs'  => 'available_programs.php',
            'apply_program'       => 'apply_program.php',
            'my_applications'     => 'my_applications.php',
            'upload_documents'    => 'upload_documents.php',
            'download_forms'      => 'download_forms.php',
            'submitted_forms'     => 'submitted_forms.php',
            'travel_info'         => 'travel_information.php',
            'activity_reports'    => 'activity_reports.php',
            'notifications'       => 'user_notifications.php',
            'profile'             => 'profile.php',
            'change_password'     => 'change_password.php',
            'delete_account'      => 'delete_account.php',
            'announcements'       => 'announcements.php',
            'mobility_schedule'   => 'mobility_sched.php',
        ];

        $file = __DIR__ . '/' . $pageFiles[$page];

        if (!is_file($file)) {
            http_response_code(404);
            echo '<div class="page-box">';
            echo '<h2>Page not found</h2>';
            echo '<p>This module is not available yet.</p>';
            echo '</div>';
        } elseif ($page === 'home') {
            include $file;
        } else {
            echo '<div class="page-box">';
            include $file;
            echo '</div>';
        }
        ?>
    </main>
</div>

<script src="../asset/js/home.js"></script>
</body>
</html>