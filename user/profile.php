<?php
if (!isset($pdo, $userId, $user) || !($pdo instanceof PDO)) {
    header('Location: dashboard.php?page=profile');
    exit;
}

require_once __DIR__ . '/../config/psu_academics.php';
$psuCampuses  = psu_campuses();
$yearLevels   = psu_year_levels();
$facultyRanks = psu_faculty_ranks();
$isStudentAccount = ($user['account_type'] ?? 'Student') !== 'Faculty';
$activeTab = $_GET['tab'] ?? 'profile'; // profile, security, danger

$saved = false;
$errors = [];
$successMsg = '';

/* =========================================================
   HANDLE FORM SUBMISSIONS
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? 'update_profile';

        /* --- 1. UPDATE PROFILE --- */
        if ($action === 'update_profile') {
            $first     = is_string($_POST['first_name'] ?? null) ? trim($_POST['first_name']) : '';
            $middle    = is_string($_POST['middle_name'] ?? null) ? trim($_POST['middle_name']) : '';
            $last      = is_string($_POST['last_name'] ?? null) ? trim($_POST['last_name']) : '';
            $suffix    = is_string($_POST['suffix'] ?? null) ? trim($_POST['suffix']) : '';
            $contact   = is_string($_POST['contact'] ?? null) ? trim($_POST['contact']) : '';
            $birthdate = is_string($_POST['birthdate'] ?? null) ? trim($_POST['birthdate']) : '';
            $sex       = is_string($_POST['sex'] ?? null) ? $_POST['sex'] : '';
            $field = fn(string $k): string => is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : '';
            $institutionSel  = $field('institution');
            $institutionName = $field('institution_name');
            $campus          = $field('campus');
            $department      = $field('department');
            $otherDepartment = $field('other_department');
            $otherProgram    = $field('other_program');
            $studentNumber   = $field('student_number');
            $yearLevel       = $field('year_level');
            $employeeId      = $field('employee_id_number');
            $facultyRank     = $field('faculty_rank');
            $designation     = $field('designation');

            if ($first === '') $errors[] = 'First name is required.';
            if ($last === '')  $errors[] = 'Last name is required.';
            if (mb_strlen($first) > 100 || mb_strlen($middle) > 100 || mb_strlen($last) > 100) {
                $errors[] = 'Names must be 100 characters or fewer.';
            }

            // Affiliation: PSU users choose a campus first, then a program offered there.
            if ($institutionSel === PSU_INSTITUTION) {
                if (!psu_is_valid_campus($campus)) {
                    $errors[] = 'Select your PSU campus.';
                } elseif (!psu_campus_offers($campus, $department)) {
                    $errors[] = 'Select a program offered at ' . $campus . '.';
                }
                $institutionForDb = PSU_INSTITUTION;
                $campusForDb      = $campus;
                $departmentForDb  = $department;
                $programForDb     = $isStudentAccount ? $department : null;
            } elseif ($institutionSel === 'Other') {
                if ($institutionName === '' || mb_strlen($institutionName) > 150) {
                    $errors[] = 'Enter the name of your institution (150 characters max).';
                }
                if ($otherDepartment === '' || mb_strlen($otherDepartment) > 150) {
                    $errors[] = 'Enter your college/department (150 characters max).';
                }
                if ($isStudentAccount && ($otherProgram === '' || mb_strlen($otherProgram) > 150)) {
                    $errors[] = 'Enter your program/course (150 characters max).';
                }
                $institutionForDb = $institutionName;
                $campusForDb      = null;
                $departmentForDb  = $otherDepartment;
                $programForDb     = $isStudentAccount ? $otherProgram : null;
            } else {
                $errors[] = 'Select your institution.';
            }

            // Classification-specific details.
            if ($isStudentAccount) {
                if ($studentNumber === '' || mb_strlen($studentNumber) > 30) {
                    $errors[] = 'Enter your student number (30 characters max).';
                }
                if (!in_array($yearLevel, $yearLevels, true)) {
                    $errors[] = 'Select your year level.';
                }
            } else {
                if ($employeeId === '' || mb_strlen($employeeId) > 30) {
                    $errors[] = 'Enter your employee ID number (30 characters max).';
                }
                if (!in_array($facultyRank, $facultyRanks, true)) {
                    $errors[] = 'Select your rank/position.';
                }
                if (mb_strlen($designation) > 150) {
                    $errors[] = 'Designation must be 150 characters or fewer.';
                }
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
                        contact = ?, birthdate = ?, sex = ?,
                        institution = ?, campus = ?, department = ?, program = ?,
                        student_id_number = ?, year_level = ?,
                        employee_id_number = ?, faculty_rank = ?, designation = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $first, $middle !== '' ? $middle : null, $last, $suffix !== '' ? $suffix : null,
                    $contact !== '' ? $contact : null, $birthdate !== '' ? $birthdate : null,
                    $sex !== '' ? $sex : null,
                    $institutionForDb, $campusForDb, $departmentForDb, $programForDb,
                    $isStudentAccount ? $studentNumber : null,
                    $isStudentAccount ? $yearLevel : null,
                    $isStudentAccount ? null : $employeeId,
                    $isStudentAccount ? null : $facultyRank,
                    (!$isStudentAccount && $designation !== '') ? $designation : null,
                    $userId,
                ]);

                $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $_SESSION['first_name'] = $first;
                $saved = true;
                $successMsg = 'Profile updated successfully.';
            }
        }

        /* --- 2. UPDATE PASSWORD --- */
        if ($action === 'update_password') {
            $activeTab = 'security';
            $current = $_POST['current_password'] ?? '';
            $new     = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if ($current === '' || $new === '' || $confirm === '') {
                $errors[] = 'Please complete all password fields.';
            } else {
                $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $hash = $stmt->fetchColumn();

                if (!is_string($hash) || !password_verify($current, $hash)) {
                    $errors[] = 'Your current password is incorrect.';
                } elseif (strlen($new) < 8) {
                    $errors[] = 'New password must be at least 8 characters long.';
                } elseif ($new !== $confirm) {
                    $errors[] = 'New passwords do not match.';
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                    $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
                    $successMsg = 'Password updated successfully!';
                }
            }
        }

        /* --- 3. DEACTIVATE ACCOUNT --- */
        if ($action === 'deactivate_account') {
            $activeTab = 'danger';
            try {
                $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
                $stmt->execute([$userId]);
                session_destroy();
                header('Location: ../public/login.php?msg=account_deactivated');
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Could not deactivate account. Please contact support.';
            }
        }

        /* --- 4. DELETE ACCOUNT --- */
        if ($action === 'delete_account') {
            $activeTab = 'danger';
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                session_destroy();
                header('Location: ../public/login.php?msg=account_deleted');
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Could not delete account. Please contact support.';
            }
        }
    }
}

