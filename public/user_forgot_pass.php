<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ------------------------------------------------------------------
 * MAIL CONFIG
 * ------------------------------------------------------------------ */
if (!defined('MAIL_HOST'))        define('MAIL_HOST', 'smtp.example.com');
if (!defined('MAIL_USERNAME'))    define('MAIL_USERNAME', 'no-reply@example.com');
if (!defined('MAIL_PASSWORD'))    define('MAIL_PASSWORD', '');
if (!defined('MAIL_PORT'))        define('MAIL_PORT', 587);
if (!defined('MAIL_FROM_ADDR'))   define('MAIL_FROM_ADDR', 'no-reply@psuinternalization.edu');
if (!defined('MAIL_FROM_NAME'))   define('MAIL_FROM_NAME', 'PSU Internalization');
if (!defined('APP_BASE_URL'))     define('APP_BASE_URL', (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']);

function sendPasswordResetEmail(string $toEmail, string $resetToken): bool {
    $resetLink = rtrim(APP_BASE_URL, '/') . '/reset.php?token=' . urlencode($resetToken);

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_FROM_ADDR, MAIL_FROM_NAME);
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = 'Reset your PSU Internalization password';
        $mail->Body    = '
            <p>We received a request to reset your password.</p>
            <p><a href="' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '">Click here to reset your password</a></p>
            <p>This link expires in 1 hour. If you didn\'t request this, you can ignore this email.</p>
        ';
        $mail->AltBody = "Reset your password: {$resetLink} (expires in 1 hour)";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Password reset email failed: ' . $mail->ErrorInfo);
        return false;
    }
}

$pdo = db();

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* ==================================================================
 * STATE VARIABLES
 * ================================================================== */
$forgot_error   = '';
$forgot_message = '';
$forgot_email   = '';

