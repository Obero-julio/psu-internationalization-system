<?php
if (!isset($pdo, $userId) || !($pdo instanceof PDO)) {
    header('Location: dashboard.php?page=available_programs');
    exit;
}

$programId = (int)($_GET['id'] ?? 0);
<<<<<<< HEAD
if ($programId <= 0) {
    header('Location: ?page=available_programs');
    exit;
}

/* Schema-safe query: only select columns that exist */
=======
>>>>>>> d3584a4a544d2d7e792870691aa98d25b9cb5666
$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.program_name,
        p.description,
        p.start_date,
        p.end_date,
        p.status,
<<<<<<< HEAD
        c.country_name,
        partner.institution_name AS partner_institution
    FROM programs p
    INNER JOIN users applicant ON applicant.id = ?
    LEFT JOIN countries c ON c.id = p.country_id
    LEFT JOIN partners partner ON partner.id = p.partner_id
    WHERE p.id = ?
      AND p.status IN ('Active', 'Upcoming')
      AND p.end_date >= CURDATE()
      AND p.eligible_account_type = applicant.account_type
      AND (p.eligible_department = 'All Departments' OR p.eligible_department = applicant.department)
");
$stmt->execute([$userId, $programId]);
$program = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$program) {
    header('Location: ?page=available_programs');
    exit;
}

/* Check if user already applied */
$stmt = $pdo->prepare("SELECT id, status FROM applications WHERE applicant_id = ? AND program_id = ? LIMIT 1");
$stmt->execute([$userId, $programId]);
$existingApplication = $stmt->fetch(PDO::FETCH_ASSOC);

/* Fetch requirements for this program */
$stmt = $pdo->prepare("
    SELECT r.requirement_name, r.description
    FROM program_requirements pr
    INNER JOIN requirements r ON r.id = pr.requirement_id
    WHERE pr.program_id = ?
    ORDER BY r.requirement_name ASC
");
$stmt->execute([$programId]);
$requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="content-card" style="max-width: 900px; margin: 0 auto;">
    <div class="page-header">
        <div>
            <a href="?page=available_programs" style="display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem; color: var(--blue); font-weight: 600; margin-bottom: 12px;">
                <i class="fa-solid fa-arrow-left"></i> Back to Programs
            </a>
            <h2><?= h($program['program_name']) ?></h2>
            <?php if (!empty($program['country_name'])): ?>
                <p><i class="fa-solid fa-location-dot"></i> <?= h($program['country_name']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= h($msgType) ?>" style="margin-bottom: 24px; padding: 16px; border-radius: 10px; background: <?= $msgType === 'success' ? '#f0fdf4' : ($msgType === 'warning' ? '#fffbeb' : '#fef2f2') ?>; color: <?= $msgType === 'success' ? '#166534' : ($msgType === 'warning' ? '#92400e' : '#991b1b') ?>; border: 1px solid <?= $msgType === 'success' ? '#bbf7d0' : ($msgType === 'warning' ? '#fde68a' : '#fecaca') ?>;">
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <div style="margin-bottom: 24px;">
        <h3 style="font-size: 1rem; color: var(--navy-800); margin-bottom: 12px;">Description</h3>
        <p style="color: var(--gray-600); line-height: 1.6;"><?= nl2br(h($program['description'] ?? 'No description provided.')) ?></p>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
        <div style="background: var(--gray-50); padding: 16px; border-radius: 10px; border: 1px solid var(--gray-100);">
            <small style="color: var(--gray-500); display: block; margin-bottom: 4px;">Start Date</small>
            <strong><?= !empty($program['start_date']) ? h(date('M d, Y', strtotime($program['start_date']))) : 'TBA' ?></strong>
        </div>
        <div style="background: var(--gray-50); padding: 16px; border-radius: 10px; border: 1px solid var(--gray-100);">
            <small style="color: var(--gray-500); display: block; margin-bottom: 4px;">End Date</small>
            <strong><?= !empty($program['end_date']) ? h(date('M d, Y', strtotime($program['end_date']))) : 'TBA' ?></strong>
        </div>
        <div style="background: var(--gray-50); padding: 16px; border-radius: 10px; border: 1px solid var(--gray-100); grid-column: span 2;">
            <small style="color: var(--gray-500); display: block; margin-bottom: 4px;">Partner Institution</small>
            <strong><?= h($program['partner_institution'] ?? 'Not specified') ?></strong>
        </div>
    </div>

    <div style="margin-bottom: 24px;">
        <h3 style="font-size: 1rem; color: var(--navy-800); margin-bottom: 12px;">Requirements</h3>
        <?php if ($requirements): ?>
            <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px;">
                <?php foreach ($requirements as $req): ?>
                    <li style="display: flex; gap: 10px; font-size: 0.9rem; color: var(--gray-600); background: var(--gray-50); padding: 12px; border-radius: 10px;">
                        <i class="fa-solid fa-check-circle" style="color: var(--blue); margin-top: 2px;"></i>
                        <div>
                            <strong style="color: var(--navy-800);"><?= h($req['requirement_name']) ?></strong>
                            <?php if (!empty($req['description'])): ?>
                                <p style="color: var(--gray-500); font-size: 0.85rem; margin: 4px 0 0;"><?= h($req['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p style="color: var(--gray-500); font-style: italic;">No specific requirements listed.</p>
        <?php endif; ?>
    </div>

    <div style="padding-top: 24px; border-top: 1px solid var(--gray-200); display: flex; justify-content: flex-end; gap: 12px;">
        <?php if ($existingApplication): ?>
            <span class="badge-success" style="padding: 10px 16px; background: #f0fdf4; color: #166534; border-radius: 10px; font-weight: 700; font-size: 0.85rem;">Already applied</span>
            <a href="?page=my_applications" class="btn-secondary" style="padding: 10px 20px; background: var(--gray-100); border-radius: 10px; color: var(--navy-800); font-weight: 600;">View Status</a>
        <?php else: ?>
            <a href="?page=apply&program_id=<?= (int)$program['id'] ?>" class="btn-primary">
                Apply Now <i class="fa-solid fa-arrow-right" style="margin-left: 8px;"></i>
            </a>
=======
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
>>>>>>> d3584a4a544d2d7e792870691aa98d25b9cb5666
        <?php endif; ?>
    </div>
</div>