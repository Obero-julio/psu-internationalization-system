<?php
if (!isset($pdo, $userId, $user) || !($pdo instanceof PDO)) {
    header('Location: dashboard.php?page=available_programs');
    exit;
}

$programId = (int)($_GET['program_id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.program_name,
        p.start_date,
        p.end_date,
        COALESCE(NULLIF(p.partner_institution, ''), partner.institution_name) AS partner_institution,
        country.country_name
    FROM programs p
    LEFT JOIN partners partner ON partner.id = p.partner_id
    LEFT JOIN countries country ON country.id = p.country_id
    WHERE p.id = ? AND p.status IN ('Active', 'Upcoming')
");
$stmt->execute([$programId]);
$program = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$program) {
    echo '<div class="content-card"><p>This program is unavailable for applications.</p></div>';
    return;
}

$stmt = $pdo->prepare("
    SELECT id, status
    FROM applications
    WHERE applicant_id = ? AND program_id = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute([$userId, $programId]);
$existingApplication = $stmt->fetch(PDO::FETCH_ASSOC);

$errors = [];
$mobilityType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existingApplication) {
    if (!checkCsrf()) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    } else {
        $mobilityType = is_string($_POST['mobility_type'] ?? null)
            ? trim($_POST['mobility_type'])
            : '';

        if (!in_array($mobilityType, ['Inbound', 'Outbound'], true)) {
            $errors[] = 'Select a valid mobility type.';
        }
        if (($_POST['confirm'] ?? '') !== '1') {
            $errors[] = 'Confirm the application details before submitting.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare("
                INSERT INTO applications (applicant_id, program_id, mobility_type, status)
                VALUES (?, ?, ?, 'submitted')
            ");
            $stmt->execute([$userId, $programId, $mobilityType]);
            $applicationId = (int)$pdo->lastInsertId();

            header('Location: ?page=my_application_detail&id=' . $applicationId);
            exit;
        }
    }
}
?>

<section class="content-card" aria-labelledby="apply-title">
    <div class="page-header">
        <div>
            <h2 id="apply-title">Apply — <?= h($program['program_name']) ?></h2>
            <p>Review the program details and choose your mobility type.</p>
        </div>
    </div>

    <?php if ($existingApplication): ?>
        <div class="alert info">
            You already have an application for this program
            (<?= h(ucwords(str_replace('_', ' ', $existingApplication['status']))) ?>).
        </div>
        <a class="btn-primary" href="?page=my_application_detail&id=<?= (int)$existingApplication['id'] ?>">
            View Application
        </a>
    <?php else: ?>
        <?php if ($errors): ?>
            <div class="alert alert-error" role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="detail-grid">
            <div><label>Partner Institution</label><strong><?= h($program['partner_institution'] ?? '—') ?></strong></div>
            <div><label>Country</label><strong><?= h($program['country_name'] ?? '—') ?></strong></div>
            <div><label>Program Dates</label><strong><?= h($program['start_date']) ?> – <?= h($program['end_date']) ?></strong></div>
            <div><label>Applicant</label><strong><?= h(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></strong></div>
        </div>

        <form method="post" class="apply-form">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

            <div class="form-grid">
                <label for="mobility-type">Mobility Type
                    <select id="mobility-type" name="mobility_type" required>
                        <option value="">Select a mobility type</option>
                        <option value="Inbound" <?= $mobilityType === 'Inbound' ? 'selected' : '' ?>>Inbound</option>
                        <option value="Outbound" <?= $mobilityType === 'Outbound' ? 'selected' : '' ?>>Outbound</option>
                    </select>
                </label>
            </div>

            <label class="checkbox-row">
                <input type="checkbox" name="confirm" value="1" required>
                I confirm the application information is accurate.
            </label>

            <div class="form-actions">
                <a class="btn-secondary" href="?page=program_detail&id=<?= (int)$program['id'] ?>">Back to Program</a>
                <button type="submit" class="btn-primary">Submit Application</button>
            </div>
        </form>
    <?php endif; ?>
</section>