/* Affiliation / classification values shown in the form (submitted values win after a failed save). */
$savedInstitution = (string)($user['institution'] ?? '');
$savedIsPsu = $savedInstitution === '' || $savedInstitution === PSU_INSTITUTION;
$savedDepartment = (string)($user['department'] ?? '');
$pf = [
    'institution'      => $savedIsPsu ? PSU_INSTITUTION : 'Other',
    'institution_name' => $savedIsPsu ? '' : $savedInstitution,
    'campus'           => $savedIsPsu ? ((string)($user['campus'] ?? '') ?: (string)psu_infer_campus($savedDepartment)) : '',
    'department'       => $savedIsPsu ? $savedDepartment : '',
    'other_department' => $savedIsPsu ? '' : $savedDepartment,
    'other_program'    => $savedIsPsu ? '' : (string)($user['program'] ?? ''),
    'student_number'   => (string)($user['student_id_number'] ?? ''),
    'year_level'       => (string)($user['year_level'] ?? ''),
    'employee_id_number' => (string)($user['employee_id_number'] ?? ''),
    'faculty_rank'     => (string)($user['faculty_rank'] ?? ''),
    'designation'      => (string)($user['designation'] ?? ''),
];
if ($errors && ($_POST['action'] ?? '') === 'update_profile') {
    foreach (array_keys($pf) as $k) {
        if (is_string($_POST[$k] ?? null)) {
            $pf[$k] = trim($_POST[$k]);
        }
    }
}
$pfIsPsu = $pf['institution'] === PSU_INSTITUTION;

