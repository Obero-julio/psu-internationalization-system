<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$pdo = db();

/* ------------------------------------------------------------------
 * ROUTING
 * ------------------------------------------------------------------ */
$allowedPages = ['login', 'browse'];
$page = $_GET['page'] ?? 'login';
if (!in_array($page, $allowedPages, true)) {
    $page = 'login';
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* ==================================================================
 * STATE VARIABLES
 * ================================================================== */
$login_error    = '';
$login_username = $_COOKIE['remember_user'] ?? '';
$login_remember = !empty($_COOKIE['remember_user']);

/* ==================================================================
 * POST HANDLING (LOGIN ONLY) — USER PORTAL ONLY
 * Admins & super admins are NOT allowed to authenticate here.
 * ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'login') {

    $login_username = trim($_POST['username'] ?? '');
    $password       = $_POST['password'] ?? '';
    $login_remember = isset($_POST['remember']);

    if ($login_username === '' || $password === '') {
        $login_error = 'Please enter your email and password.';
    } else {
        $stmt = $pdo->prepare("
            SELECT id, first_name, email, user_role, password_hash
            FROM users
            WHERE email = :username
            LIMIT 1
        ");
        $stmt->execute([':username' => $login_username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Generic message — do not reveal whether email exists
            $login_error = 'Invalid email or password. Please try again.';
        } else {
            $role = strtolower(trim($user['user_role'] ?? ''));

            // -------- BLOCK ADMIN / SUPER ADMIN FROM USER PORTAL --------
            if (in_array($role, ['admin', 'super admin'], true)) {
                // Do NOT create a session. Do NOT set remember cookie.
                // Generic message so we don't confirm the account type.
                $login_error = 'Invalid email or password. Please try again.';
            }
            // -------- ONLY ALLOW USER-SIDE ROLES --------
            elseif (in_array($role, ['applicant', 'student', 'faculty', 'user', 'ap'], true)) {

                session_regenerate_id(true);
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['username']   = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['user_role']  = $user['user_role'];

                $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

                if ($login_remember) {
                    setcookie('remember_user', $login_username, [
                        'expires'  => time() + 60 * 60 * 24 * 30,
                        'path'     => '/',
                        'secure'   => $isHttps,
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                } else {
                    setcookie('remember_user', '', [
                        'expires'  => time() - 3600,
                        'path'     => '/',
                    ]);
                }

                header('Location: ../user/home.php');
                exit();
            }
            // -------- UNKNOWN ROLE --------
            else {
                $login_error = 'Your account does not have access to this portal.';
            }
        }
    }
}

/* ==================================================================
 * BROWSE DATA (If routed to browse)
 * ================================================================== */
$programs = [];
$loadError = null;
$typeCounts = ['exchange' => 0, 'internship' => 0, 'research' => 0, 'training' => 0];
$countrySet = [];
$totalPrograms = 0;
$totalCountries = 0;

if ($page === 'browse') {
    try {
        $stmt = $pdo->prepare("
            SELECT p.id, p.program_name, p.program_type, p.description, p.start_date, p.end_date,
                   p.status, p.partner_id, p.country_id, c.country_name, pr.institution_name
            FROM programs p
            LEFT JOIN countries c ON p.country_id = c.id
            LEFT JOIN partners pr ON p.partner_id = pr.id
            WHERE p.status = 'Active'
            ORDER BY p.id DESC
        ");
        $stmt->execute();
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $ex) {
        error_log('index.php (browse) query failed: ' . $ex->getMessage());
        $loadError = 'We ran into a problem loading programs. Please try again shortly.';
    }

    foreach ($programs as $p) {
        $t = strtolower($p['program_type'] ?? '');
        if (isset($typeCounts[$t])) $typeCounts[$t]++;
        if (!empty($p['country_name'])) $countrySet[$p['country_name']] = true;
    }
    $totalPrograms = count($programs);
    $totalCountries = count($countrySet);
}

$pageTitles = [
    'login'    => 'Sign In — PSU Internalization',
    'browse'   => 'PSU Internalization — Student Programs Portal',
];
$pageTitle = $pageTitles[$page];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>

    <?php if ($page === 'login'): ?>
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
                --shadow-md:   0 12px 32px rgba(15,44,87,0.12);
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

            /* Login Card */
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

            /* Error Alert */
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
            
            .toggle-pw {
                position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
                background: none; border: none; cursor: pointer; padding: 8px; color: var(--gray-400);
                display: grid; place-items: center; border-radius: 8px; transition: all 0.2s;
            }
            .toggle-pw:hover { color: var(--blue); background: var(--gray-100); }

            /* Options */
            .form-options {
                display: flex; justify-content: space-between; align-items: center;
                font-size: 0.88rem; margin-bottom: 28px; margin-top: 4px;
            }
            .form-options label {
                display: flex; align-items: center; gap: 8px; cursor: pointer;
                color: var(--gray-700); font-weight: 500;
            }
            .form-options input[type="checkbox"] { 
                width: 18px; height: 18px; accent-color: var(--blue); cursor: pointer; 
                border-radius: 4px;
            }
            .form-options a { color: var(--blue); text-decoration: none; font-weight: 600; transition: color 0.2s; }
            .form-options a:hover { color: var(--blue-dark); text-decoration: underline; }

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

            /* Divider */
            .divider {
                display: flex; align-items: center; text-align: center; margin: 28px 0;
                color: var(--gray-400); font-size: 0.8rem; font-weight: 600;
                text-transform: uppercase; letter-spacing: 0.8px;
            }
            .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1.5px solid var(--gray-200); }
            .divider::before { margin-right: 20px; }
            .divider::after { margin-left: 20px; }

            /* Register Button */
            .btn-register {
                width: 100%; background: var(--white); color: var(--blue);
                border: 2px solid var(--gray-200); border-radius: var(--radius-sm);
                font-family: var(--font-heading); font-size: 1rem; font-weight: 700;
                padding: 14px; cursor: pointer; transition: all 0.25s ease;
                text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px;
            }
            .btn-register:hover { 
                background: var(--gray-100); border-color: var(--blue); 
                transform: translateY(-2px); 
            }
            .btn-register svg { transition: transform 0.25s ease; }
            .btn-register:hover svg { transform: translateX(4px); }

            /* Footer */
            .login-footer { text-align: center; margin-top: 32px; font-size: 0.8rem; color: var(--gray-400); line-height: 1.6; }
            .login-footer a { color: var(--blue); text-decoration: none; font-weight: 600; }
            .login-footer a:hover { text-decoration: underline; }

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
    <?php else: ?>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="../asset/css/website.css">
    <?php endif; ?>
</head>

<body>

<?php if ($page === 'login'): ?>

    <!-- ============================== LOGIN ============================== -->
    <div class="page">

        <!-- HERO PANEL -->
        <aside class="hero">
            <div class="orb o1"></div>
            <div class="orb o2"></div>

            <div class="hero-top">
                <a href="website.php" class="brand"><span class="badge">PSU</span> PSU <span class="gold">Internalization</span></a>
            </div>

            <div class="hero-body">
                <div class="eyebrow"><i></i> Student Mobility &amp; Internship Portal</div>
                <h2>Welcome back to your <em>global</em> journey.</h2>
                <p class="lead">Sign in to continue your applications, track your status, and discover new international programs from Pangasinan State University and its partners.</p>

                <ul class="features">
                    <li>
                        <div class="ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/></svg></div>
                        <div><strong>Exchange &amp; study abroad</strong><small>Programs with partner institutions worldwide</small></div>
                    </li>
                    <li>
                        <div class="ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg></div>
                        <div><strong>Internships &amp; research</strong><small>Gain hands-on international experience</small></div>
                    </li>
                    <li>
                        <div class="ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg></div>
                        <div><strong>Real-time updates</strong><small>Know where every application stands</small></div>
                    </li>
                </ul>
            </div>

            <div class="hero-foot">&copy; <?= date('Y') ?> Pangasinan State University &middot; Internalization Office</div>
        </aside>

        <!-- FORM SIDE -->
        <main class="form-side">
            <div class="login-card">

                <div class="login-header">
                    <h1>Welcome Back</h1>
                    <p>Sign in to your PSU Internalization account</p>
                </div>

                <?php if ($login_error): ?>
                    <div class="alert-error">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <?= e($login_error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="?page=login">
                    <div class="form-group">
                        <label class="form-label" for="username">Email Address</label>
                        <div class="input-wrap">
                            <svg class="lead-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 6L2 7"/></svg>
                            <input class="form-input" type="email" name="username" id="username"
                                placeholder="e.g. juan.delacruz@psu.edu.ph"
                                value="<?= e($login_username) ?>"
                                required autocomplete="username" autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-wrap">
                            <svg class="lead-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input class="form-input" type="password" name="password" id="password"
                                placeholder="Enter your password"
                                required autocomplete="current-password" style="padding-right:48px;">
                            <button type="button" class="toggle-pw" onclick="togglePw()" aria-label="Show or hide password">
                                <svg id="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label>
                            <input type="checkbox" name="remember" <?= $login_remember ? 'checked' : '' ?>>
                            Remember me
                        </label>
                        <a href="user_forgot_pass.php">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn-submit">
                        Sign In
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </button>
                </form>

                <div class="divider">New to PSU Internalization?</div>

                <a href="register.php" class="btn-register">
                    Create an Account
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>

                <div class="login-footer">
                    By signing in, you agree to our <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.
                </div>

            </div>
        </main>
    </div>

    <script>
        function togglePw() {
            const pw = document.getElementById('password');
            const icon = document.getElementById('eye-icon');
            if (pw.type === 'password') {
                pw.type = 'text';
                // Change to 'eye off' icon
                icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
            } else {
                pw.type = 'password';
                // Change back to 'eye' icon
                icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
            }
        }
    </script>

<?php elseif ($page === 'browse'): ?>

    <!-- ============================== BROWSE PROGRAMS ============================== -->
    <nav>
      <div class="nav-logo">PSU <span>Internalization</span></div>
      <ul class="nav-links">
        <li><a href="?page=browse">Browse Programs</a></li>
        <li><a href="partner_ins.php">Partner Institutions</a></li>
        <li><a href="how_it_works.php">How It Works</a></li>
        <li><a href="register.php" class="nav-cta">Register Now</a></li>
      </ul>
    </nav>

    <section class="hero">
      <div class="hero-inner">
        <div class="hero-eyebrow">Student Mobility &amp; Internship Portal</div>
        <h1>Find your next program, <em>abroad</em>.</h1>
        <p>Discover internships, exchange programs, and research opportunities from partner institutions around the world — open to PSU students.</p>
        <div class="search-bar">
          <input type="text" placeholder="Search by program name, country, or field…" id="searchInput"/>
          <select id="typeFilter">
            <option value="">All Types</option>
            <option value="exchange">Exchange</option>
            <option value="internship">Internship</option>
            <option value="research">Research</option>
            <option value="training">Training</option>
          </select>
          <button onclick="applySearch()">Search Programs</button>
        </div>
        <div class="stats-strip">
          <div class="stat"><strong><?= $totalPrograms ?></strong> Active Programs</div>
          <div class="stat"><strong><?= $totalCountries ?></strong> Partner Countries</div>
          <div class="stat"><strong>130+</strong> Students Placed</div>
        </div>
      </div>
    </section>

    <div class="page-body">
      <aside class="sidebar">
        <div class="filter-card">
          <h3>Program Type</h3>
          <div class="filter-group">
            <label><input type="checkbox" data-filter="type" value="exchange" onchange="filterCards()"> Student Exchange <span class="badge-count"><?= $typeCounts['exchange'] ?></span></label>
            <label><input type="checkbox" data-filter="type" value="internship" onchange="filterCards()"> Internship <span class="badge-count"><?= $typeCounts['internship'] ?></span></label>
            <label><input type="checkbox" data-filter="type" value="research" onchange="filterCards()"> Research <span class="badge-count"><?= $typeCounts['research'] ?></span></label>
            <label><input type="checkbox" data-filter="type" value="training" onchange="filterCards()"> Training <span class="badge-count"><?= $typeCounts['training'] ?></span></label>
          </div>
        </div>

        <div class="filter-card">
          <h3>Country / Region</h3>
          <select id="countryFilter" onchange="filterCards()">
            <option value="">All Countries</option>
            <?php foreach (array_keys($countrySet) as $countryName): ?>
              <option><?= e($countryName) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-card">
          <h3>Duration</h3>
          <div class="filter-group">
            <label><input type="checkbox" onchange="filterCards()"> 1 – 3 months</label>
            <label><input type="checkbox" onchange="filterCards()"> 4 – 6 months</label>
            <label><input type="checkbox" onchange="filterCards()"> 1 semester</label>
            <label><input type="checkbox" onchange="filterCards()"> 1 academic year</label>
          </div>
        </div>

        <div class="filter-card">
          <h3>Funding</h3>
          <div class="filter-group">
            <label><input type="checkbox" data-filter="funding" value="full scholarship" onchange="filterCards()"> With Scholarship</label>
            <label><input type="checkbox" data-filter="funding" value="partial" onchange="filterCards()"> Partially Funded</label>
            <label><input type="checkbox" data-filter="funding" value="self-funded" onchange="filterCards()"> Self-funded</label>
          </div>
        </div>

        <button class="clear-btn" onclick="clearFilters()">Clear all filters</button>
      </aside>

      <main>
        <div class="listings-header">
          <div>
            <h2>Available Programs</h2>
            <span id="resultCount">Showing <?= $totalPrograms ?> of <?= $totalPrograms ?> programs</span>
          </div>
          <select class="sort-select" id="sortSelect">
            <option value="deadline">Sort: Deadline (Soonest)</option>
            <option value="slots">Sort: Most Slots</option>
            <option value="az">Sort: A → Z</option>
          </select>
        </div>

        <?php if ($loadError): ?>
          <div class="programs-empty"><p><?= e($loadError) ?></p></div>
        <?php elseif ($totalPrograms === 0): ?>
          <div class="programs-empty"><p>No programs are available right now. Please check back soon.</p></div>
        <?php else: ?>
          <div id="programList"></div>
          <div class="pagination" id="paginationBar"></div>
        <?php endif; ?>
      </main>
    </div>

    <div class="modal-overlay" id="modalOverlay" onclick="closeModal(event)">
      <div class="modal" id="modalBox">
        <div class="modal-header">
          <div class="modal-logo" id="modalLogo"></div>
          <div>
            <div class="modal-title" id="modalTitle"></div>
            <div class="modal-institution" id="modalInstitution"></div>
            <div class="card-tags" id="modalTags"></div>
          </div>
          <button class="modal-close" onclick="closeModalDirect()">✕</button>
        </div>
        <div class="modal-body">
          <div class="modal-section">
            <h4>About this Program</h4>
            <p id="modalDesc"></p>
          </div>
          <div class="modal-section">
            <h4>Program Details</h4>
            <div class="modal-meta" id="modalMeta"></div>
          </div>
          <div class="modal-section">
            <h4>Requirements</h4>
            <ul class="requirements-list" id="modalReqs"></ul>
          </div>
          <a href="#" id="applyLink" class="apply-btn">Apply for this Program</a>
        </div>
      </div>
    </div>

    <footer>
      <strong>PSU Internalization Management System</strong><br/>
      Pangasinan State University · Office of International Affairs · <a href="#" style="color:rgba(255,255,255,.55)">Contact Us</a>
    </footer>

    <?php if (!$loadError && $totalPrograms > 0): ?>
    <script>
      const rawPrograms = <?= json_encode($programs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      const TYPE_EMOJI = { exchange: '🔄', internship: '💼', research: '🔬' };

      function formatDate(dateStr) {
        if (!dateStr) return 'TBA';
        const d = new Date(dateStr);
        if (isNaN(d)) return 'TBA';
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
      }

      function computeDuration(start, end) {
        if (!start || !end) return 'Flexible';
        const s = new Date(start), e = new Date(end);
        if (isNaN(s) || isNaN(e)) return 'Flexible';
        const months = Math.max(1, Math.round((e - s) / (1000 * 60 * 60 * 24 * 30)));
        if (months >= 10) return '1 academic year';
        if (months >= 5) return '1 semester';
        if (months >= 4) return '4 – 6 months';
        return '1 – 3 months';
      }

      function isUrgent(endDate) {
        if (!endDate) return false;
        const days = (new Date(endDate) - new Date()) / (1000 * 60 * 60 * 24);
        return days >= 0 && days <= 14;
      }

      const programs = rawPrograms.map(p => {
        const type = (p.program_type || '').toLowerCase();
        return {
            id: p.id, title: p.program_name, type: type,
            desc: p.description || 'No description available.',
            institution: p.institution_name || 'Partner Institution',
            country: p.country_name || 'Unknown',
            startDate: p.start_date, endDate: p.end_date,
            deadline: formatDate(p.end_date),
            duration: computeDuration(p.start_date, p.end_date),
            emoji: TYPE_EMOJI[type] || '🎓',
            featured: false, urgent: isUrgent(p.end_date)
        };
      });

      const PER_PAGE = 8;
      let currentPage = 1;
      let filteredPrograms = [...programs];

      function renderPrograms(list, page) {
        currentPage = page || 1;
        filteredPrograms = list;
        const total = list.length;
        const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
        const start = (currentPage - 1) * PER_PAGE;
        const pageItems = list.slice(start, start + PER_PAGE);
        const container = document.getElementById('programList');
        container.innerHTML = '';

        if (total === 0) {
          container.innerHTML = '<div style="text-align:center;padding:60px 0;color:var(--gray-500);">No programs match your filters. Try adjusting your search.</div>';
          renderPagination(0, 1);
          document.getElementById('resultCount').textContent = 'No programs found';
          return;
        }

        pageItems.forEach(p => {
            const desc = p.desc.length > 120 ? p.desc.substring(0, 120) + "..." : p.desc;
            const card = document.createElement('div');
            card.className = 'program-card' + (p.featured ? ' featured' : '');
            card.onclick = () => openModal(p);

            card.innerHTML = `
                <div class="card-logo">${p.emoji}</div>
                <div class="card-body">
                    <div class="card-top">
                        <div>
                            <div class="card-title">${p.title}</div>
                            <div class="card-institution">${p.institution} · ${p.country}</div>
                        </div>
                        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0;">
                            ${p.featured ? '<span class="featured-badge">FEATURED</span>' : ''}
                            <span class="card-deadline${p.urgent ? ' urgent' : ''}">Deadline: ${p.deadline}</span>
                        </div>
                    </div>
                    <div class="card-desc">${desc}</div>
                    <div class="card-tags">
                        <span class="tag type-${p.type}">${capitalize(p.type)}</span>
                        <span class="tag">⏱ ${p.duration}</span>
                    </div>
                </div>
            `;
            container.appendChild(card);
        });

        document.getElementById('resultCount').textContent =
          `Showing ${start + 1}–${Math.min(start + PER_PAGE, total)} of ${total} programs`;
        renderPagination(totalPages, currentPage);
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }

      function renderPagination(totalPages, active) {
        const pg = document.getElementById('paginationBar');
        pg.innerHTML = '';
        if (totalPages <= 1) return;

        const btn = (label, page, disabled, isActive) => {
          const b = document.createElement('button');
          b.className = 'page-btn' + (isActive ? ' active' : '');
          b.textContent = label;
          b.disabled = disabled;
          if (!disabled && !isActive) b.onclick = () => renderPrograms(filteredPrograms, page);
          return b;
        };

        pg.appendChild(btn('‹', active - 1, active === 1, false));
        let pages = [];
        if (totalPages <= 7) {
          pages = Array.from({length: totalPages}, (_, i) => i + 1);
        } else {
          pages = [1];
          if (active > 3) pages.push('…');
          for (let i = Math.max(2, active - 1); i <= Math.min(totalPages - 1, active + 1); i++) pages.push(i);
          if (active < totalPages - 2) pages.push('…');
          pages.push(totalPages);
        }
        pages.forEach(p => {
          if (p === '…') {
            const s = document.createElement('span');
            s.textContent = '…';
            s.style.cssText = 'padding:0 6px;color:var(--gray-500);line-height:36px;';
            pg.appendChild(s);
          } else {
            pg.appendChild(btn(p, p, false, p === active));
          }
        });
        pg.appendChild(btn('›', active + 1, active === totalPages, false));
      }

      function openModal(p) {
        document.getElementById('modalLogo').textContent = p.emoji;
        document.getElementById('modalTitle').textContent = p.title;
        document.getElementById('modalInstitution').textContent = p.institution + ' · ' + p.country;
        document.getElementById('modalTags').innerHTML = `
          <span class="tag type-${p.type}">${capitalize(p.type)}</span>
          <span class="tag">⏱ ${p.duration}</span>`;
        document.getElementById('modalDesc').textContent = p.desc;
        document.getElementById('modalMeta').innerHTML = `
          <div class="meta-item"><div class="label">Duration</div><div class="value">${p.duration}</div></div>
          <div class="meta-item"><div class="label">Application Deadline</div><div class="value">${p.deadline}</div></div>
          <div class="meta-item"><div class="label">Country</div><div class="value">${p.country}</div></div>`;
        document.getElementById('applyLink').href = '?page=apply_program&program_id=' + p.id;
        document.getElementById('modalOverlay').classList.add('open');
      }

      function closeModal(e) {
        if (e.target === document.getElementById('modalOverlay')) closeModalDirect();
      }
      function closeModalDirect() {
        document.getElementById('modalOverlay').classList.remove('open');
      }

      function applySearch() {
        const q = document.getElementById('searchInput').value.toLowerCase();
        const type = document.getElementById('typeFilter').value;
        const checkedTypes = [...document.querySelectorAll('.filter-group input[type=checkbox][data-filter=type]:checked')].map(c => c.value);
        const countryVal = document.getElementById('countryFilter').value;

        const filtered = programs.filter(p => {
          const matchQ = !q || p.title.toLowerCase().includes(q) || p.country.toLowerCase().includes(q) || p.institution.toLowerCase().includes(q);
          const matchType = (!type || p.type === type) && (checkedTypes.length === 0 || checkedTypes.includes(p.type));
          const matchCountry = !countryVal || p.country === countryVal;
          return matchQ && matchType && matchCountry;
        });
        renderPrograms(filtered, 1);
      }

      function filterCards() { applySearch(); }

      function clearFilters() {
        document.querySelectorAll('.filter-group input[type=checkbox]').forEach(cb => cb.checked = false);
        document.getElementById('searchInput').value = '';
        document.getElementById('typeFilter').value = '';
        document.getElementById('countryFilter').value = '';
        renderPrograms(programs, 1);
      }

      function capitalize(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }

      document.getElementById('searchInput').addEventListener('keydown', e => {
        if (e.key === 'Enter') applySearch();
      });

      document.getElementById('sortSelect').addEventListener('change', function() {
        const val = this.value;
        const sorted = [...filteredPrograms];
        sorted.sort((a, b) => {
          const da = a.endDate ? new Date(a.endDate) : new Date(8640000000000000);
          const db = b.endDate ? new Date(b.endDate) : new Date(8640000000000000);
          return da - db;
        });
        if (val === 'az') sorted.sort((a, b) => a.title.localeCompare(b.title));
        renderPrograms(sorted, 1);
      });

      renderPrograms(programs, 1);
    </script>
    <?php endif; ?>

<?php endif; ?>

</body>
</html>