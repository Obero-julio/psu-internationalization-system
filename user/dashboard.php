<?php
/**
 * Home dashboard content.
 *
 * Included from home.php, which already ran session_start(), auth checks,
 * and set $userId, $firstName before requiring this file. Database access
 * goes through the db() helper defined in config/db.php.
 */

// Safeguard for editor false positives
$userId = $userId ?? (int) ($_SESSION['user_id'] ?? 0);

$dbError           = null;
$totalApplications = 0;
$inReview          = 0;
$pending           = 0;
$approved          = 0;
$rejected          = 0;
$latestApplication = null;
$programs          = [];
$notifications     = [];
$requirements      = [];
$deadlines         = [];

$stepNames = ['Submitted', 'Under Review', 'Approved', 'Completed'];

$statusIcons = [
    'completed'   => 'fa-circle-check',
    'in_progress' => 'fa-clock',
    'pending'     => 'fa-circle-dot',
    'not_started' => 'fa-circle-xmark',
];

$statusColors = [
    'completed'   => 'var(--green)',
    'in_progress' => 'var(--orange)',
    'pending'     => 'var(--gray-400)', 
    'not_started' => 'var(--red)',
];

$statusLabels = [
    'completed'   => 'Completed',
    'in_progress' => 'In Progress',
    'pending'     => 'Pending',
    'not_started' => 'Not Started',
];

$noticeIcons = ['fa-file-lines', 'fa-circle-check', 'fa-bullhorn', 'fa-bell'];

try {
    // Status counts across every application this user owns
    $stmt = db()->prepare(
        'SELECT status, COUNT(*) AS total
         FROM applications
         WHERE user_id = :uid
         GROUP BY status'
    );
    $stmt->execute([':uid' => $userId]);

    foreach ($stmt->fetchAll() as $row) {
        $count = (int) $row['total'];
        $totalApplications += $count;

        match ($row['status']) {
            'submitted', 'in_review' => $inReview += $count,
            'pending'                => $pending  += $count,
            'approved', 'completed'  => $approved += $count,
            'rejected'               => $rejected += $count,
            default                  => null,
        };
    }

    // Most recently updated application, with its program info
    $stmt = db()->prepare(
        'SELECT a.id, a.application_code, a.status, a.progress_step, a.updated_at,
                p.title AS program_title, p.country, p.image AS program_image
         FROM applications a
         JOIN programs p ON p.id = a.program_id
         WHERE a.user_id = :uid
         ORDER BY a.updated_at DESC
         LIMIT 1'
    );
    $stmt->execute([':uid' => $userId]);
    $latestApplication = $stmt->fetch() ?: null;

    if ($latestApplication) {
        $stmt = db()->prepare(
            'SELECT label, status
             FROM application_requirements
             WHERE application_id = :aid
             ORDER BY id ASC'
        );
        $stmt->execute([':aid' => $latestApplication['id']]);
        $requirements = $stmt->fetchAll();
    }

    // Open programs the applicant hasn't applied to yet
    $stmt = db()->prepare(
        "SELECT title, country, term, type, image
         FROM programs
         WHERE status = 'open'
           AND id NOT IN (SELECT program_id FROM applications WHERE user_id = :uid)
         ORDER BY application_deadline ASC
         LIMIT 4"
    );
    $stmt->execute([':uid' => $userId]);
    $programs = $stmt->fetchAll();

    // Recent notifications
    $stmt = db()->prepare(
        'SELECT title, message, is_read, created_at
         FROM notifications
         WHERE user_id = :uid
         ORDER BY created_at DESC
         LIMIT 4'
    );
    $stmt->execute([':uid' => $userId]);
    $notifications = $stmt->fetchAll();

    // Upcoming deadlines (shared across all applicants)
    $today = date('Y-m-d');
    $stmt = db()->prepare(
        'SELECT title, description, due_date
         FROM deadlines
         WHERE due_date >= :today
         ORDER BY due_date ASC
         LIMIT 3'
    );
    $stmt->execute([':today' => $today]);
    $deadlines = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('[dashboard] ' . $e->getMessage());
    $dbError = 'We could not load some of your dashboard data. Please refresh in a moment.';
}