/* ==================================================================
 * POST HANDLING
 * ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $forgot_email = trim($_POST['email'] ?? '');
    $email = filter_var($forgot_email, FILTER_VALIDATE_EMAIL);

    if (!$email) {
        $forgot_error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND user_role IN ('applicant', 'ap') LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $reset_token  = bin2hex(random_bytes(32));
                $token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $update = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
                $update->execute([hash('sha256', $reset_token), $token_expiry, $user['id']]);
                
                sendPasswordResetEmail($email, $reset_token);
            }
            // Same message whether or not the account exists, so we don't
            // leak which emails are registered.
            $forgot_message = 'If an account exists for that email, a reset link has been sent.';
        } catch (PDOException $ex) {
            $forgot_error = 'System error. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — PSU Internalization</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ===== DESIGN TOKENS ===== */
        :root {
            --blue:        #1a4a8a;
            --blue-light:  #2a63b8;
            --blue-dark:   #0f2c57;
            --gold:        #e8b830;
            --gold-light:  #f6d676;
            --bg:          #eef2f8;
            --white:       #ffffff;
            --navy:        #14233d;
            --gray-700:    #3a4a5e;
            --gray-500:    #6b7a8f;
            --gray-400:    #94a3b8;
            --gray-300:    #cbd5e1;
            --gray-200:    #e2e8f0;
            --gray-100:    #f4f7fb;
            --radius-sm:   10px;
            --radius:      14px;
            --radius-lg:   24px;
            --shadow-lg:   0 24px 64px rgba(15,44,87,0.15);
            --shadow-btn:  0 8px 24px rgba(26,74,138,0.35);
            --font-body:   'Inter', sans-serif;
            --font-heading:'Plus Jakarta Sans', sans-serif;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { min-height: 100%; }
        body {
            font-family: var(--font-body);
            background: var(--bg);
            color: var(--navy);
            -webkit-font-smoothing: antialiased;
        }
        a:focus-visible, button:focus-visible, input:focus-visible {
            outline: 2px solid var(--blue);
            outline-offset: 2px;
        }

        /* ===== PAGE LAYOUT ===== */
        .page {
            display: grid;
            grid-template-columns: minmax(420px, 1fr) 1fr;
            min-height: 100vh;
        }

        /* ===== HERO PANEL (LEFT) ===== */
        .hero {
            position: relative;
            overflow: hidden;
            color: var(--white);
            padding: 56px 64px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: 
                radial-gradient(circle at 100% 0%, rgba(232,184,48,0.2) 0%, transparent 40%),
                radial-gradient(circle at 0% 100%, rgba(42,99,184,0.6) 0%, transparent 50%),
                linear-gradient(135deg, var(--blue-dark) 0%, var(--blue) 100%);
            z-index: 1;
        }

        /* Subtle grid pattern */
        .hero::before {
            content: '';
            position: absolute; inset: 0;
            background-image: 
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            mask-image: radial-gradient(circle at center, black 40%, transparent 80%);
            -webkit-mask-image: radial-gradient(circle at center, black 40%, transparent 80%);
            z-index: -1;
        }

        /* Animated glowing orbs */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            z-index: -1;
            animation: pulse 8s infinite alternate;
        }
        .orb.o1 {
            width: 400px; height: 400px;
            background: rgba(232,184,48,0.15);
            top: -100px; right: -100px;
        }
        .orb.o2 {
            width: 300px; height: 300px;
            background: rgba(42,99,184,0.3);
            bottom: -50px; left: -50px;
            animation-delay: -4s;
        }
        @keyframes pulse {
            0% { transform: scale(1) translate(0, 0); opacity: 0.8; }
            100% { transform: scale(1.1) translate(20px, 20px); opacity: 1; }
        }

        .hero-top, .hero-body, .hero-foot { position: relative; z-index: 2; }

        /* Brand Logo */
        .brand {
            display: inline-flex; align-items: center; gap: 12px;
            font-family: var(--font-heading);
            font-weight: 800; font-size: 1.4rem;
            color: var(--white); text-decoration: none;
        }
        .brand .badge {
            background: var(--gold); color: var(--blue-dark);
            font-size: 0.75rem; padding: 6px 12px; border-radius: 8px; letter-spacing: 0.5px;
            box-shadow: 0 4px 16px rgba(232,184,48,0.4);
        }
        .brand span.gold { color: var(--gold-light); }

        /* Hero Text */
        .hero-body { margin: 48px 0; }
        .eyebrow {
            display: inline-flex; align-items: center; gap: 10px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            padding: 8px 16px; border-radius: 999px;
            font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px;
            margin-bottom: 24px;
            color: rgba(255,255,255,0.9);
        }
        .eyebrow i { 
            width: 8px; height: 8px; border-radius: 50%; 
            background: var(--gold); box-shadow: 0 0 12px var(--gold); 
        }
        
        .hero h2 {
            font-family: var(--font-heading);
            font-size: clamp(2.2rem, 3.5vw, 3.2rem);
            line-height: 1.15; font-weight: 800; letter-spacing: -1px;
            margin-bottom: 20px;
        }
        .hero h2 em { 
            font-style: normal; color: var(--gold); 
            position: relative; white-space: nowrap; 
        }
        .hero p.lead { 
            color: rgba(255,255,255,0.8); 
            font-size: 1.05rem; line-height: 1.7; 
            max-width: 420px; 
            margin-bottom: 40px;
        }

        /* Feature Glass Cards */
        .features { list-style: none; display: grid; gap: 16px; max-width: 440px; }
        .features li {
            display: flex; align-items: center; gap: 16px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            backdrop-filter: blur(12px);
            padding: 16px 20px; border-radius: var(--radius);
            transition: all 0.3s ease;
        }
        .features li:hover { 
            transform: translateX(8px); 
            background: rgba(255,255,255,0.1); 
            border-color: rgba(255,255,255,0.25);
        }
        .features .ico {
            flex: 0 0 44px; height: 44px; border-radius: 12px;
            display: grid; place-items: center;
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: var(--blue-dark);
            box-shadow: 0 4px 12px rgba(232,184,48,0.3);
        }
        .features strong { display: block; font-family: var(--font-heading); font-size: 1rem; margin-bottom: 4px; }
        .features small { color: rgba(255,255,255,0.6); font-size: 0.85rem; }
        
        .hero-foot { font-size: 0.85rem; color: rgba(255,255,255,0.5); }

        /* ===== FORM SIDE (RIGHT) ===== */
        .form-side {
            display: flex; align-items: center; justify-content: center;
            padding: 48px 32px;
            background: var(--gray-100);
            position: relative;
        }

        /* Form Card */
        .login-card {
            width: 100%; max-width: 460px;
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            padding: 48px;
            position: relative; overflow: hidden;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes slideUp { 
            from { opacity: 0; transform: translateY(30px); } 
            to { opacity: 1; transform: translateY(0); } 
        }

        /* Card Top Accent */
        .login-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 6px;
            background: linear-gradient(90deg, var(--blue-dark), var(--blue), var(--gold));
        }

        .login-header { margin-bottom: 32px; text-align: center; }
        .login-header h1 {
            font-family: var(--font-heading);
            font-size: 2rem; font-weight: 800; letter-spacing: -0.8px;
            margin-bottom: 8px; color: var(--navy);
        }
        .login-header p { color: var(--gray-500); font-size: 0.95rem; }

        /* Alerts */
        .alert-error {
            background: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #ef4444;
            color: #b91c1c; padding: 16px; border-radius: var(--radius-sm);
            margin-bottom: 24px; font-size: 0.9rem; font-weight: 500;
            display: flex; align-items: center; gap: 12px;
            animation: shake 0.4s ease-in-out;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        .alert-error svg { width: 20px; height: 20px; flex-shrink: 0; }

        .alert-success {
            background: #ecfdf5; border: 1px solid #a7f3d0; border-left: 4px solid #10b981;
            color: #047857; padding: 16px; border-radius: var(--radius-sm);
            margin-bottom: 24px; font-size: 0.9rem; font-weight: 600;
            display: flex; align-items: center; gap: 12px;
        }
        .alert-success svg { width: 20px; height: 20px; flex-shrink: 0; }

        /* Input Fields */
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--gray-700); margin-bottom: 8px; }
        
        .input-wrap { position: relative; }
        .input-wrap > svg.lead-ic {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            width: 20px; height: 20px; color: var(--gray-400); pointer-events: none; transition: color 0.2s;
        }
        
        .form-input {
            width: 100%; padding: 16px 16px 16px 48px;
            border: 1.5px solid var(--gray-200); border-radius: var(--radius-sm);
            font-family: var(--font-body); font-size: 0.95rem; color: var(--navy);
            background: var(--white); transition: all 0.2s ease;
        }
        .form-input:hover { border-color: var(--gray-300); background: var(--gray-100); }
        .form-input:focus {
            outline: none; background: var(--white);
            border-color: var(--blue); box-shadow: 0 0 0 4px rgba(26,74,138,0.1);
        }
        .input-wrap:focus-within > svg.lead-ic { color: var(--blue); }
        .form-input::placeholder { color: var(--gray-400); }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, var(--blue) 0%, var(--blue-light) 100%);
            color: var(--white); border: none; border-radius: var(--radius-sm);
            font-family: var(--font-heading); font-size: 1.05rem; font-weight: 700;
            padding: 16px; cursor: pointer; transition: all 0.3s ease;
            box-shadow: var(--shadow-btn);
            position: relative; overflow: hidden;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-submit::after {
            content: ''; position: absolute; top: 0; left: -100%; width: 50%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }
        .btn-submit:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 12px 28px rgba(26,74,138,0.45); 
        }
        .btn-submit:hover::after { left: 150%; }
        .btn-submit:active { transform: translateY(0); }

        /* Footer Links */
        .login-footer { text-align: center; margin-top: 32px; font-size: 0.9rem; color: var(--gray-500); }
        .login-footer a { color: var(--blue); text-decoration: none; font-weight: 700; transition: color 0.2s; }
        .login-footer a:hover { color: var(--blue-dark); text-decoration: underline; }

        .copyright { text-align: center; margin-top: 24px; font-size: 0.8rem; color: var(--gray-400); }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .hero { padding: 40px; }
            .hero h2 { font-size: 2.2rem; }
        }
        @media (max-width: 860px) {
            .page { grid-template-columns: 1fr; }
            .hero { display: none; /* Hide hero on tablets/mobile for focus */ }
            .form-side { padding: 32px 16px; background: var(--bg); }
            .login-card { padding: 36px 28px; box-shadow: var(--shadow-md); }
        }
        @media (max-width: 480px) {
            .login-card { padding: 28px 20px; border-radius: var(--radius); }
            .login-header h1 { font-size: 1.7rem; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
        }
    </style>
</head>
<body>

<div class="page">

    <!-- ============ HERO PANEL (LEFT) ============ -->
    <aside class="hero">
        <div class="orb o1"></div>
        <div class="orb o2"></div>

        <div class="hero-top">
            <a href="browse_program.php" class="brand"><span class="badge">PSU</span> PSU <span class="gold">Internalization</span></a>
        </div>

        <div class="hero-body">
            <div class="eyebrow"><i></i> Account Recovery</div>
            <h2>Get back to your <em>global</em> journey.</h2>
            <p class="lead">Forgot your password? No worries. Enter your email address and we'll send you a secure link to reset it.</p>

            <ul class="features">
                <li>
                    <div class="ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
                    <div><strong>Secure recovery</strong><small>Your account safety is our priority</small></div>
                </li>
                <li>
                    <div class="ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 6L2 7"/></svg></div>
                    <div><strong>Instant email delivery</strong><small>Receive your reset link immediately</small></div>
                </li>
            </ul>
        </div>

        <div class="hero-foot">&copy; <?= date('Y') ?> Pangasinan State University &middot; Internalization Office</div>
    </aside>

    <!-- ============ FORM SIDE (RIGHT) ============ -->
    <main class="form-side">
        <div class="login-card">

            <div class="login-header">
                <h1>Reset Password</h1>
                <p>Enter your email to recover your account</p>
            </div>

            <?php if ($forgot_error): ?>
                <div class="alert-error">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?= e($forgot_error) ?>
                </div>
            <?php endif; ?>

            <?php if ($forgot_message): ?>
                <div class="alert-success">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <?= e($forgot_message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="forgot.php">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <div class="input-wrap">
                        <svg class="lead-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 6L2 7"/></svg>
                        <input class="form-input" type="email" name="email" id="email"
                            placeholder="Enter your email address"
                            value="<?= e($forgot_email) ?>"
                            required autocomplete="email" autofocus>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    Send Reset Link
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </form>

            <div class="login-footer">
                Remember your password? <a href="user_login.php">Sign In</a>
            </div>

            <div class="copyright">
                &copy; <?= date('Y') ?> Pangasinan State University
            </div>

        </div>
    </main>
</div>

</body>
</html>