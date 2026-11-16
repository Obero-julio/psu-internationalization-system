<?php
$message = '';
$messageType = '';

if (!isset($pdo, $userId) || !($pdo instanceof PDO)) {
    header('Location: dashboard.php?page=settings');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $new = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

    if (!checkCsrf()) {
        $message = 'Your session expired. Please refresh the page and try again.';
        $messageType = 'error';
    } elseif ($current === '' || $new === '' || $confirm === '') {
        $message = 'Complete all password fields.';
        $messageType = 'error';
    } else {
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        if (!is_string($hash) || !password_verify($current, $hash)) {
            $message = 'Current password is incorrect.';
            $messageType = 'error';
        } elseif (strlen($new) < 8) {
            $message = 'New password must be at least 8 characters.';
            $messageType = 'error';
        } elseif ($new !== $confirm) {
            $message = 'Passwords do not match.';
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
            $message = 'Password updated.';
            $messageType = 'success';
        }
    }
}
?>

<section class="content-card" aria-labelledby="settings-title">
    <div class="page-header">
        <div><h2 id="settings-title">Settings</h2><p>Change your password.</p></div>
    </div>
    <?php if ($message): ?>
        <div class="alert alert-<?= h($messageType) ?>" role="<?= $messageType === 'success' ? 'status' : 'alert' ?>">
            <?= h($message) ?>
        </div>
    <?php endif; ?>
    <form method="post" class="apply-form">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <div class="form-grid">
            <label for="current-password">Current Password
                <input id="current-password" type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <label for="new-password">New Password
                <input id="new-password" type="password" name="new_password" autocomplete="new-password" minlength="8" required>
            </label>
            <label for="confirm-password">Confirm New Password
                <input id="confirm-password" type="password" name="confirm_password" autocomplete="new-password" minlength="8" required>
            </label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-primary">Update Password</button>
        </div>
    </form>
</section>