/* ---------------------------------------------------------------------
 * Derived display values
 * ------------------------------------------------------------------- */

$latestStatus = $latestApplication['status'] ?? null;

$progressMap   = ['submitted' => 0, 'in_review' => 1, 'approved' => 2, 'completed' => 3];
$progressIndex = $progressMap[$latestStatus] ?? -1;

$applicationImage = $latestApplication['program_image'] ?? '../asset/img/program_placeholder.jpg';

$doneCount = 0;
foreach ($requirements as $r) {
    if (strtolower($r['status']) === 'completed') {
        $doneCount++;
    }
}

$overallProgress = count($requirements) > 0
    ? (int) round(($doneCount / count($requirements)) * 100)
    : 0;

// Build a conic-gradient for the donut straight from the live counts.
if ($totalApplications > 0) {
    $segments = [
        ['value' => $inReview, 'color' => '#ee9916'],
        ['value' => $pending,  'color' => '#7250c5'],
        ['value' => $approved, 'color' => '#309548'],
        ['value' => $rejected, 'color' => '#e84545'],
    ];

    $stops  = [];
    $cursor = 0;

    foreach ($segments as $seg) {
        if ($seg['value'] <= 0) {
            continue;
        }
        $start   = $cursor;
        $cursor += ($seg['value'] / $totalApplications) * 360;
        $stops[] = "{$seg['color']} {$start}deg {$cursor}deg";
    }

    $donutGradient = $stops !== []
        ? 'conic-gradient(' . implode(', ', $stops) . ')'
        : 'conic-gradient(#e8edf5 0deg 360deg)';
} else {
    $donutGradient = 'conic-gradient(#e8edf5 0deg 360deg)';
}

// Greeting + date
date_default_timezone_set('Asia/Manila');
$hour    = (int) date('G');
$greeting = $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening');
$today   = date('l, F j, Y');

$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>

