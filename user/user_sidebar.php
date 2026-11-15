<?php
// Ensure session is available if sidebar is included standalone
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = $_GET['page'] ?? 'home';

// Fallback initials if not already defined by parent page
if (!isset($initials)) {
    $initials = '?';
    if (isset($user['first_name'], $user['last_name'])) {
        $initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
    }
}
?>

<aside class="sidebar" id="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-logo">
            <i class="fa-solid fa-earth-asia"></i>
        </div>
        <div class="brand-text">
            <div class="brand-title">PSU</div>
            <div class="brand-sub">Internalization</div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <div class="nav-section-label">Main Menu</div>

        <a href="?page=home" class="nav-item <?= $currentPage === 'home' ? 'active' : '' ?>">
            <i class="fa-solid fa-house"></i>
            <span>Dashboard</span>
        </a>

        <a href="?page=available_programs" class="nav-item <?= $currentPage === 'available_programs' ? 'active' : '' ?>">
            <i class="fa-solid fa-graduation-cap"></i>
            <span>Available Programs</span>
        </a>

        <a href="?page=my_applications" class="nav-item <?= $currentPage === 'my_applications' ? 'active' : '' ?>">
            <i class="fa-regular fa-file-lines"></i>
            <span>My Applications</span>
        </a>

        <a href="?page=partner_directory" class="nav-item <?= $currentPage === 'partner_directory' ? 'active' : '' ?>">
            <i class="fa-solid fa-building-columns"></i>
            <span>Partner Directory</span>
        </a>

        <a href="?page=documents" class="nav-item <?= $currentPage === 'documents' ? 'active' : '' ?>">
            <i class="fa-regular fa-folder-open"></i>
            <span>Documents</span>
        </a>

        <div class="nav-section-label">Account</div>

        <a href="?page=profile" class="nav-item <?= $currentPage === 'profile' ? 'active' : '' ?>">
            <i class="fa-regular fa-user"></i>
            <span>My Profile</span>
        </a>

        <a href="?page=settings" class="nav-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
            <i class="fa-solid fa-gear"></i>
            <span>Settings</span>
        </a>

        <a href="../public/user_logout.php" class="nav-item nav-logout">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>
    </nav>

    <!-- Footer / Help Card -->
    <div class="sidebar-footer">
        <div class="help-card">
            <i class="fa-solid fa-circle-question"></i>
            <div>
                <div class="help-title">Need Help?</div>
                <div class="help-sub">Contact the OIP office</div>
            </div>
        </div>
    </div>

</aside>