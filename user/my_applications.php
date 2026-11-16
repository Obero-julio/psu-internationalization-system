<?php
if (!isset($pdo, $userId) || !($pdo instanceof PDO)) {
    header('Location: dashboard.php?page=my_applications');
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.program_id,
        a.status,
        a.documents_status,
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
    WHERE a.applicant_id = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([$userId]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="content-card" aria-labelledby="applications-title">
    <div class="page-header">
        <div>
            <h2 id="applications-title">My Applications</h2>
            <p>Track the status of your mobility program applications.</p>
        </div>
    </div>

    <?php if (!$applications): ?>
        <div class="empty-state">
            <h3>No applications yet</h3>
            <p>Browse available programs to get started.</p>
            <a href="?page=available_programs" class="btn-primary">Browse Programs</a>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="applications-table">
                <thead>
                    <tr>
                        <th scope="col">Program</th>
                        <th scope="col">Partner Institution</th>
                        <th scope="col">Country</th>
                        <th scope="col">Duration</th>
                        <th scope="col">Date Applied</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $application):
                        $status = strtolower((string)($application['status'] ?? 'submitted'));
                        $badgeClass = match ($status) {
                            'approved', 'completed' => 'badge-approved',
                            'rejected' => 'badge-rejected',
                            default => 'badge-pending',
                        };
                    ?>
                        <tr>
                            <td class="program-name-cell"><?= h($application['program_name'] ?? 'Program unavailable') ?></td>
                            <td><?= h($application['partner_institution'] ?? '—') ?></td>
                            <td><?= h($application['country_name'] ?? '—') ?></td>
                            <td class="date-range">
                                <?= !empty($application['start_date']) ? h(date('M j, Y', strtotime($application['start_date']))) : '—' ?>
                                <span class="date-sep">–</span>
                                <?= !empty($application['end_date']) ? h(date('M j, Y', strtotime($application['end_date']))) : '—' ?>
                            </td>
                            <td class="date-applied">
                                <?= !empty($application['created_at']) ? h(date('M j, Y', strtotime($application['created_at']))) : '—' ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $badgeClass ?>">
                                    <?= h(ucwords(str_replace('_', ' ', $status))) ?>
                                </span>
                            </td>
                            <td>
                                <a class="btn-view" href="?page=my_application_detail&id=<?= (int)$application['id'] ?>">
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
