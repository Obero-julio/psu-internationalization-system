<?php
$currentPage = $page ?? 'home';
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
