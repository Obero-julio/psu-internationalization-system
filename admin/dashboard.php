<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth_middleware.php';
requireAdmin();

$page = (string)($_GET['page'] ?? 'home');

/* =========================
   POST HANDLERS (AJAX)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ajaxPages = [
        'countries_addresses', 'program_category', 'agreement_types',
        'partners', 'programs', 'requirements', 'forms', 'users',
        'program_requirements' // <-- ADDED THIS
    ];

    if (in_array($page, $ajaxPages, true)) {
        require $page . '.php';
        exit;
    }
}

/* =========================
   VALID PAGES
========================= */
$validPages = [
    'home',
    
    // Master Data
    'countries_addresses', 'partners', 'agreement_types', 'program_category',
    
    // Program Management
    'programs', 'requirements', 'forms', 'program_requirements',
    
    // Applications
    'applications', 'documents',
    
    // Student Mobility
    'student_management', 'approved_students', 'travel_information', 'activity_reports',
    
    // Communication
    'notifications', 'announcements',
    
    // Administration
    'users', 'audit_trail', 'settings'
];

if (!in_array($page, $validPages, true)) {
    $page = 'home';
}

$firstName = $_SESSION['first_name'] ?? 'Administrator';
$lastName  = $_SESSION['last_name']  ?? '';
$role      = strtolower(trim((string)($_SESSION['user_role'] ?? '')));
$role      = str_replace('_', ' ', $role);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PSU Internalization Management System</title>
    <link rel="stylesheet" href="../asset/css/dashboard.css">
    <link rel="stylesheet" href="../asset/css/report.css">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="topbar">
    <div class="topbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Open menu">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <span class="topbar-title">PSU Internalization</span>
    </div>
    <div class="user">
        <span class="material-symbols-outlined">account_circle</span>
        <span><?= htmlspecialchars(trim("$firstName $lastName"), ENT_QUOTES, 'UTF-8') ?></span>
    </div>
</div>

<div class="container">
<?php
switch ($page) {
    case 'home':                require 'reports.php'; break;
    case 'countries_addresses': require 'countries_addresses.php'; break;
    case 'partners':            require 'partners.php'; break;
    case 'agreement_types':     require 'agreement_types.php'; break;
    case 'program_category':    require 'program_category.php'; break;
    case 'programs':            require 'programs.php'; break;
    case 'requirements':        require 'requirements.php'; break;
    case 'forms':               require 'forms.php'; break;
    case 'program_requirements':require 'program_requirements.php'; break;
    case 'applications':        require 'applications.php'; break;
    case 'documents':           require 'documents.php'; break;
    case 'student_management':  require 'student_management.php'; break;
    case 'travel_information':  require 'travel_information.php'; break;
    case 'activity_reports':    require 'activity_reports.php'; break;
    case 'approved_students':   require 'approved_students.php'; break;
    case 'notifications':       require 'notifications.php'; break;
    case 'announcements':       require 'announcements.php'; break;
    case 'users':               require 'users.php'; break;
    case 'audit_trail':         require 'audit_trail.php'; break;
    case 'settings':            require 'general_settings.php'; break;
    default:                    require 'reports.php'; break;
}
?>
</div>

<script src="../asset/js/dashboard.js"></script>
</body>
</html>