<?php
/**
 * Application Status Reports.
 * Included from home.php — $userId, $firstName and db() are already available.
 */

$userId = (int) $_SESSION['user_id'];

$statusCounts      = ['in_review' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$totalApplications = 0;
$applications      = [];
$dbError           = null;

$statusMap = [
    'in_review' => ['In Review', '#ee9916', '#fff1d6'],
    'pending'   => ['Pending',   '#7250c5', '#eee9ff'],
    'approved'  => ['Approved',  '#309548', '#def3e3'],
    'rejected'  => ['Rejected',  '#e84545', '#ffe2e3'],
];

try {
    // Status counts
    $stmt = db()->prepare(
        'SELECT status, COUNT(*) AS total
         FROM applications
         WHERE user_id = :uid
         GROUP BY status'
    );
    $stmt->execute([':uid' => $userId]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = strtolower(str_replace(' ', '_', trim((string) $row['status'])));
        $count  = (int) $row['total'];

        if (isset($statusCounts[$status])) {
            $statusCounts[$status] = $count;
        }

        $totalApplications += $count;
    }

    // Full list
    $stmt = db()->prepare(
        'SELECT a.id, a.application_id, a.status, a.created_at,
                p.title AS program_title, p.country, p.image
         FROM applications a
         LEFT JOIN programs p ON p.id = a.program_id
         WHERE a.user_id = :uid
         ORDER BY a.created_at DESC'
    );
    $stmt->execute([':uid' => $userId]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[reports] ' . $e->getMessage());
    $dbError = 'Report data is temporarily unavailable.';
}

$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>

<section class="report-page">

    <header class="report-head">
        <h1>Application Status Reports</h1>
        <p class="lead">A complete breakdown of your applications and their current status.</p>
    </header>

    <?php if ($dbError !== null): ?>
        <div class="report-alert" role="alert">
            <?= $e($dbError) ?>
        </div>
    <?php endif; ?>

    <div class="report-summary">
        <?php foreach ($statusMap as $key => [$label]): ?>
            <div class="panel report-summary-card">
                <h3><?= $e($label) ?></h3>
                <div class="big"><?= $statusCounts[$key] ?></div>
                <div class="sub">
                    <?= $totalApplications > 0
                        ? round($statusCounts[$key] / $totalApplications * 100) . '% of total'
                        : '—' ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="panel report-table-panel">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Program</th>
                    <th>Application ID</th>
                    <th>Destination</th>
                    <th>Submitted</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$applications): ?>
                    <tr>
                        <td colspan="5">
                            <div class="report-empty">
                                <i class="fa-regular fa-folder-open"></i>
                                No applications yet.
                                <a href="?page=available_programs">Browse programs</a> to get started.
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                        <?php
                        $raw = strtolower(str_replace(' ', '_', trim((string) $app['status'])));
                        [$tagLabel, $tagColor, $tagBg] = $statusMap[$raw] ?? ['Unknown', '#647494', '#e8edf5'];
                        ?>
                        <tr>
                            <td>
                                <div class="report-program">
                                    <img class="report-thumb"
                                         src="<?= $e($app['image'] ?? '') ?>"
                                         alt=""
                                         loading="lazy">
                                    <strong><?= $e($app['program_title'] ?? 'Program') ?></strong>
                                </div>
                            </td>
                            <td><?= $e($app['application_id'] ?? $app['id'] ?? '') ?></td>
                            <td><?= $e($app['country'] ?? '—') ?></td>
                            <td><?= $e($app['created_at'] ?? '—') ?></td>
                            <td>
                                <span class="status-tag" style="background:<?= $tagBg ?>;color:<?= $tagColor ?>">
                                    <?= $e($tagLabel) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>