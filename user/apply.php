<?php
<<<<<<< HEAD
// apply.php — handles ?page=apply&program_id=X
=======
>>>>>>> d3584a4a544d2d7e792870691aa98d25b9cb5666
if (!isset($pdo, $userId, $user) || !($pdo instanceof PDO)) {
    header('Location: dashboard.php?page=available_programs');
    exit;
}

<<<<<<< HEAD
$message = '';
$msgType = '';
$programId = (int)($_GET['program_id'] ?? $_POST['program_id'] ?? 0);

if ($programId <= 0) {
    header('Location: dashboard.php?page=available_programs');
    exit;
}

// Verify program is eligible for this user
$stmt = $pdo->prepare("
    SELECT p.id, p.program_name, p.description, p.start_date, p.end_date,
           country.country_name
    FROM programs p
    LEFT JOIN countries country ON country.id = p.country_id
    WHERE p.id = ?
      AND p.status IN ('Active', 'Upcoming')
      AND p.end_date >= CURDATE()
      AND p.eligible_account_type = ?
      AND (p.eligible_department = 'All Departments' OR p.eligible_department = ?)
");
$stmt->execute([$programId, $user['account_type'] ?? '', $user['department'] ?? '']);
$program = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$program) {
    header('Location: dashboard.php?page=available_programs');
    exit;
}

// Check for an existing application
$check = $pdo->prepare("SELECT id, status FROM applications WHERE applicant_id = ? AND program_id = ? LIMIT 1");
$check->execute([$userId, $programId]);
$existing = $check->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    if (!checkCsrf()) {
        $message = 'Your session expired. Please refresh and try again.';
        $msgType = 'error';
    } elseif ($existing) {
        $message = 'You have already applied to this program.';
        $msgType = 'warning';
    } else {
        try {
            // CRITICAL: program_id MUST be stored here so documents.php can find it
            $ins = $pdo->prepare("
                INSERT INTO applications (applicant_id, program_id, status, created_at, updated_at)
                VALUES (?, ?, 'pending', NOW(), NOW())
            ");
            $ins->execute([$userId, $programId]);

            $message = 'Application submitted successfully! You will be notified once it is reviewed.';
            $msgType = 'success';
            $existing = ['id' => (int)$pdo->lastInsertId(), 'status' => 'pending'];
        } catch (PDOException $e) {
            error_log('Application insert failed: ' . $e->getMessage());
            $message = 'Could not submit your application. Please try again.';
            $msgType = 'error';
        }
    }
}

// Fetch requirements for this program (for the preview checklist)
$reqStmt = $pdo->prepare("
    SELECT pr.id, pr.is_required, r.requirement_name, r.description
    FROM program_requirements pr
    INNER JOIN requirements r ON pr.requirement_id = r.id
    WHERE pr.program_id = ?
    ORDER BY r.requirement_name
");
$reqStmt->execute([$programId]);
$requirements = $reqStmt->fetchAll(PDO::FETCH_ASSOC);

$csrfToken = $_SESSION['csrf_token'] ?? '';
=======
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
>>>>>>> d3584a4a544d2d7e792870691aa98d25b9cb5666
?>
<style>
    .apply-page { padding: 32px; background: var(--white); border: 1px solid var(--border-light); border-radius: var(--radius-lg); box-shadow: var(--shadow-card); }
    .apply-page h2 { font-family: var(--font-heading); color: var(--psu-navy); margin-bottom: 8px; }
    .apply-page .lead { color: var(--text-muted); margin-bottom: 24px; }
    .apply-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 24px 0; }
    .apply-meta div { padding: 16px; background: var(--bg-main); border-radius: var(--radius-sm); }
    .apply-meta small { display: block; text-transform: uppercase; font-size: 0.7rem; color: var(--text-light); font-weight: 700; margin-bottom: 4px; }
    .apply-meta strong { color: var(--psu-navy); font-family: var(--font-heading); }
    .req-list { list-style: none; padding: 0; margin: 16px 0 24px; }
    .req-list li { padding: 12px 16px; border: 1px solid var(--border-light); border-radius: var(--radius-sm); margin-bottom: 8px; }
    .req-list li strong { color: var(--psu-navy); }
    .req-list li small { color: var(--text-muted); display: block; margin-top: 2px; }
    .apply-actions { display: flex; gap: 12px; margin-top: 24px; }
    .btn-apply { padding: 12px 24px; background: var(--psu-navy); color: #fff; border: 1px solid var(--psu-navy); border-radius: var(--radius-sm); font-weight: 700; cursor: pointer; }
    .btn-apply:hover { background: var(--psu-blue); border-color: var(--psu-blue); }
    .btn-back { padding: 12px 24px; background: var(--white); color: var(--psu-navy); border: 1px solid var(--border-light); border-radius: var(--radius-sm); text-decoration: none; font-weight: 600; }
    .program-alert { padding: 16px 20px; border-radius: var(--radius-sm); margin-bottom: 24px; border: 1px solid; font-weight: 500; }
    .program-alert.success { color: #047857; background: #ecfdf5; border-color: #a7f3d0; }
    .program-alert.error { color: #b91c1c; background: #fef2f2; border-color: #fecaca; }
    .program-alert.warning { color: #92400e; background: #fffbeb; border-color: #fde68a; }
</style>

<<<<<<< HEAD
<div class="apply-page">
    <h2>Apply to <?= h($program['program_name']) ?></h2>
    <p class="lead">Review the program details and requirements before submitting your application.</p>

    <?php if ($message): ?>
        <div class="program-alert <?= h($msgType) ?>"><?= h($message) ?></div>
    <?php endif; ?>

    <p><?= nl2br(h($program['description'] ?? '')) ?></p>

    <div class="apply-meta">
        <div><small>Location</small><strong><?= h($program['country_name'] ?? 'TBA') ?></strong></div>
        <div><small>Duration</small><strong>
            <?= h(date('M d, Y', strtotime($program['start_date']))) ?> – <?= h(date('M d, Y', strtotime($program['end_date']))) ?>
        </strong></div>
    </div>

    <?php if ($requirements): ?>
        <h3 style="font-family: var(--font-heading); color: var(--psu-navy);">Requirements Preview</h3>
        <p style="color: var(--text-muted); font-size: 0.9rem;">You will upload these after your application is approved.</p>
        <ul class="req-list">
            <?php foreach ($requirements as $r): ?>
                <li>
                    <strong><?= h($r['requirement_name']) ?><?= !empty($r['is_required']) ? ' *' : '' ?></strong>
                    <small><?= h($r['description'] ?? '') ?></small>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="apply-actions">
        <a class="btn-back" href="?page=available_programs">Back</a>
        <?php if (!$existing): ?>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="program_id" value="<?= (int)$programId ?>">
                <button type="submit" name="submit_application" class="btn-apply">Submit Application</button>
            </form>
        <?php else: ?>
            <a class="btn-back" href="?page=my_applications">View My Application</a>
        <?php endif; ?>
    </div>
</div>
=======
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
>>>>>>> d3584a4a544d2d7e792870691aa98d25b9cb5666
