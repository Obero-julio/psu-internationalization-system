<?php
if (!isset($pdo, $userId, $user) || !($pdo instanceof PDO)) {
    header('Location: dashboard.php?page=home');
    exit;
}

/* Approved application (if any) */
$stmt = $pdo->prepare("
    SELECT
        a.id,
        p.program_name,
        c.country_name AS host_country,
        p.start_date AS program_start_date,
        p.end_date AS program_end_date
    FROM applications a
    LEFT JOIN programs p ON p.id = a.program_id
    LEFT JOIN countries c ON c.id = p.country_id
    WHERE a.applicant_id = ? AND a.status = 'approved'
    ORDER BY COALESCE(a.reviewed_at, a.updated_at, a.created_at) DESC
    LIMIT 1
");
$stmt->execute([$userId]);
$approved = $stmt->fetch(PDO::FETCH_ASSOC);

/* Recent notifications */
$stmt = $pdo->prepare("
    SELECT id, type, title, message, is_read, created_at
    FROM notifications WHERE user_id = ?
    ORDER BY created_at DESC LIMIT 5
");
$stmt->execute([$userId]);
$recentNotifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Application counts */
$stmt = $pdo->prepare("
    SELECT status, COUNT(*) AS application_count
    FROM applications
    WHERE applicant_id = ?
    GROUP BY status
");
$stmt->execute([$userId]);
$statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadCount = (int)$stmt->fetchColumn();
?>

<?php if ($approved): ?>
<div class="approval-banner">
    <div class="banner-icon">🎉</div>
    <div class="banner-body">
        <h3>Your application was approved!</h3>
        <p><strong><?= h($approved['program_name'] ?? 'Approved program') ?></strong>
           <?php if (!empty($approved['host_country'])): ?> — <?= h($approved['host_country']) ?><?php endif; ?>
        </p>
        <p>
            <?php if (!empty($approved['program_start_date']) && !empty($approved['program_end_date'])): ?>
                Program: <?= h(date('M d, Y', strtotime($approved['program_start_date']))) ?>
                – <?= h(date('M d, Y', strtotime($approved['program_end_date']))) ?>
            <?php endif; ?>
        </p>
        <a class="btn-primary" href="?page=my_application_detail&id=<?= (int)$approved['id'] ?>">
            View Full Details →
        </a>
    </div>
</div>
<?php endif; ?>

<div class="content-card">
    <div class="page-header">
        <div>
            <h2>Welcome back, <?= h($user['first_name'] ?? 'Applicant') ?>!</h2>
            <p>Here's an overview of your application activity.</p>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <i class="fa-regular fa-file-lines"></i>
            <div>
                <span class="stat-value"><?= array_sum($statusCounts) ?></span>
                <span class="stat-label">Total Applications</span>
            </div>
        </div>
        <div class="stat-card">
            <i class="fa-solid fa-hourglass-half"></i>
            <div>
                <span class="stat-value"><?= (int)($statusCounts['pending'] ?? 0) + (int)($statusCounts['under_review'] ?? 0) ?></span>
                <span class="stat-label">In Review</span>
            </div>
        </div>
        <div class="stat-card">
            <i class="fa-solid fa-circle-check"></i>
            <div>
                <span class="stat-value"><?= (int)($statusCounts['approved'] ?? 0) ?></span>
                <span class="stat-label">Approved</span>
            </div>
        </div>
        <div class="stat-card">
            <i class="fa-regular fa-bell"></i>
            <div>
                <span class="stat-value"><?= $unreadCount ?? 0 ?></span>
                <span class="stat-label">Unread Alerts</span>
            </div>
        </div>
    </div>

    <h3 class="section-title">Recent Notifications</h3>
    <?php if ($recentNotifs): ?>
        <ul class="mini-notif-list">
            <?php foreach ($recentNotifs as $n): ?>
                <li class="<?= $n['is_read'] ? '' : 'unread' ?>">
                    <strong><?= h($n['title']) ?></strong>
                    <small><?= date('M d, Y g:i A', strtotime($n['created_at'])) ?></small>
                </li>
            <?php endforeach; ?>
        </ul>
        <a href="?page=notifications" class="link-more">View all →</a>
    <?php else: ?>
        <p class="muted">No notifications yet.</p>
    <?php endif; ?>
</div>