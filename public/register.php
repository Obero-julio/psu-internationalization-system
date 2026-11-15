<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$pdo = db();

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* ==================================================================
 * STATE VARIABLES
 * ================================================================== */
$reg_errors      = [];
$reg_success     = false;

$reg_first_name  = '';
$reg_middle_name = '';
$reg_last_name   = '';
$reg_suffix      = '';
$reg_email       = '';
$reg_sex         = '';
$reg_dob         = '';
$reg_student_id  = '';

$reg_department  = '';
$reg_program     = '';
$reg_year_level  = '';
$reg_institution = 'Pangasinan State University';
$reg_term        = '';

$reg_step = 1; 

/* ==================================================================
 * POST HANDLING
 * ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Step 1
    $reg_first_name  = trim($_POST['first_name'] ?? '');
    $reg_middle_name = trim($_POST['middle_name'] ?? '');
    $reg_last_name   = trim($_POST['last_name'] ?? '');
    $reg_suffix      = trim($_POST['suffix'] ?? '');
    $reg_sex         = trim($_POST['sex'] ?? '');
    $reg_dob         = trim($_POST['date_of_birth'] ?? '');
    $reg_student_id  = trim($_POST['student_id_number'] ?? '');

    // Step 2
    $reg_email       = trim($_POST['email'] ?? '');

    // Step 3
    $reg_department  = trim($_POST['department'] ?? '');
    $reg_program     = trim($_POST['program'] ?? '');
    $reg_year_level  = trim($_POST['year_level'] ?? '');
    $reg_institution = trim($_POST['institution'] ?? '');
    $reg_term        = trim($_POST['term'] ?? '');

    // Step 4
    $password        = $_POST['password'] ?? '';
    $confirm         = $_POST['confirm_password'] ?? '';
    $agree_terms     = isset($_POST['agree_terms']);

    $role = 'applicant';
    
    // FIX: Matches DB Enum exactly
    $allowedSex = ['Male', 'Female', 'Prefer not to say'];

    // --- STEP 1 VALIDATION ---
    if ($reg_first_name === '' || $reg_last_name === '' || $reg_student_id === '') {
        $reg_errors[] = 'Please fill in all required personal fields.';
        $reg_step = 1;
    }
    if ($reg_sex === '' || !in_array($reg_sex, $allowedSex, true)) {
        $reg_errors[] = 'Please select your sex.';
        $reg_step = 1;
    }

    $dobForDb = null;
    if ($reg_dob !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $reg_dob);
        $valid = $d && $d->format('Y-m-d') === $reg_dob;
        if (!$valid) {
            $reg_errors[] = 'Please enter a valid date of birth.';
            $reg_step = 1;
        } elseif ($d > new DateTime()) {
            $reg_errors[] = 'Date of birth cannot be in the future.';
            $reg_step = 1;
        } else {
            $dobForDb = $reg_dob;
        }
    }

    // --- STEP 2 VALIDATION ---
    if ($reg_email === '') {
        $reg_errors[] = 'Please enter your email address.';
        if ($reg_step === 1) $reg_step = 2;
    } elseif (!filter_var($reg_email, FILTER_VALIDATE_EMAIL)) {
        $reg_errors[] = 'Invalid email address.';
        if ($reg_step === 1) $reg_step = 2;
    }

    // --- STEP 3 VALIDATION ---
    if ($reg_department === '' || $reg_program === '' || $reg_year_level === '') {
        $reg_errors[] = 'Please complete all academic details.';
        if ($reg_step <= 3) $reg_step = 3;
    }

    // --- STEP 4 VALIDATION ---
    if ($password === '' || $confirm === '') {
        $reg_errors[] = 'Please create a password.';
        if ($reg_step < 4) $reg_step = 4;
    } elseif ($password !== $confirm) {
        $reg_errors[] = 'Passwords do not match.';
        if ($reg_step < 4) $reg_step = 4;
    }
    if (!$agree_terms) {
        $reg_errors[] = 'You must agree to the Terms and Conditions.';
        if ($reg_step < 4) $reg_step = 4;
    }

    // --- SAVE TO DATABASE ---
    if (!$reg_errors) {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $check->execute([':email' => $reg_email]);

        if ($check->fetch()) {
            $reg_errors[] = 'This email address is already registered.';
            $reg_step = 2;
        } else {
            try {
                // FIX: Changed 'date_of_birth' to 'birthdate'
                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        first_name, middle_name, last_name, suffix, email,
                        password_hash, sex, birthdate, student_id_number, user_role, status
                    ) VALUES (
                        :first_name, :middle_name, :last_name, :suffix, :email,
                        :password_hash, :sex, :birthdate, :student_id_number, :user_role, :status
                    )
                ");

                $result = $stmt->execute([
                    ':first_name'         => $reg_first_name,
                    ':middle_name'        => $reg_middle_name !== '' ? $reg_middle_name : null,
                    ':last_name'          => $reg_last_name,
                    ':suffix'             => $reg_suffix !== '' ? $reg_suffix : null,
                    ':email'              => $reg_email,
                    ':password_hash'      => password_hash($password, PASSWORD_DEFAULT),
                    ':sex'                => $reg_sex,
                    ':birthdate'          => $dobForDb, // FIX: Changed key
                    ':student_id_number'  => $reg_student_id,
                    ':user_role'          => $role,
                    ':status'             => 'active', // FIX: Lowercase enum
                ]);

                if ($result) {
                    $reg_success = true;
                    header('refresh:2;url=user_login.php');
                } else {
                    $reg_errors[] = 'Registration failed. Please try again.';
                }

            } catch (PDOException $ex) {
                // FIX: Show the REAL error instead of hiding it
                $reg_errors[] = 'Database error: ' . $ex->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — PSU Internalization</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../asset/css/register.css" rel="stylesheet">

</head>
<body>

<div class="page">

    <aside class="hero">
        <div class="orb o1"></div>
        <div class="orb o2"></div>

        <div class="hero-top">
            <a href="website.php" class="brand"><span class="badge">PSU</span> PSU <span class="gold">Internalization</span></a>
        </div>

        <div class="hero-body">
            <div class="eyebrow"><i></i> Applications are open</div>
            <h2>Your journey to the <em>world</em> starts here.</h2>
            <p class="lead">Create your account to explore international exchange, study abroad, and global learning programs offered by Pangasinan State University.</p>

            <ul class="features">
                <li>
                    <div class="ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/></svg></div>
                    <div><strong>Browse global programs</strong><small>Find opportunities abroad that fit you</small></div>
                </li>
                <li>
                    <div class="ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 15l2 2 4-4"/></svg></div>
                    <div><strong>Apply in one place</strong><small>Submit and manage your applications online</small></div>
                </li>
                <li>
                    <div class="ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg></div>
                    <div><strong>Track your status</strong><small>Stay updated at every step of the process</small></div>
                </li>
            </ul>
        </div>

        <div class="hero-foot">&copy; <?= date('Y') ?> Pangasinan State University &middot; Internalization Office</div>
    </aside>

    <main class="form-side">
        <div class="register-card">

            <div class="register-header">
                <h1>Create Account</h1>
                <p>Join PSU Internalization to apply for global programs</p>
            </div>

            <!-- Stepper -->
            <div class="stepper" id="stepper">
                <div class="st" data-s="1"><div class="dot"><span>1</span></div><div class="lbl">Personal</div></div>
                <div class="line"><i></i></div>
                <div class="st" data-s="2"><div class="dot"><span>2</span></div><div class="lbl">Account</div></div>
                <div class="line"><i></i></div>
                <div class="st" data-s="3"><div class="dot"><span>3</span></div><div class="lbl">Academic</div></div>
                <div class="line"><i></i></div>
                <div class="st" data-s="4"><div class="dot"><span>4</span></div><div class="lbl">Security</div></div>
            </div>

            <?php if (!empty($reg_errors)): ?>
                <div class="alert-error">
                    <?php foreach ($reg_errors as $error): ?>
                        &bull; <?= e($error) ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($reg_success): ?>
                <div class="alert-success">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    Registration successful! Redirecting...
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" id="registerForm" novalidate>

                <!-- STEP 1: PERSONAL INFO -->
                <div class="form-step <?= ($reg_step === 1) ? 'active' : '' ?>" id="step-1">
                    <div class="step-title">
                        <div class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                        <div><h3>Tell us about yourself</h3><p>Your personal details</p></div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">First Name *</label>
                            <div class="input-wrap">
                                <svg class="lead-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <input class="form-input" type="text" name="first_name" placeholder="Juan" required value="<?= e($reg_first_name) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name *</label>
                            <div class="input-wrap">
                                <svg class="lead-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <input class="form-input" type="text" name="last_name" placeholder="Dela Cruz" required value="<?= e($reg_last_name) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Middle Name <span class="opt">(Optional)</span></label>
                            <div class="input-wrap">
                                <svg class="lead-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <input class="form-input" type="text" name="middle_name" placeholder="Santos" value="<?= e($reg_middle_name) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Suffix <span class="opt">(Optional)</span></label>
                            <div class="input-wrap">
                                <svg class="lead-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9h16M4 15h16M10 3L8 21M16 3l-2 18"/></svg>
                                <input class="form-input" type="text" name="suffix" placeholder="Jr., III" value="<?= e($reg_suffix) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Sex *</label>
                        <div class="radio-group">
                            <!-- FIX: Capitalized values to match DB -->
                            <label class="radio-pill"><input type="radio" name="sex" value="Male" <?= $reg_sex === 'Male' ? 'checked' : '' ?> required><span>Male</span></label>
                            <label class="radio-pill"><input type="radio" name="sex" value="Female" <?= $reg_sex === 'Female' ? 'checked' : '' ?>><span>Female</span></label>
                            <label class="radio-pill"><input type="radio" name="sex" value="Prefer not to say" <?= $reg_sex === 'Prefer not to say' ? 'checked' : '' ?>><span>Prefer not to say</span></label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Date of Birth *</label>
                            <input class="form-input no-ic" type="date" name="date_of_birth" required value="<?= e($reg_dob) ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Student ID *</label>
                            <div class="input-wrap">
                                <svg class="lead-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M15 9h3M15 13h3M6 17c.5-2 2-3 3-3s2.5 1 3 3"/></svg>
                                <input class="form-input" type="text" name="student_id_number" placeholder="21-00123" required value="<?= e($reg_student_id) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-actions" style="justify-content: flex-end;">
                        <button type="button" class="btn-primary" onclick="nextStep(2, 1)">Next &rarr;</button>
                    </div>
                </div>

                <!-- STEP 2: ACCOUNT INFO -->
                <div class="form-step <?= ($reg_step === 2) ? 'active' : '' ?>" id="step-2">
                    <div class="step-title">
                        <div class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 6L2 7"/></svg></div>
                        <div><h3>Your account email</h3><p>We'll use this to sign you in and send updates</p></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">PSU Email Address *</label>
                        <div class="input-wrap">
                            <svg class="lead-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 6L2 7"/></svg>
                            <input class="form-input" type="email" name="email" placeholder="juan.delacruz@psu.edu.ph" required value="<?= e($reg_email) ?>">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="nextStep(1)">&larr; Back</button>
                        <button type="button" class="btn-primary" onclick="nextStep(3, 2)">Next &rarr;</button>
                    </div>
                </div>

                <!-- STEP 3: ACADEMIC DETAILS -->
                <div class="form-step <?= ($reg_step === 3) ? 'active' : '' ?>" id="step-3">
                    <div class="step-title">
                        <div class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10L12 4 2 10l10 6 10-6z"/><path d="M6 12.5V17c0 1 3 3 6 3s6-2 6-3v-4.5"/></svg></div>
                        <div><h3>Academic details</h3><p>Where and what you study</p></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Institution *</label>
                        <div class="input-wrap">
                            <svg class="lead-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6"/></svg>
                            <select class="form-input" name="institution" required>
                                <option value="Pangasinan State University" <?= $reg_institution === 'Pangasinan State University' ? 'selected' : '' ?>>Pangasinan State University</option>
                                <option value="Other" <?= $reg_institution === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Department *</label>
                            <select class="form-input no-ic" name="department" required>
                                <option value="">Select</option>
                                <option value="College of Engineering" <?= $reg_department === 'College of Engineering' ? 'selected' : '' ?>>Engineering</option>
                                <option value="College of Information Technology" <?= $reg_department === 'College of Information Technology' ? 'selected' : '' ?>>IT</option>
                                <option value="College of Business" <?= $reg_department === 'College of Business' ? 'selected' : '' ?>>Business</option>
                                <option value="College of Education" <?= $reg_department === 'College of Education' ? 'selected' : '' ?>>Education</option>
                                <option value="College of Arts and Sciences" <?= $reg_department === 'College of Arts and Sciences' ? 'selected' : '' ?>>Arts & Sciences</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Program *</label>
                            <select class="form-input no-ic" name="program" required>
                                <option value="">Select</option>
                                <option value="BS Information Technology" <?= $reg_program === 'BS Information Technology' ? 'selected' : '' ?>>BS IT</option>
                                <option value="BS Computer Science" <?= $reg_program === 'BS Computer Science' ? 'selected' : '' ?>>BS CS</option>
                                <option value="BS Civil Engineering" <?= $reg_program === 'BS Civil Engineering' ? 'selected' : '' ?>>BS CE</option>
                                <option value="BS Accountancy" <?= $reg_program === 'BS Accountancy' ? 'selected' : '' ?>>BS Accountancy</option>
                                <option value="Bachelor of Elementary Education" <?= $reg_program === 'Bachelor of Elementary Education' ? 'selected' : '' ?>>BEEd</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Year Level *</label>
                            <select class="form-input no-ic" name="year_level" required>
                                <option value="">Select</option>
                                <option value="1st Year" <?= $reg_year_level === '1st Year' ? 'selected' : '' ?>>1st Year</option>
                                <option value="2nd Year" <?= $reg_year_level === '2nd Year' ? 'selected' : '' ?>>2nd Year</option>
                                <option value="3rd Year" <?= $reg_year_level === '3rd Year' ? 'selected' : '' ?>>3rd Year</option>
                                <option value="4th Year" <?= $reg_year_level === '4th Year' ? 'selected' : '' ?>>4th Year</option>
                                <option value="5th Year" <?= $reg_year_level === '5th Year' ? 'selected' : '' ?>>5th Year</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Term <span class="opt">(Optional)</span></label>
                            <input class="form-input no-ic" type="text" name="term" placeholder="1st Sem 2024" value="<?= e($reg_term) ?>">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="nextStep(2)">&larr; Back</button>
                        <button type="button" class="btn-primary" onclick="nextStep(4, 3)">Next &rarr;</button>
                    </div>
                </div>

                <!-- STEP 4: SECURITY -->
                <div class="form-step <?= ($reg_step === 4) ? 'active' : '' ?>" id="step-4">
                    <div class="step-title">
                        <div class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg></div>
                        <div><h3>Secure your account</h3><p>Choose a strong password</p></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <div class="input-wrap">
                            <svg class="lead-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input class="form-input" type="password" name="password" id="pw" placeholder="Create a password" required autocomplete="new-password" style="padding-right:46px;">
                            <button type="button" class="toggle-pw" onclick="togglePw()" aria-label="Show or hide password">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <div class="strength" id="strength"><i></i><i></i><i></i><i></i></div>
                        <div class="strength-text" id="strengthText"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm Password *</label>
                        <div class="input-wrap">
                            <svg class="lead-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input class="form-input" type="password" name="confirm_password" id="pw2" placeholder="Confirm password" required autocomplete="new-password">
                        </div>
                    </div>

                    <label class="checkbox-group">
                        <input type="checkbox" name="agree_terms" required>
                        <span>I agree to the <a href="#">Terms and Conditions</a> and <a href="#">Privacy Policy</a>.</span>
                    </label>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="nextStep(3)">&larr; Back</button>
                        <button type="submit" class="btn-primary" onclick="return validateFinal()">Create Account</button>
                    </div>
                </div>

            </form>

            <div class="login-link">
                Already have an account? <a href="user_login.php">Sign In</a>
            </div>

        </div>
    </main>
</div>

<script>
    const stepNodes = document.querySelectorAll('#stepper .st');
    const lineNodes = document.querySelectorAll('#stepper .line');

    function updateStepper(step) {
        stepNodes.forEach(node => {
            const s = Number(node.dataset.s);
            node.classList.toggle('active', s === step);
            node.classList.toggle('done', s < step);
        });
        lineNodes.forEach((line, i) => line.classList.toggle('fill', i + 1 < step));
    }

    function validateStep(n) {
        const inputs = document.getElementById('step-' + n).querySelectorAll('input[required], select[required]');
        for (const input of inputs) {
            if (!input.checkValidity()) { input.reportValidity(); return false; }
        }
        return true;
    }

    function nextStep(step, validateFrom = null) {
        if (validateFrom !== null && !validateStep(validateFrom)) return;
        document.querySelectorAll('.form-step').forEach(el => el.classList.remove('active'));
        document.getElementById('step-' + step).classList.add('active');
        updateStepper(step);
    }

    function validateFinal() {
        for (let n = 1; n <= 4; n++) {
            if (!validateStep(n)) { nextStep(n); validateStep(n); return false; }
        }
        return true;
    }

    function togglePw() {
        const pw = document.getElementById('pw');
        const pw2 = document.getElementById('pw2');
        const type = pw.type === 'password' ? 'text' : 'password';
        pw.type = type; pw2.type = type;
    }

    document.getElementById('pw').addEventListener('input', function () {
        const v = this.value;
        let score = 0;
        if (v.length >= 8) score++;
        if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
        if (/\d/.test(v)) score++;
        if (/[^A-Za-z0-9]/.test(v)) score++;
        const colors = ['#ef4444', '#f59e0b', '#84cc16', '#10b981'];
        const labels = ['Weak', 'Fair', 'Good', 'Strong'];
        document.querySelectorAll('#strength i').forEach((bar, i) => {
            bar.style.background = (v && i < score) ? colors[score - 1] : '';
        });
        document.getElementById('strengthText').textContent = v ? labels[Math.max(score - 1, 0)] : '';
    });

    document.addEventListener('DOMContentLoaded', () => updateStepper(<?= (int)$reg_step ?>));
</script>

</body>
</html>