<?php
if (!isset($pdo, $userId) || !($pdo instanceof PDO)) {
    header('Location: dashboard.php?page=available_programs');
    exit;
}

$programId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.program_name,
        p.description,
        p.start_date,
        p.end_date,
        p.status,
        COALESCE(NULLIF(p.partner_institution, ''), partner.institution_name) AS partner_institution,
        country.country_name
    FROM programs p
    LEFT JOIN partners partner ON partner.id = p.partner_id
    LEFT JOIN countries country ON country.id = p.country_id
    WHERE p.id = ?
      AND p.status IN ('Active', 'Upcoming')
      AND p.end_date >= CURDATE()
");
$stmt->execute([$programId]);
$program = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$program) {
    echo '<div class="content-card"><p>Program not found.</p></div>';
    return;
}

/* Already applied? */
$stmt = $pdo->prepare("
    SELECT id, status
    FROM applications
    WHERE applicant_id = ? AND program_id = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute([$userId, $programId]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

/* Requirements preview */
$stmt = $pdo->prepare("
    SELECT r.requirement_name, r.description, pr.is_required
    FROM program_requirements pr
    INNER JOIN requirements r ON r.id = pr.requirement_id
    WHERE pr.program_id = ?
    ORDER BY r.requirement_name
");
$stmt->execute([$programId]);
$requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content-card">
    <div class="page-header">
        <div>
            <h2><?= h($program['program_name']) ?></h2>
            <p><?= h($program['description'] ?? '') ?></p>
        </div>
    </div>

    <div class="detail-grid">
        <?php if (!empty($program['partner_institution'])): ?>
            <div><label>Partner Institution</label><strong><?= h($program['partner_institution']) ?></strong></div>
        <?php endif; ?>
        <?php if (!empty($program['country_name'])): ?>
            <div><label>Country</label><strong><?= h($program['country_name']) ?></strong></div>
        <?php endif; ?>
        <?php if (!empty($program['start_date'])): ?>
            <div><label>Start Date</label><strong><?= h(date('M d, Y', strtotime($program['start_date']))) ?></strong></div>
        <?php endif; ?>
        <?php if (!empty($program['end_date'])): ?>
            <div><label>End Date</label><strong><?= h(date('M d, Y', strtotime($program['end_date']))) ?></strong></div>
        <?php endif; ?>
    </div>

    <h3 class="section-title">📋 Requirements you'll need to upload</h3>
    <ul class="requirement-preview">
        <?php foreach ($requirements as $r): ?>
            <li>
                <i class="fa-solid <?= $r['is_required'] ? 'fa-circle-check' : 'fa-circle' ?>"></i>
                <div>
                    <strong><?= h($r['requirement_name']) ?></strong>
                    <?php if (!empty($r['description'])): ?>
                        <small><?= h($r['description']) ?></small>
                    <?php endif; ?>
                    <?php if (!$r['is_required']): ?><em>(optional)</em><?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="form-actions">
        <?php if ($existing): ?>
            <span class="badge-success">You've already applied — status: <?= h($existing['status']) ?></span>
            <a class="btn-secondary" href="?page=my_applications">View my applications</a>
        <?php else: ?>
            <a class="btn-primary" href="?page=apply&program_id=<?= (int)$programId ?>">Start Application →</a>
        <?php endif; ?>
    </div>
</div>