$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$initials = strtoupper(substr($user['first_name'] ?? 'A', 0, 1) . substr($user['last_name'] ?? '', 0, 1));
$profileNotifications = [];
if (isset($pdo, $userId)) {
    $notificationStmt = $pdo->prepare("
        SELECT id, type, title, message, is_read, created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $notificationStmt->execute([$userId]);
    $profileNotifications = $notificationStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<style>
    /* =====================================================
       PROFILE PAGE STYLES
       ===================================================== */
    /* Tabs */
    .profile-tabs {
        display: flex;
        gap: 8px;
        border-bottom: 1px solid var(--border-light);
        margin-bottom: 32px;
        overflow-x: auto;
    }
    .profile-tabs::-webkit-scrollbar { height: 0; }
    
    .profile-tab {
        padding: 14px 24px;
        font-family: var(--font-heading);
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--text-muted);
        border-bottom: 3px solid transparent;
        cursor: pointer;
        transition: var(--transition);
        white-space: nowrap;
        text-decoration: none;
    }
    .profile-tab:hover { color: var(--psu-navy); }
    .profile-tab.active { color: var(--psu-navy); border-bottom-color: var(--psu-gold); }
    .profile-tab.danger-tab { color: #b91c1c; }
    .profile-tab.danger-tab.active { border-bottom-color: #b91c1c; }

    /* Profile Header */
    .profile-header-card { 
        display: flex; 
        align-items: center; 
        gap: 24px; 
        margin-bottom: 40px; 
        padding-bottom: 32px;
        border-bottom: 1px solid var(--border-light);
    }
    
    .profile-avatar-large {
        width: 96px; 
        height: 96px; 
        border-radius: 50%;
        background: var(--psu-gold);
        color: var(--psu-navy); 
        display: flex; 
        align-items: center; 
        justify-content: center;
        font-size: 2.2rem; 
        font-family: var(--font-heading); 
        font-weight: 800;
        box-shadow: 0 4px 12px rgba(228, 177, 52, 0.3);
    }
    
    .profile-header-info h2 { 
        font-family: var(--font-heading);
        font-size: 1.75rem; 
        color: var(--psu-navy); 
        margin-bottom: 6px; 
    }
    .profile-header-info p { 
        color: var(--text-muted); 
        font-size: 0.95rem; 
        display: flex; 
        align-items: center; 
        gap: 8px; 
        font-weight: 500;
    }
    .profile-header-info i { color: var(--psu-gold); }

    /* Form Grid */
    .profile-form-grid { 
        display: grid; 
        grid-template-columns: repeat(2, 1fr); 
        gap: 24px; 
        margin-bottom: 32px; 
    }
    .profile-form-group { 
        display: flex; 
        flex-direction: column; 
        gap: 8px;
    }
    .profile-form-group.full-width { grid-column: span 2; }
    
    .profile-form-group label { 
        font-size: 0.85rem; 
        font-weight: 700; 
        color: var(--psu-navy); 
    }
    
    .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }
    
    .input-wrapper svg {
        position: absolute;
        left: 16px;
        width: 20px;
        height: 20px;
        color: var(--text-light);
        pointer-events: none;
        transition: var(--transition);
    }

    .profile-form-group input, 
    .profile-form-group select {
        width: 100%; 
        padding: 14px 16px 14px 48px; 
        border: 1px solid var(--border-light);
        border-radius: var(--radius-sm); 
        font-family: var(--font-body);
        font-size: 0.95rem; 
        color: var(--psu-navy); 
        background: var(--white);
        transition: var(--transition);
        outline: none;
    }
    
    .profile-form-group input:focus, 
    .profile-form-group select:focus {
        border-color: var(--psu-navy); 
        box-shadow: 0 0 0 3px rgba(11, 26, 48, 0.08);
    }
    
    .input-wrapper:focus-within svg { color: var(--psu-navy); }
    
    .profile-form-group input:disabled { 
        background: var(--bg-main); 
        color: var(--text-light); 
        cursor: not-allowed; 
        border-color: var(--border-light); 
    }

    /* Danger Zone */
    .danger-zone {
        border: 1px solid #fecaca;
        background: #fef2f2;
        border-radius: var(--radius);
        padding: 32px;
        margin-top: 24px;
    }
    .danger-zone h3 { 
        font-family: var(--font-heading);
        color: #b91c1c; 
        font-size: 1.25rem; 
        margin-bottom: 12px; 
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .danger-zone p { 
        color: #991b1b; 
        font-size: 0.95rem; 
        margin-bottom: 24px; 
        line-height: 1.6;
    }
    
    .danger-actions { display: flex; gap: 16px; flex-wrap: wrap; }
    
    .btn-danger-outline {
        padding: 12px 24px; 
        border-radius: var(--radius-sm); 
        font-family: var(--font-heading);
        font-weight: 700; 
        font-size: 0.9rem;
        background: transparent; 
        border: 2px solid #ef4444; 
        color: #ef4444; 
        cursor: pointer; 
        transition: var(--transition);
    }
    .btn-danger-outline:hover { background: #ef4444; color: white; }
    
    .btn-danger-solid {
        padding: 12px 24px; 
        border-radius: var(--radius-sm); 
        font-family: var(--font-heading);
        font-weight: 700; 
        font-size: 0.9rem;
        background: #dc2626; 
        border: 2px solid #dc2626; 
        color: white; 
        cursor: pointer; 
        transition: var(--transition);
    }
    .btn-danger-solid:hover { background: #b91c1c; border-color: #b91c1c; }

    /* Utilities */
    .alert-box { 
        padding: 16px 20px; 
        border-radius: var(--radius-sm); 
        font-size: 0.9rem; 
        font-weight: 600; 
        margin-bottom: 24px; 
        display: flex; 
        gap: 12px; 
        align-items: center;
    }
    .alert-success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    
    .btn-save {
        display: inline-flex; 
        align-items: center; 
        justify-content: center;
        padding: 14px 28px; 
        background: var(--psu-navy); 
        color: var(--white); 
        border: none;
        border-radius: var(--radius-sm); 
        font-family: var(--font-heading);
        font-weight: 700; 
        font-size: 1rem; 
        cursor: pointer; 
        transition: var(--transition);
        gap: 8px;
    }
    .btn-save:hover { 
        background: var(--psu-blue); 
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(11, 26, 48, 0.15);
    }
    
    @media (max-width: 600px) {
        .profile-form-grid { grid-template-columns: 1fr; }
        .profile-form-group.full-width { grid-column: span 1; }
        .profile-header-card { flex-direction: column; text-align: center; }
    }
</style>

<section class="content-card" aria-labelledby="profile-title">
    
    <div class="profile-header-card">
        <div class="profile-avatar-large"><?= h($initials) ?></div>
        <div class="profile-header-info">
            <h2 id="profile-title"><?= h($fullName ?: 'My Account') ?></h2>
            <p><i class="fa-solid fa-envelope"></i> <?= h($user['email'] ?? 'No email') ?></p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="profile-tabs">
        <a href="?page=profile&tab=profile" class="profile-tab <?= $activeTab === 'profile' ? 'active' : '' ?>">Personal Info</a>
        <a href="?page=profile&tab=security" class="profile-tab <?= $activeTab === 'security' ? 'active' : '' ?>">Security</a>
        <a href="?page=profile&tab=notifications" class="profile-tab <?= $activeTab === 'notifications' ? 'active' : '' ?>">Notifications</a>
        <a href="?page=profile&tab=danger" class="profile-tab danger-tab <?= $activeTab === 'danger' ? 'active' : '' ?>">Danger Zone</a>
    </div>

    <?php if ($successMsg): ?>
        <div class="alert-box alert-success"><i class="fa-solid fa-circle-check"></i> <?= h($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert-box alert-error">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div><ul style="margin:0; padding-left:16px;"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
        </div>
    <?php endif; ?>

    <!-- ==========================================
         TAB 3: NOTIFICATIONS
         ========================================== -->
    <?php if ($activeTab === 'notifications'): ?>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <?php if (empty($profileNotifications)): ?>
                <p style="color:var(--text-muted);">No notifications yet.</p>
            <?php else: ?>
                <?php foreach ($profileNotifications as $notification): ?>
                    <div style="padding:16px;border:1px solid var(--border-light);border-radius:12px;background:<?= empty($notification['is_read']) ? '#fffbeb' : 'var(--white)' ?>;">
                        <strong><?= h($notification['title']) ?></strong>
                        <p style="margin:6px 0;color:var(--text-muted);"><?= h($notification['message']) ?></p>
                        <small style="color:var(--text-muted);"><?= h(date('M d, Y g:i A', strtotime($notification['created_at']))) ?></small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ==========================================
         TAB 1: PERSONAL INFO
         ========================================== -->
    <?php if ($activeTab === 'profile'): ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="action" value="update_profile">
        
        <div class="profile-form-grid">
            <div class="profile-form-group">
                <label>First Name *</label>
                <div class="input-wrapper">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <input type="text" name="first_name" maxlength="100" value="<?= h($user['first_name'] ?? '') ?>" required>
                </div>
            </div>
            <div class="profile-form-group">
                <label>Middle Name</label>
                <div class="input-wrapper">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <input type="text" name="middle_name" maxlength="100" value="<?= h($user['middle_name'] ?? '') ?>">
                </div>
            </div>
            <div class="profile-form-group">
                <label>Last Name *</label>
                <div class="input-wrapper">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <input type="text" name="last_name" maxlength="100" value="<?= h($user['last_name'] ?? '') ?>" required>
                </div>
            </div>
            <div class="profile-form-group">
                <label>Suffix</label>
                <div class="input-wrapper">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9h16M4 15h16M10 3L8 21M16 3l-2 18"/></svg>
                    <input type="text" name="suffix" maxlength="20" value="<?= h($user['suffix'] ?? '') ?>" placeholder="e.g. Jr., III">
                </div>
            </div>
            <div class="profile-form-group">
                <label>Email Address (Locked)</label>
                <div class="input-wrapper">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 6L2 7"/></svg>
                    <input type="email" value="<?= h($user['email'] ?? '') ?>" disabled title="Email cannot be changed">
                </div>
            </div>
            <?php $bldg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6"/></svg>'; ?>
            <?php $idIc = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M15 9h3M15 13h3M6 17c.5-2 2-3 3-3s2.5 1 3 3"/></svg>'; ?>
            <div class="profile-form-group">
                <label>Classification (Locked)</label>
                <div class="input-wrapper">
                    <?= $idIc ?>
                    <input type="text" value="<?= $isStudentAccount ? 'Student' : 'Faculty / Employee' ?>" disabled title="Contact the administrator to change your classification">
                </div>
            </div>
            <div class="profile-form-group">
                <label>Institution *</label>
                <div class="input-wrapper">
                    <?= $bldg ?>
                    <select name="institution" id="pf-institution" required>
                        <option value="<?= h(PSU_INSTITUTION) ?>" <?= $pfIsPsu ? 'selected' : '' ?>><?= h(PSU_INSTITUTION) ?></option>
                        <option value="Other" <?= $pfIsPsu ? '' : 'selected' ?>>Other institution</option>
                    </select>
                </div>
            </div>

            <!-- PSU: campus first, then programs offered on that campus -->
            <div class="profile-form-group" data-pf-group="psu">
                <label>Campus *</label>
                <div class="input-wrapper">
                    <?= $bldg ?>
                    <select name="campus" id="pf-campus" required>
                        <option value="">Select campus</option>
                        <?php foreach (array_keys($psuCampuses) as $campusName): ?>
                            <option value="<?= h($campusName) ?>" <?= $pf['campus'] === $campusName ? 'selected' : '' ?>><?= h($campusName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="profile-form-group" data-pf-group="psu">
                <label><?= $isStudentAccount ? 'Program *' : 'Department / Program *' ?></label>
                <div class="input-wrapper">
                    <?= $bldg ?>
                    <select name="department" id="pf-department" required data-selected="<?= h($pf['department']) ?>">
                        <option value="">Select a campus first</option>
                    </select>
                </div>
            </div>

            <!-- Non-PSU institution -->
            <div class="profile-form-group" data-pf-group="other">
                <label>Name of Institution *</label>
                <div class="input-wrapper">
                    <?= $bldg ?>
                    <input type="text" name="institution_name" maxlength="150" value="<?= h($pf['institution_name']) ?>" required>
                </div>
            </div>
            <div class="profile-form-group" data-pf-group="other">
                <label>College / Department *</label>
                <div class="input-wrapper">
                    <?= $bldg ?>
                    <input type="text" name="other_department" maxlength="150" value="<?= h($pf['other_department']) ?>" required>
                </div>
            </div>
            <?php if ($isStudentAccount): ?>
            <div class="profile-form-group" data-pf-group="other">
                <label>Program / Course *</label>
                <div class="input-wrapper">
                    <?= $bldg ?>
                    <input type="text" name="other_program" maxlength="150" value="<?= h($pf['other_program']) ?>" required>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($isStudentAccount): ?>
            <div class="profile-form-group">
                <label>Student Number *</label>
                <div class="input-wrapper">
                    <?= $idIc ?>
                    <input type="text" name="student_number" maxlength="30" value="<?= h($pf['student_number']) ?>" placeholder="e.g. 22-BGU-1283" required>
                </div>
            </div>
            <div class="profile-form-group">
                <label>Year Level *</label>
                <div class="input-wrapper">
                    <?= $idIc ?>
                    <select name="year_level" required>
                        <option value="">Select year level</option>
                        <?php foreach ($yearLevels as $yl): ?>
                            <option value="<?= h($yl) ?>" <?= $pf['year_level'] === $yl ? 'selected' : '' ?>><?= h($yl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php else: ?>
            <div class="profile-form-group">
                <label>Employee ID Number *</label>
                <div class="input-wrapper">
                    <?= $idIc ?>
                    <input type="text" name="employee_id_number" maxlength="30" value="<?= h($pf['employee_id_number']) ?>" required>
                </div>
            </div>
            <div class="profile-form-group">
                <label>Rank / Position *</label>
                <div class="input-wrapper">
                    <?= $idIc ?>
                    <select name="faculty_rank" required>
                        <option value="">Select rank/position</option>
                        <?php foreach ($facultyRanks as $rank): ?>
                            <option value="<?= h($rank) ?>" <?= $pf['faculty_rank'] === $rank ? 'selected' : '' ?>><?= h($rank) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="profile-form-group">
                <label>Designation</label>
                <div class="input-wrapper">
                    <?= $idIc ?>
                    <input type="text" name="designation" maxlength="150" value="<?= h($pf['designation']) ?>" placeholder="e.g. Department Chair">
                </div>
            </div>
            <?php endif; ?>
            <div class="profile-form-group">
                <label>Contact Number</label>
                <div class="input-wrapper">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <input type="text" name="contact" maxlength="20" value="<?= h($user['contact'] ?? '') ?>">
                </div>
            </div>
            <div class="profile-form-group">
                <label>Birthdate</label>
                <div class="input-wrapper">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    <input type="date" name="birthdate" value="<?= h($user['birthdate'] ?? '') ?>">
                </div>
            </div>
            <div class="profile-form-group">
                <label>Sex</label>
                <div class="input-wrapper">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="10" r="4"/><path d="M12 14v7M8 18h8M19 5l-3 3M17 3l2 2"/></svg>
                    <select name="sex">
                        <option value="">Prefer not to specify</option>
                        <option value="Male" <?= ($user['sex'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($user['sex'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Prefer not to say" <?= ($user['sex'] ?? '') === 'Prefer not to say' ? 'selected' : '' ?>>Prefer not to say</option>
                    </select>
                </div>
            </div>
        </div>
        <button type="submit" class="btn-save">
            <i class="fa-solid fa-floppy-disk"></i> Save Changes
        </button>
    </form>
    <script>
    (function () {
        const CAMPUSES = <?= json_encode($psuCampuses, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const PSU_NAME = <?= json_encode(PSU_INSTITUTION) ?>;
        const inst = document.getElementById('pf-institution');
        const campus = document.getElementById('pf-campus');
        const dept = document.getElementById('pf-department');

        function fillPrograms() {
            const keep = dept.value || dept.dataset.selected || '';
            dept.innerHTML = '';
            dept.add(new Option(campus.value ? 'Select program' : 'Select a campus first', ''));
            Object.entries(CAMPUSES[campus.value] || {}).forEach(([name, label]) => {
                const opt = new Option(name + ' (' + label + ')', name);
                if (name === keep) opt.selected = true;
                dept.add(opt);
            });
            dept.dataset.selected = '';
        }

        // Hidden groups are disabled so their required fields are skipped and not submitted.
        function refresh() {
            const isPsu = inst.value === PSU_NAME;
            document.querySelectorAll('[data-pf-group]').forEach(g => {
                const show = (g.dataset.pfGroup === 'psu') === isPsu;
                g.style.display = show ? '' : 'none';
                g.querySelectorAll('input, select').forEach(f => { f.disabled = !show; });
            });
        }

        campus.addEventListener('change', () => { dept.value = ''; fillPrograms(); });
        inst.addEventListener('change', refresh);
        fillPrograms();
        refresh();
    })();
    </script>
    <?php endif; ?>

    <!-- ==========================================
         TAB 2: SECURITY (PASSWORD)
         ========================================== -->
    <?php if ($activeTab === 'security'): ?>
    <form method="post" style="max-width: 500px;">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="action" value="update_password">
        
        <div class="profile-form-group" style="margin-bottom: 24px;">
            <label>Current Password</label>
            <div class="input-wrapper">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <input type="password" name="current_password" required placeholder="Enter current password">
            </div>
        </div>
        <div class="profile-form-group" style="margin-bottom: 24px;">
            <label>New Password (min 8 chars)</label>
            <div class="input-wrapper">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <input type="password" name="new_password" minlength="8" required placeholder="At least 8 characters">
            </div>
        </div>
        <div class="profile-form-group" style="margin-bottom: 32px;">
            <label>Confirm New Password</label>
            <div class="input-wrapper">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <input type="password" name="confirm_password" minlength="8" required placeholder="Re-type new password">
            </div>
        </div>
        <button type="submit" class="btn-save">
            <i class="fa-solid fa-shield-halved"></i> Update Password
        </button>
    </form>
    <?php endif; ?>

    <!-- ==========================================
         TAB 3: DANGER ZONE
         ========================================== -->
    <?php if ($activeTab === 'danger'): ?>
        <div class="danger-zone">
            <h3><i class="fa-solid fa-triangle-exclamation"></i> Danger Zone</h3>
            <p>Once you delete your account, there is no going back. All of your applications, documents, and data will be permanently erased. If you just want to take a break, you can deactivate your account instead.</p>
            
            <div class="danger-actions">
                <!-- Deactivate -->
                <form method="post" onsubmit="return confirm('Are you sure you want to deactivate your account? You can contact support to reactivate it later.');">
                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="deactivate_account">
                    <button type="submit" class="btn-danger-outline">Deactivate Account</button>
                </form>

                <!-- Delete -->
                <form method="post" onsubmit="return confirm('WARNING: This will permanently delete your account and all data. This cannot be undone. Are you absolutely sure?');">
                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="delete_account">
                    <button type="submit" class="btn-danger-solid">Permanently Delete Account</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

</section>
