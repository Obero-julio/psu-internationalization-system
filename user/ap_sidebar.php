<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Use $currentPage consistently, fallback to 'home' if not set
$currentPage = $_GET['page'] ?? 'home';

$userId = $_SESSION['user_id'] ?? 0;
$unreadCount = 0;
try {
    // Assuming db() is available via config, but adding a check just in case
    if (function_exists('db')) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0');
        $stmt->execute([':uid' => $userId]);
        $unreadCount = (int) $stmt->fetchColumn();
    }
} catch (Throwable $e) {
    // Ignore silent errors on unread counts
}
?>

<aside class="sidebar" id="sidebar">
    <div class="brand">
        <img src="../asset/img/psu_logo.png" alt="PSU logo">
        <div>
            <strong>PSU</strong>
            <b>INTERNATIONALIZATION</b>
            <small>APPLICANT PORTAL</small>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">MAIN</div>
        <!-- Using $currentPage to match the logic we set up earlier -->
        <a href="?page=home" class="nav-link <?= ($currentPage ?? $page ?? '') === 'home' ? 'active' : '' ?>">
            <i class="fa-solid fa-house"></i> <span>Home</span>
        </a>
        <a href="?page=my_applications" class="nav-link <?= ($currentPage ?? $page ?? '') === 'my_applications' ? 'active' : '' ?>">
            <i class="fa-regular fa-file-lines"></i> <span>My Applications</span>
        </a>
        <a href="?page=reports" class="nav-link <?= ($currentPage ?? $page ?? '') === 'reports' ? 'active' : '' ?>">
            <i class="fa-regular fa-clock"></i> <span>Application Status</span>
        </a>
        <a href="?page=upload_documents" class="nav-link <?= ($currentPage ?? $page ?? '') === 'upload_documents' ? 'active' : '' ?>">
            <i class="fa-solid fa-arrow-up-from-bracket"></i> <span>Upload Documents</span>
        </a>
        <a href="?page=download_forms" class="nav-link <?= ($currentPage ?? $page ?? '') === 'download_forms' ? 'active' : '' ?>">
            <i class="fa-regular fa-pen-to-square"></i> <span>Forms & Requirements</span>
        </a>

        <div class="nav-section">PROGRAMS</div>
        <a href="?page=available_programs" class="nav-link <?= ($currentPage ?? $page ?? '') === 'available_programs' ? 'active' : '' ?>">
            <i class="fa-solid fa-compass"></i> <span>Available Programs</span>
        </a>
        <a href="?page=partner_directory" class="nav-link <?= ($currentPage ?? $page ?? '') === 'partner_directory' ? 'active' : '' ?>">
            <i class="fa-solid fa-address-book"></i> <span>Partner Directory</span>
        </a>
        <a href="?page=mobility_schedule" class="nav-link <?= ($currentPage ?? $page ?? '') === 'mobility_schedule' ? 'active' : '' ?>">
            <i class="fa-regular fa-calendar-days"></i> <span>Mobility Schedule</span>
        </a>

        <div class="nav-section">COMMUNICATION</div>
        <a href="?page=notifications" class="nav-link <?= ($currentPage ?? $page ?? '') === 'notifications' ? 'active' : '' ?>">
            <i class="fa-regular fa-bell"></i> <span>Notifications</span>
        </a>
    </nav>
</aside>