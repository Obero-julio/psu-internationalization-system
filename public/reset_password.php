<?php
require_once __DIR__ . '/../config/auth_co.php';
session_start();

if (!function_exists('e')) {
    function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
}

$email = filter_var($_GET['email'] ?? $_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$otp   = preg_replace('/\D/', '', $_GET['otp'] ?? $_POST['otp'] ?? '');

if (!$email || strlen($otp) !== 6) {
    die("Invalid or missing reset link. Please request a new one.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new  = $_POST['password'] ?? '';
    $conf = $_POST['confirm_password'] ?? '';

    if (strlen($new) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($new !== $conf) {
        $error = 'Passwords do not match.';
    } else {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND reset_token = ? AND reset_token_expiry > NOW() LIMIT 1');
        $stmt->execute([$email, hash('sha256', $otp)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = 'This reset link has expired or is invalid.';
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            
            $success = 'Password updated successfully! You can now log in.';
            header('Refresh: 3; url=user_login.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Set New Password | PSU Internalization</title>
  <link rel="stylesheet" href="../asset/css/user_forgot_pass.css">
</head>
<body>
  <main class="shell">
    <aside class="panel">
      <div class="brand"><span class="brand-mark">PSU</span> Account Recovery</div>
      <h2>Set your<span>New Password</span></h2>
      <p>Your link was verified successfully. Choose a strong new password below.</p>
      <a href="user_login.php" class="ghost-btn">Sign In</a>
    </aside>
    <section class="form-side">
      <h1>New Password</h1>
      <p class="subtitle">Account: <strong><?= e($email) ?></strong></p>
      
      <?php if ($error): ?>
        <div class="alert" style="color: red; background: #ffe6e6; padding: 10px; margin-bottom: 15px; border-radius: 5px;"><?= e($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert success" style="color: green; background: #e6ffe6; padding: 10px; margin-bottom: 15px; border-radius: 5px;"><?= e($success) ?></div>
      <?php else: ?>
        <form method="POST">
          <input type="hidden" name="email" value="<?= e($email) ?>">
          <input type="hidden" name="otp" value="<?= e($otp) ?>">
          
          <div class="field">
            <label class="lbl">New Password</label>
            <div class="wrap">
              <input type="password" name="password" minlength="8" required placeholder="Enter new password" autofocus>
            </div>
          </div>
          
          <div class="field">
            <label class="lbl">Confirm Password</label>
            <div class="wrap">
              <input type="password" name="confirm_password" minlength="8" required placeholder="Confirm new password">
            </div>
          </div>

          <button type="submit" class="submit-btn">Update Password</button>
        </form>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>