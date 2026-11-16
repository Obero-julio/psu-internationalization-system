<?php
if (!isset($pdo, $userId, $user) || !($pdo instanceof PDO)) {
    header('Location: dashboard.php?page=profile');
    exit;
}

$saved = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    } else {
        $first = is_string($_POST['first_name'] ?? null) ? trim($_POST['first_name']) : '';
        $middle = is_string($_POST['middle_name'] ?? null) ? trim($_POST['middle_name']) : '';
        $last = is_string($_POST['last_name'] ?? null) ? trim($_POST['last_name']) : '';
        $suffix = is_string($_POST['suffix'] ?? null) ? trim($_POST['suffix']) : '';
        $contact = is_string($_POST['contact'] ?? null) ? trim($_POST['contact']) : '';
        $birthdate = is_string($_POST['birthdate'] ?? null) ? trim($_POST['birthdate']) : '';
        $sex = is_string($_POST['sex'] ?? null) ? $_POST['sex'] : '';

        if ($first === '') {
            $errors[] = 'First name is required.';
        }
        if ($last === '') {
            $errors[] = 'Last name is required.';
        }
        if (mb_strlen($first) > 100 || mb_strlen($middle) > 100 || mb_strlen($last) > 100) {
            $errors[] = 'Names must be 100 characters or fewer.';
        }
        if (mb_strlen($suffix) > 20) {
            $errors[] = 'Suffix must be 20 characters or fewer.';
        }
        if (mb_strlen($contact) > 20) {
            $errors[] = 'Contact number must be 20 characters or fewer.';
        }
        if ($birthdate !== '') {
            $parsedBirthdate = DateTimeImmutable::createFromFormat('!Y-m-d', $birthdate);
            if (!$parsedBirthdate || $parsedBirthdate->format('Y-m-d') !== $birthdate) {
                $errors[] = 'Enter a valid birthdate.';
            }
        }
        if (!in_array($sex, ['', 'Male', 'Female', 'Prefer not to say'], true)) {
            $errors[] = 'Select a valid option for sex.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare("
                UPDATE users
                SET first_name = ?, middle_name = ?, last_name = ?, suffix = ?,
                    contact = ?, birthdate = ?, sex = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $first,
                $middle !== '' ? $middle : null,
                $last,
                $suffix !== '' ? $suffix : null,
                $contact !== '' ? $contact : null,
                $birthdate !== '' ? $birthdate : null,
                $sex !== '' ? $sex : null,
                $userId,
            ]);

            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $_SESSION['first_name'] = $first;
            $saved = true;
        }
    }
}
?>

<section class="content-card" aria-labelledby="profile-title">
    <div class="page-header">
        <div>
            <h2 id="profile-title">My Profile</h2>
            <p>Keep your personal information up to date.</p>
        </div>
    </div>

    <?php if ($saved): ?>
        <div class="alert alert-success" role="status">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i> Profile updated.
        </div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert alert-error" role="alert">
            <ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" class="apply-form">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <div class="form-grid">
            <div class="form-group">
                <label for="profile-first-name">First Name *</label>
                <input id="profile-first-name" type="text" name="first_name" maxlength="100" value="<?= h($user['first_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="profile-middle-name">Middle Name</label>
                <input id="profile-middle-name" type="text" name="middle_name" maxlength="100" value="<?= h($user['middle_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="profile-last-name">Last Name *</label>
                <input id="profile-last-name" type="text" name="last_name" maxlength="100" value="<?= h($user['last_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="profile-suffix">Suffix</label>
                <input id="profile-suffix" type="text" name="suffix" maxlength="20" value="<?= h($user['suffix'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="profile-email">Email</label>
                <input id="profile-email" type="email" value="<?= h($user['email'] ?? '') ?>" disabled>
            </div>
            <div class="form-group">
                <label for="profile-contact">Contact Number</label>
                <input id="profile-contact" type="text" name="contact" maxlength="20" value="<?= h($user['contact'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="profile-birthdate">Birthdate</label>
                <input id="profile-birthdate" type="date" name="birthdate" value="<?= h($user['birthdate'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="profile-sex">Sex</label>
                <select id="profile-sex" name="sex">
                    <option value="">Prefer not to specify</option>
                    <option value="Male" <?= ($user['sex'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= ($user['sex'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                    <option value="Prefer not to say" <?= ($user['sex'] ?? '') === 'Prefer not to say' ? 'selected' : '' ?>>Prefer not to say</option>
                </select>
            </div>
            <div class="form-group">
                <label for="profile-program">Program</label>
                <input id="profile-program" type="text" value="<?= h($user['program'] ?? '') ?>" disabled>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-primary">Save Changes</button>
        </div>
    </form>
</section>
