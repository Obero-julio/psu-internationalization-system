<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$schedule = [];

if ($user_id) {
    $pdo = db();

    // FIXED: Uncommented and properly joined to get program name, destination, and dates
    $stmt = $pdo->prepare("
        SELECT 
            p.program_name,
            c.country_name AS destination,
            p.start_date,
            p.end_date,
            a.status
        FROM applications a
        JOIN programs p ON a.program_id = p.id
        LEFT JOIN partners pr ON p.partner_id = pr.id
        LEFT JOIN countries c ON pr.country_id = c.id
        WHERE a.applicant_id = ? 
        ORDER BY p.start_date ASC
    ");

    $stmt->execute([$user_id]);
    $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<link rel="stylesheet" href="../asset/css/mobility_sched.css">

<div class="content-card">

    <div class="page-header">
        <div class="page-header-icon">
            <i class="fa-regular fa-calendar-check"></i>
        </div>
        <div>
            <h2>Mobility Schedule</h2>
            <p>Your program timeline and travel schedule overview.</p>
        </div>
    </div>

    <?php if (empty($schedule)): ?>

        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fa-regular fa-calendar-xmark"></i>
            </div>
            <h3>No Schedule Available</h3>
            <p>Your mobility schedule will appear here once you have submitted an application.</p>
            <a href="?page=available_programs" class="btn-primary">Browse Programs</a>
        </div>

    <?php else: ?>

        <div class="timeline">

            <?php foreach ($schedule as $s): ?>

                <?php
                    $status = strtolower($s['status'] ?? 'pending');
                    $statusClass = match ($status) {
                        'approved' => 'approved',
                        'rejected' => 'rejected',
                        default => 'pending'
                    };
                    
                    // Format dates nicely
                    $startFormatted = !empty($s['start_date']) ? date('M d, Y', strtotime($s['start_date'])) : 'TBD';
                    $endFormatted = !empty($s['end_date']) ? date('M d, Y', strtotime($s['end_date'])) : 'TBD';
                ?>

                <div class="timeline-item">

                    <div class="timeline-dot <?= e($statusClass) ?>"></div>

                    <div class="timeline-content">
                        <div class="timeline-header">
                            <h3><?= e($s['program_name'] ?? 'Unknown Program') ?></h3>
                            <span class="badge <?= e($statusClass) ?>">
                                <?= e(ucfirst($status)) ?>
                            </span>
                        </div>

                        <p class="location">
                            <i class="fa-solid fa-location-dot"></i>
                            <?= e($s['destination'] ?? 'Destination TBD') ?>
                        </p>

                        <p class="date">
                            <i class="fa-regular fa-clock"></i>
                            <?= e($startFormatted) ?> &nbsp;&rarr;&nbsp; <?= e($endFormatted) ?>
                        </p>
                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>