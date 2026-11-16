<?php
if (!isset($pdo, $userId, $user) || !($pdo instanceof PDO)) {
    header('Location: dashboard.php?page=my_applications');
    exit;
}

$appId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.department,
        a.mobility_type,
        a.institution,
        a.status,
        a.documents_status,
        a.reviewed_at,
        a.created_at,
        p.program_name,
        p.start_date,
        p.end_date,
        COALESCE(NULLIF(a.institution, ''), p.partner_institution, partner.institution_name) AS partner_institution,
        COALESCE(NULLIF(a.country, ''), country.country_name) AS country_name
    FROM applications a
    LEFT JOIN programs p ON p.id = a.program_id
    LEFT JOIN partners partner ON partner.id = p.partner_id
    LEFT JOIN countries country ON country.id = p.country_id
    WHERE a.id = ? AND a.applicant_id = ?
");
$stmt->execute([$appId, $userId]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
    echo '<div class="content-card"><p>Application not found.</p></div>';
    return;
}

$status = strtolower((string)$app['status']);
$badgeClass = match ($status) {
    'approved', 'completed' => 'badge-approved',
    'rejected' => 'badge-rejected',
    default => 'badge-pending',
};
?>

<section class="content-card" aria-labelledby="application-title">
    <div class="page-header">
        <div>
            <h2 id="application-title"><?= h($app['program_name'] ?? 'Application details') ?></h2>
            <p>
                Status:
                <span class="status-badge <?= $badgeClass ?>">
                    <?= h(ucwords(str_replace('_', ' ', $status))) ?>
                </span>
            </p>
        </div>
        <a class="btn-secondary" href="?page=my_applications">Back to applications</a>
    </div>

    <div class="detail-grid">
        <div><label>Applicant</label><strong><?= h(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></strong></div>
        <div><label>Email</label><strong><?= h($user['email'] ?? '—') ?></strong></div>
        <div><label>Program</label><strong><?= h($app['program_name'] ?? '—') ?></strong></div>
        <div><label>Department</label><strong><?= h($app['department'] ?? '—') ?></strong></div>
        <div><label>Mobility Type</label><strong><?= h($app['mobility_type'] ?? '—') ?></strong></div>
        <div><label>Partner Institution</label><strong><?= h($app['partner_institution'] ?? '—') ?></strong></div>
        <div><label>Country</label><strong><?= h($app['country_name'] ?? '—') ?></strong></div>
        <div><label>Documents</label><strong><?= h(ucfirst((string)($app['documents_status'] ?? 'pending'))) ?></strong></div>
        <div><label>Date Applied</label><strong><?= !empty($app['created_at']) ? h(date('M d, Y', strtotime($app['created_at']))) : '—' ?></strong></div>
        <div><label>Reviewed</label><strong><?= !empty($app['reviewed_at']) ? h(date('M d, Y', strtotime($app['reviewed_at']))) : '—' ?></strong></div>
        <div><label>Program Start</label><strong><?= !empty($app['start_date']) ? h(date('M d, Y', strtotime($app['start_date']))) : '—' ?></strong></div>
        <div><label>Program End</label><strong><?= !empty($app['end_date']) ? h(date('M d, Y', strtotime($app['end_date']))) : '—' ?></strong></div>
    </div>
</section>
