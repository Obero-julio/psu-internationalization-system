<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Authentication Check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit();
}

$pdo = db();
$userId = $_SESSION['user_id'];

// 2. Fetch User Data
$stmt = $pdo->prepare("SELECT first_name, last_name, email, user_role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: ../public/login.php');
    exit();
}

// 3. Generate Initials for Avatar
$initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));

// 4. Determine current page
$currentPage = $_GET['page'] ?? 'home';

// 5. Fetch Dashboard Stats and Recent Applications
$stats = ['approved' => 0, 'pending' => 0, 'rejected' => 0, 'total' => 0];
$recentApps = [];

if ($currentPage === 'home') {
    // Fetch status counts
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM applications 
        WHERE applicant_id = ? 
        GROUP BY status
    ");
    $stmt->execute([$userId]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $row) {
        $s = strtolower($row['status']);
        $stats['total'] += (int)$row['count'];
        if ($s === 'approved') $stats['approved'] = (int)$row['count'];
        elseif ($s === 'rejected') $stats['rejected'] = (int)$row['count'];
        else $stats['pending'] += (int)$row['count'];
    }

    // Fetch 5 most recent applications
    $stmt = $pdo->prepare("
        SELECT a.id, a.status, a.created_at, p.program_name 
        FROM applications a
        LEFT JOIN programs p ON a.program_id = p.id
        WHERE a.applicant_id = ?
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $recentApps = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper to calculate chart heights (max 100%)
$maxVal = max($stats['approved'], $stats['pending'], $stats['rejected'], 1);
$heightApproved = ($stats['approved'] / $maxVal) * 100;
$heightPending  = ($stats['pending'] / $maxVal) * 100;
$heightRejected = ($stats['rejected'] / $maxVal) * 100;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Portal - PSU Internalization</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../asset/css/applicant_dashboard.css">
</head>
<body>

<div class="layout">

    <!-- ===================================================
         SIDEBAR (Included)
         =================================================== -->
    <?php include 'sidebar.php'; ?>

    <!-- ===================================================
         MAIN CONTENT
         =================================================== -->
    <main class="main">
        
        <!-- TOPBAR -->
        <header class="topbar">
            <div>
                <div class="topbar-title">Applicant Dashboard</div>
                <div class="topbar-sub">Manage your mobility journey</div>
            </div>

            <!-- Account Dropdown -->
            <div class="topbar-right account-menu" id="accountMenu">
                <div class="account-trigger" id="accountTrigger">
                    <div class="account-avatar"><?= htmlspecialchars($initials) ?></div>
                    <div>
                        <div class="account-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                        <div class="account-role"><?= htmlspecialchars(ucfirst($user['user_role'])) ?></div>
                    </div>
                    <i class="fa-solid fa-chevron-down account-chevron"></i>
                </div>

                <div class="dropdown">
                    <div class="dropdown-header">
                        <div class="dh-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                        <div class="dh-email"><?= htmlspecialchars($user['email']) ?></div>
                    </div>
                    <a href="?page=profile"><i class="fa-regular fa-user"></i> My Profile</a>
                    <a href="?page=settings"><i class="fa-solid fa-gear"></i> Settings</a>
                    <div class="dropdown-divider"></div>
                    <a href="../public/user_logout.php" class="danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
                </div>
            </div>
        </header>

        <!-- PAGE CONTENT (Routing) -->
        <?php if ($currentPage === 'home'): ?>
            
            <div class="dashboard-container">
                
                <!-- WELCOME BANNER -->
                <div class="welcome-banner">
                    <div class="welcome-text">
                        <h1>Hello, <?= htmlspecialchars($user['first_name']) ?>! 👋</h1>
                        <p>Here is a summary of your application statuses.</p>
                    </div>
                    <a href="?page=available_programs" class="btn-primary">
                        <i class="fa-solid fa-plus"></i> New Application
                    </a>
                </div>

                <!-- KPI STATS GRID -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Approved</span>
                            <div class="stat-icon icon-approved"><i class="fa-solid fa-circle-check"></i></div>
                        </div>
                        <div class="stat-info"><h3><?= $stats['approved'] ?></h3></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Pending Review</span>
                            <div class="stat-icon icon-pending"><i class="fa-solid fa-clock"></i></div>
                        </div>
                        <div class="stat-info"><h3><?= $stats['pending'] ?></h3></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Rejected</span>
                            <div class="stat-icon icon-rejected"><i class="fa-solid fa-circle-xmark"></i></div>
                        </div>
                        <div class="stat-info"><h3><?= $stats['rejected'] ?></h3></div>
                    </div>
                </div>

                <!-- DYNAMIC GRAPH ROW -->
                <div class="analytics-row">
                    <div class="report-card">
                        <div class="report-header">
                            <h3>Your Application Breakdown</h3>
                            <span style="font-size: 0.8rem; color: var(--gray-400); font-weight: 600;">Total: <?= $stats['total'] ?> Application<?= $stats['total'] != 1 ? 's' : '' ?></span>
                        </div>
                        
                        <?php if ($stats['total'] > 0): ?>
                            <div class="chart-container">
                                <div class="chart-column">
                                    <div class="chart-bar-wrapper">
                                        <div class="chart-bar bar-approved" style="height: <?= $heightApproved ?>%;">
                                            <span class="bar-value"><?= $stats['approved'] ?></span>
                                        </div>
                                    </div>
                                    <span class="chart-label">Approved</span>
                                </div>

                                <div class="chart-column">
                                    <div class="chart-bar-wrapper">
                                        <div class="chart-bar bar-pending" style="height: <?= $heightPending ?>%;">
                                            <span class="bar-value"><?= $stats['pending'] ?></span>
                                        </div>
                                    </div>
                                    <span class="chart-label">Pending</span>
                                </div>

                                <div class="chart-column">
                                    <div class="chart-bar-wrapper">
                                        <div class="chart-bar bar-rejected" style="height: <?= $heightRejected ?>%;">
                                            <span class="bar-value"><?= $stats['rejected'] ?></span>
                                        </div>
                                    </div>
                                    <span class="chart-label">Rejected</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="chart-empty">
                                <div style="text-align: center;">
                                    <i class="fa-solid fa-chart-simple" style="font-size: 2.5rem; color: var(--gray-300); margin-bottom: 12px;"></i>
                                    <p>No data available. Apply to a program to see your stats!</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="report-card" style="background: var(--gray-100); justify-content: center; align-items: center; text-align: center; border: 1px dashed var(--gray-300);">
                        <i class="fa-solid fa-lightbulb" style="font-size: 2rem; color: var(--gold); margin-bottom: 16px;"></i>
                        <h3 style="font-family: 'Plus Jakarta Sans'; margin-bottom: 8px; color: var(--navy);">Next Steps</h3>
                        <p style="font-size: 0.9rem; color: var(--gray-500); margin-bottom: 20px; max-width: 250px;">Ensure your documents are uploaded to speed up the review process.</p>
                        <a href="?page=documents" class="btn-primary" style="background: var(--blue); color: var(--white); box-shadow: none;">Upload Documents</a>
                    </div>
                </div>

                <!-- RECENT APPLICATIONS TABLE -->
                <div class="section-title">
                    <h2>Recent Applications</h2>
                    <a href="?page=my_applications" class="view-all">View All</a>
                </div>

                <div class="table-card">
                    <table>
                        <thead>
                            <tr>
                                <th>Program</th>
                                <th>Date Applied</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentApps)): ?>
                                <?php foreach ($recentApps as $app): ?>
                                    <?php 
                                        $status = strtolower($app['status']);
                                        $badgeClass = 'badge-pending';
                                        if ($status === 'approved') $badgeClass = 'badge-approved';
                                        elseif ($status === 'rejected') $badgeClass = 'badge-rejected';
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($app['program_name'] ?? 'Unknown') ?></strong></td>
                                        <td><?= date('M d, Y', strtotime($app['created_at'])) ?></td>
                                        <td>
                                            <span class="badge <?= $badgeClass ?>">
                                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $app['status']))) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; color: var(--gray-400); padding: 40px;">
                                        <i class="fa-regular fa-folder-open" style="font-size: 2rem; margin-bottom: 12px; display: block;"></i>
                                        No applications found. Start by exploring available programs!
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

        <?php elseif ($currentPage === 'my_applications'): ?>
            <div class="box">
                <h2 style="font-family: 'Plus Jakarta Sans'; color: var(--navy); margin-bottom: 20px;">My Applications</h2>
                <p style="color: var(--gray-500);">Your application history will appear here.</p>
            </div>

        <?php elseif ($currentPage === 'partner_directory'): ?>
            <div class="box">
                <h2 style="font-family: 'Plus Jakarta Sans'; color: var(--navy); margin-bottom: 20px;">Partner Directory</h2>
                <p style="color: var(--gray-500);">List of partner institutions will appear here.</p>
            </div>

        <?php else: ?>
            <div class="box">
                <h2 style="font-family: 'Plus Jakarta Sans'; color: var(--navy); margin-bottom: 20px;"><?= ucwords(str_replace('_', ' ', $currentPage)) ?></h2>
                <p style="color: var(--gray-500);">This page is under construction.</p>
            </div>
        <?php endif; ?>

    </main>
</div>

<!-- ===================================================
     JAVASCRIPT (Account Dropdown Toggle)
     =================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const accountMenu = document.getElementById('accountMenu');
    const accountTrigger = document.getElementById('accountTrigger');

    accountTrigger.addEventListener('click', function(e) {
        e.stopPropagation();
        accountMenu.classList.toggle('active');
    });

    document.addEventListener('click', function(e) {
        if (!accountMenu.contains(e.target)) {
            accountMenu.classList.remove('active');
        }
    });
});
</script>

</body>
</html>