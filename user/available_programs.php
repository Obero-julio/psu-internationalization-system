<?php
if (!isset($pdo, $userId) || !($pdo instanceof PDO)) {
    header('Location: dashboard.php?page=available_programs');
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.program_name,
        p.description,
        p.start_date,
        p.end_date,
        country.country_name,
        EXISTS(
            SELECT 1
            FROM applications a
            WHERE a.applicant_id = ? AND a.program_id = p.id
        ) AS already_applied
    FROM programs p
    LEFT JOIN countries country ON country.id = p.country_id
    WHERE p.status IN ('Active', 'Upcoming')
      AND p.end_date >= CURDATE()
    ORDER BY p.start_date, p.program_name
");
$stmt->execute([$userId]);
$programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content-card">
    <div class="page-header">
        <div>
            <h2>Available Programs</h2>
            <p>Browse programs you can apply to. Click a program to see requirements and apply.</p>
        </div>
    </div>

    <?php if (empty($programs)): ?>
        <p class="muted">No programs are open for application right now.</p>
    <?php else: ?>
    <div class="program-grid">
        <?php foreach ($programs as $p): ?>
        <div class="program-card">
            <div class="program-card-head">
                <h3><?= h($p['program_name']) ?></h3>
                <?php if (!empty($p['country_name'])): ?>
                    <span class="country-chip"><i class="fa-solid fa-location-dot"></i> <?= h($p['country_name']) ?></span>
                <?php endif; ?>
            </div>
            <p class="program-desc"><?= h(mb_strimwidth((string)$p['description'], 0, 160, '…')) ?></p>
            <div class="program-meta">
                <?php if (!empty($p['start_date']) && !empty($p['end_date'])): ?>
                    <span>
                        <i class="fa-regular fa-calendar"></i>
                        <?= h(date('M d, Y', strtotime($p['start_date']))) ?>
                        – <?= h(date('M d, Y', strtotime($p['end_date']))) ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="program-card-actions">
                <?php if ($p['already_applied']): ?>
                    <span class="badge-success">Already applied</span>
                    <a class="btn-secondary" href="?page=my_applications">View status</a>
                <?php else: ?>
                    <a class="btn-primary" href="?page=program_detail&id=<?= (int)$p['id'] ?>">View & Apply →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>