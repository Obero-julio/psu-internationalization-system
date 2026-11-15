<?php
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$user_id = $_SESSION['user_id'] ?? null;

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$applications = [];

if ($user_id) {
    $pdo = db();
    // FIXED: Uncommented, changed user_id to applicant_id, and joined programs
    $stmt = $pdo->prepare("
        SELECT a.*, p.program_name 
        FROM applications a 
        LEFT JOIN programs p ON a.program_id = p.id 
        WHERE a.applicant_id = ? 
        ORDER BY a.id DESC
    ");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<link rel="stylesheet" href="../asset/css/application_status.css">

<div class="content-card">

    <div class="page-header">
        <div class="page-header-icon">
            <i class="fa-solid fa-list-check"></i>
        </div>
        <div>
            <h2>Application Status</h2>
            <p>Track the progress of your submitted applications.</p>
        </div>
    </div>

    <?php if (empty($applications)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fa-regular fa-folder-open"></i>
            </div>
            <h3>No Applications Yet</h3>
            <p>You haven't submitted any applications. Once you apply for a program, you can track its status here.</p>
            <a href="?page=available_programs" class="btn-primary">Browse Programs</a>
        </div>
    <?php else: ?>
        
        <div class="table-wrapper">
            <table class="status-table">
                <thead>
                    <tr>
                        <th>Program</th>
                        <th>Date Applied</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): 
                        $status = ucfirst(strtolower($app['status'] ?? 'Pending'));
                        $badgeClass = match($status) {
                            'Approved' => 'badge-approved',
                            'Rejected' => 'badge-rejected',
                            default    => 'badge-pending',
                        };
                    ?>
                        <tr>
                            <td>
                                <div class="program-name">
                                    <i class="fa-solid fa-graduation-cap"></i>
                                    <?= e($app['program_name'] ?? 'Unknown Program') ?>
                                </div>
                            </td>
                            <td class="date-cell">
                                <?= e(date('M d, Y', strtotime($app['created_at']))) ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $badgeClass ?>">
                                    <?= e($status) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</div>