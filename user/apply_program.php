<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = db();
$user_id = $_SESSION['user_id'] ?? null;

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* 
 * AVAILABLE PROGRAMS 
 * We need to JOIN partners and countries to get the actual names.
 */
$stmt = $pdo->query("
    SELECT 
        p.*, 
        pr.institution_name AS partner_institution, 
        c.country_name AS country
    FROM programs p
    LEFT JOIN partners pr ON pr.id = p.partner_id
    LEFT JOIN countries c ON c.id = p.country_id
    WHERE p.start_date >= CURDATE() 
    ORDER BY p.created_at DESC
");
$programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $program_id = (int)($_POST['program_id'] ?? 0);
    $reason     = trim($_POST['reason'] ?? '');

    if (!$program_id) {
        $errors[] = 'Please select a program.';
    }
    if (!$reason) {
        $errors[] = 'Statement of purpose is required.';
    } elseif (strlen($reason) > 500) {
        $errors[] = 'Statement of purpose cannot exceed 500 characters.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO applications
            (applicant_id, program_id, reason, status, created_at)
            VALUES (?, ?, ?, 'Pending', NOW())
        ");
        $stmt->execute([$user_id, $program_id, $reason]);
        $success = 'Application submitted successfully. We will notify you once it has been reviewed.';
    }
}
?>

<link rel="stylesheet" href="../asset/css/apply.css">

<div class="content-card">

    <div class="page-header">
        <div class="page-header-icon">
            <i class="fa-solid fa-plane-departure"></i>
        </div>
        <div>
            <h2>Apply for a Mobility Program</h2>
            <p>Select an available program from the list below and submit your application.</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i>
            <span><?= e($success) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                <?php foreach ($errors as $error): ?>
                    <div><?= e($error) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- APPLICATION FORM -->
    <form method="POST" class="apply-form">

        <div class="form-group">
            <label for="programSelect">Select Program <span class="required">*</span></label>
            <select name="program_id" id="programSelect" required>
                <option value="" disabled selected>Choose a Program...</option>
                <?php foreach ($programs as $program): ?>
                    <option
                        value="<?= $program['id'] ?>"
                        data-country="<?= e($program['country'] ?? 'N/A') ?>"
                        data-partner="<?= e($program['partner_institution'] ?? 'N/A') ?>"
                        data-start="<?= e($program['start_date']) ?>"
                        data-end="<?= e($program['end_date']) ?>"
                    >
                        <?= e($program['program_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($programs)): ?>
                <small class="form-hint" style="color: var(--red);">No open programs available right now. Check back later.</small>
            <?php endif; ?>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Partner Institution</label>
                <div class="input-icon-wrapper">
                    <i class="fa-solid fa-building"></i>
                    <input type="text" id="partner" placeholder="Auto-filled based on selection" readonly>
                </div>
            </div>

            <div class="form-group">
                <label>Country</label>
                <div class="input-icon-wrapper">
                    <i class="fa-solid fa-location-dot"></i>
                    <input type="text" id="country" placeholder="Auto-filled based on selection" readonly>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Start Date</label>
                <div class="input-icon-wrapper">
                    <i class="fa-regular fa-calendar"></i>
                    <input type="text" id="startDate" placeholder="Auto-filled" readonly>
                </div>
            </div>

            <div class="form-group">
                <label>End Date</label>
                <div class="input-icon-wrapper">
                    <i class="fa-regular fa-calendar-check"></i>
                    <input type="text" id="endDate" placeholder="Auto-filled" readonly>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="reason">Statement of Purpose <span class="required">*</span></label>
            <textarea
                name="reason"
                id="reason"
                rows="5"
                maxlength="500"
                placeholder="Explain why you want to join this mobility program, how it aligns with your studies, and what you hope to achieve..."
                required
            ></textarea>
            <div class="char-count"><span id="charCount">0</span> / 500 characters</div>
        </div>

        <div class="form-footer">
            <button type="submit" class="btn-primary" <?= empty($programs) ? 'disabled' : '' ?>>
                <i class="fa-solid fa-paper-plane"></i> Submit Application
            </button>
        </div>

    </form>

</div>

<script>
const select = document.getElementById('programSelect');
const partnerInput = document.getElementById('partner');
const countryInput = document.getElementById('country');
const startDateInput = document.getElementById('startDate');
const endDateInput = document.getElementById('endDate');
const reasonInput = document.getElementById('reason');
const charCount = document.getElementById('charCount');

// Format dates beautifully (e.g., "Jun 1, 2024")
function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

select.addEventListener('change', function () {
    const option = this.options[this.selectedIndex];
    
    partnerInput.value = option.dataset.partner || '';
    countryInput.value = option.dataset.country || '';
    startDateInput.value = formatDate(option.dataset.start);
    endDateInput.value = formatDate(option.dataset.end);
});

// Character counter for the textarea
reasonInput.addEventListener('input', function() {
    charCount.textContent = this.value.length;
    if (this.value.length >= 500) {
        charCount.style.color = 'var(--red)';
    } else {
        charCount.style.color = 'var(--gray-500)';
    }
});
</script>