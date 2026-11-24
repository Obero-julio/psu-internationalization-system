<?php
$currentPage = $page ?? 'home';
<<<<<<< HEAD
?>
<aside class="sidebar" id="mainSidebar">
    <div class="brand">
        <div>
            <strong>PSU</strong>
            <b>Internationalization</b>
            <small>Applicant Portal</small>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Main</div>
        <a href="?page=home" class="nav-link <?= $currentPage === 'home' ? 'active' : '' ?>">
            <i class="fa-solid fa-house"></i> <span>Dashboard</span>
        </a>
        <a href="?page=available_programs" class="nav-link <?= in_array($currentPage, ['available_programs','program_detail']) ? 'active' : '' ?>">
            <i class="fa-solid fa-globe"></i> <span>Available Programs</span>
        </a>
        <a href="?page=my_applications" class="nav-link <?= in_array($currentPage, ['my_applications','application_status','my_application_detail']) ? 'active' : '' ?>">
            <i class="fa-regular fa-file-lines"></i> <span>My Applications</span>
        </a>
        <a href="?page=documents" class="nav-link <?= in_array($currentPage, ['documents','forms_requirements']) ? 'active' : '' ?>">
            <i class="fa-solid fa-folder-open"></i> <span>Documents</span>
        </a>

        <a href="?page=mobility_schedule" class="nav-link <?= $currentPage === 'mobility_schedule' ? 'active' : '' ?>">
            <i class="fa-regular fa-calendar-days"></i> <span>Mobility Schedule</span>
        </a>
        <a href="?page=notifications" class="nav-link <?= $currentPage === 'notifications' ? 'active' : '' ?>">
            <i class="fa-regular fa-bell"></i> <span>Notifications</span>
            <?php if (!empty($unreadCount)): ?><em><?= (int)$unreadCount ?></em><?php endif; ?>
        </a>

        <div class="nav-section">Account</div>
        <a href="?page=profile" class="nav-link <?= $currentPage === 'profile' ? 'active' : '' ?>">
            <i class="fa-regular fa-user"></i> <span>My Profile</span>
        </a>
    </nav>
</aside>
=======
$navigation = [
    'home' => ['Home', 'fa-house'],
    'available_programs' => ['Available Programs', 'fa-graduation-cap'],
    'my_applications' => ['My Applications', 'fa-file-lines'],
    'documents' => ['Documents', 'fa-folder-open'],
    'notifications' => ['Notifications', 'fa-bell'],
    'profile' => ['Profile', 'fa-user'],
    'settings' => ['Settings', 'fa-gear'],
];
?>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="main-sidebar" id="mainSidebar" aria-label="Applicant navigation">
    <div class="sidebar-header">
        <a class="brand" href="dashboard.php?page=home">
            <img src="../asset/img/psu_logo.png" alt="PSU logo" class="brand-logo">
            <span class="brand-text">
                <h4>PSU Internalization</h4>
                <small>Applicant Portal</small>
            </span>
        </a>
        <button class="sidebar-close" id="sidebarClose" type="button" aria-label="Close menu">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </div>

    <nav aria-label="Main navigation">
        <ul class="sidebar-menu">
            <?php foreach ($navigation as $key => [$label, $icon]): ?>
                <li>
                    <a href="dashboard.php?page=<?= h($key) ?>"
                       class="top-link <?= $currentPage === $key ? 'active' : '' ?>"
                       <?= $currentPage === $key ? 'aria-current="page"' : '' ?>>
                        <i class="fa-solid <?= h($icon) ?>" aria-hidden="true"></i>
                        <span><?= h($label) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <a href="../public/logout.php" class="logout-btn">
            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
            <span class="logout-copy">
                <span class="logout-title">Logout</span>
                <span class="logout-sub">End your session</span>
            </span>
        </a>
    </div>
</aside>
>>>>>>> d3584a4a544d2d7e792870691aa98d25b9cb5666