<section class="dashboard">

    <!-- Greeting -->
    <div class="dash-greeting">
        <div>
            <h1>Good <?= $greeting ?>, <?= $e($firstName) ?>!</h1>
            <p>Find an international opportunity for your future.</p>
        </div>
        <div class="greeting-date">
            <i class="fa-regular fa-calendar"></i>
            <span><?= $e($today) ?></span>
        </div>
    </div>

    <!-- STAT STRIP -->
    <div class="stat-strip">
        <a class="stat-pill" href="?page=my_applications">
            <span class="pill-icon blue"><i class="fa-solid fa-file-lines"></i></span>
            <div>
                <b><?= $totalApplications ?></b>
                <small>My Applications</small>
                <span class="stat-trend up"><i class="fa-solid fa-arrow-trend-up"></i> Active</span>
            </div>
        </a>
        <a class="stat-pill" href="?page=reports">
            <span class="pill-icon orange"><i class="fa-solid fa-clock"></i></span>
            <div>
                <b><?= $inReview ?></b>
                <small>In Review</small>
                <span class="stat-trend neutral">Pending Action</span>
            </div>
        </a>
        <a class="stat-pill" href="?page=reports">
            <span class="pill-icon green"><i class="fa-solid fa-circle-check"></i></span>
            <div>
                <b><?= $approved ?></b>
                <small>Approved</small>
                <span class="stat-trend up"><i class="fa-solid fa-arrow-trend-up"></i> Successful</span>
            </div>
        </a>
        <a class="stat-pill" href="?page=reports">
            <span class="pill-icon purple"><i class="fa-solid fa-hourglass-half"></i></span>
            <div>
                <b><?= $pending ?></b>
                <small>Pending</small>
                <span class="stat-trend neutral">Awaiting Docs</span>
            </div>
        </a>
        <a class="stat-pill" href="?page=reports">
            <span class="pill-icon red"><i class="fa-solid fa-circle-xmark"></i></span>
            <div>
                <b><?= $rejected ?></b>
                <small>Rejected</small>
                <span class="stat-trend down"><i class="fa-solid fa-arrow-trend-down"></i> Action Needed</span>
            </div>
        </a>
    </div>

    <!-- CRITICAL WRAPPER: RESTORED -->
    <div class="dash-main">
        
        <!-- LEFT COLUMN -->
        <div class="dash-left">

            <!-- Application tracker -->
            <section class="panel tracker-panel">
                <?php if ($latestApplication): ?>
                    <div class="panel-head">
                        <div>
                            <h2>Latest Application</h2>
                            <p>Track your progress step by step</p>
                        </div>
                        <span class="status-pill <?= in_array($latestStatus, ['approved', 'completed'], true) ? 'ok' : ($latestStatus === 'rejected' ? 'no' : '') ?>">
                            <?= $e(ucwords(str_replace('_', ' ', $latestStatus === 'submitted' ? 'in review' : $latestStatus))) ?>
                        </span>
                    </div>

                    <div class="tracker-hero">
                        <img class="tracker-photo" src="<?= $e($applicationImage) ?>" alt="<?= $e($latestApplication['program_title']) ?> destination">
                        <div class="tracker-info">
                            <strong><?= $e($latestApplication['program_title']) ?></strong>
                            <div class="tracker-tags">
                                <span><i class="fa-solid fa-location-dot"></i> <?= $e($latestApplication['country']) ?></span>
                                <span><i class="fa-solid fa-hashtag"></i> <?= $e($latestApplication['application_code'] ?? $latestApplication['id'] ?? 'N/A') ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="step-track">
                        <?php for ($i = 0; $i < 4; $i++): ?>
                            <?php $cls = $i < $progressIndex ? 'done' : ($i === $progressIndex ? 'current' : ''); ?>
                            <div class="step <?= $cls ?>">
                                <div class="step-dot"><i class="fa-solid <?= $i < $progressIndex ? 'fa-check' : ($i === $progressIndex ? 'fa-hourglass-half' : 'fa-circle') ?>"></i></div>
                                <div class="step-bar <?= $i < $progressIndex ? 'done' : '' ?>"></div>
                                <div class="step-name"><?= $e($stepNames[$i]) ?></div>
                            </div>
                        <?php endfor; ?>
                    </div>

                    <a class="btn-outline" href="?page=reports">
                        View Application Status <i class="fa-solid fa-arrow-right"></i>
                    </a>
                <?php else: ?>
                    <div class="panel-head">
                        <div><h2>Latest Application</h2><p>You haven't started one yet</p></div>
                    </div>
                    <div class="empty-state">
                        <i class="fa-regular fa-compass"></i>
                        <p>No applications on file. Browse open programs to get started.</p>
                        <a class="btn-outline" href="?page=available_programs">Explore Programs <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Available programs -->
            <section class="panel">
                <div class="panel-head">
                    <div><h2>Explore Programs</h2><p>Open for application</p></div>
                    <a href="?page=available_programs" class="panel-link">Browse all <i class="fa-solid fa-arrow-right"></i></a>
                </div>

                <?php if ($programs): ?>
                    <div class="program-list">
                        <?php foreach ($programs as $program): ?>
                            <a class="program-row" href="?page=available_programs">
                                <img class="program-row-photo" src="<?= $e($program['image'] ?? '../asset/img/program_placeholder.jpg') ?>" alt="">
                                <div class="program-row-body">
                                    <strong><?= $e($program['title']) ?></strong>
                                    <span><i class="fa-solid fa-location-dot"></i> <?= $e($program['country']) ?> &middot; <?= $e($program['term']) ?></span>
                                </div>
                                <span class="program-row-type"><?= $e($program['type']) ?></span>
                                <i class="fa-solid fa-chevron-right program-row-arrow"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state small"><p>No open programs right now — check back soon.</p></div>
                <?php endif; ?>
            </section>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="dash-right">
            <!-- Donut & Sparkline -->
            <section class="panel">
                <div class="panel-head">
                    <div><h2>Status Breakdown</h2><p>Application distribution</p></div>
                    <div class="sparkline-wrap">
                        <svg viewBox="0 0 100 30" class="sparkline">
                            <path d="M0,25 C10,20 20,28 30,15 C40,5 50,18 60,10 C70,2 80,12 90,5 L100,8" fill="none" stroke="var(--gold)" stroke-width="2.5" stroke-linecap="round"/>
                        </svg>
                    </div>
                </div>
                <div class="chart-wrap">
                    <div class="donut-wrap">
                        <div class="donut" style="background:<?= $donutGradient ?>">
                            <div class="donut-center"><?= $totalApplications ?><small>Total</small></div>
                        </div>
                    </div>
                    <div class="legend">
                        <div class="legend-row"><i style="background:var(--orange)"></i><span>In Review</span><b><?= $inReview ?></b></div>
                        <div class="legend-row"><i style="background:var(--purple)"></i><span>Pending</span><b><?= $pending ?></b></div>
                        <div class="legend-row"><i style="background:var(--green)"></i><span>Approved</span><b><?= $approved ?></b></div>
                        <div class="legend-row"><i style="background:var(--red)"></i><span>Rejected</span><b><?= $rejected ?></b></div>
                    </div>
                </div>
            </section>

            <!-- Requirements -->
            <section class="panel">
                <div class="panel-head">
                    <div><h2>Requirements</h2><p><?= $doneCount ?> of <?= count($requirements) ?> complete</p></div>
                </div>
                <?php if ($requirements): ?>
                    <div class="progress-meta"><span>Overall</span><b><?= $overallProgress ?>%</b></div>
                    <div class="progress-bar"><span style="width:<?= $overallProgress ?>%"></span></div>
                    <?php foreach ($requirements as $r): ?>
                        <?php $st = strtolower($r['status']); ?>
                        <div class="req-row">
                            <i class="fa-solid <?= $statusIcons[$st] ?? 'fa-circle-dot' ?>" style="color:<?= $statusColors[$st] ?? 'var(--gray-400)' ?>"></i>
                            <span><?= $e($r['label']) ?></span>
                            <em><?= $statusLabels[$st] ?? 'Unknown' ?></em>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state small"><p>Requirements will appear once you start an application.</p></div>
                <?php endif; ?>
                <a class="panel-link" href="?page=upload_documents">Manage requirements <i class="fa-solid fa-arrow-right"></i></a>
            </section>

            <!-- Notifications -->
            <section class="panel notice-panel">
                <div class="panel-head">
                    <div><h2>Recent Updates</h2></div>
                    <a class="notice-link" href="?page=notifications">All</a>
                </div>
                <?php if ($notifications): ?>
                    <?php foreach ($notifications as $i => $n): ?>
                        <div class="notice-item <?= !$n['is_read'] ? 'unread' : '' ?>">
                            <span class="notice-icon"><i class="fa-solid <?= $noticeIcons[$i % count($noticeIcons)] ?>"></i></span>
                            <div>
                                <b><?= $e($n['title']) ?></b>
                                <small><?= $e($n['message']) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state small on-dark"><p>You're all caught up — no new updates.</p></div>
                <?php endif; ?>
            </section>

            <!-- Deadlines -->
            <section class="panel">
                <div class="panel-head">
                    <div><h2>Deadlines</h2></div>
                </div>
                <?php if ($deadlines): ?>
                    <?php foreach ($deadlines as $d): ?>
                        <?php
                        $due      = new DateTime($d['due_date']);
                        $daysLeft = (int) (new DateTime())->diff($due)->format('%r%a');
                        ?>
                        <div class="deadline-row">
                            <div class="deadline-chip"><?= $e($due->format('M j')) ?></div>
                            <div>
                                <b><?= $e($d['title']) ?></b>
                                <small><?= $e($d['description'] ?? '') ?></small>
                            </div>
                            <span class="deadline-count"><?= $daysLeft >= 0 ? "{$daysLeft} days left" : 'Past due' ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state small"><p>No deadlines scheduled.</p></div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>