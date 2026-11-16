<?php
$user_id = $userId;
$message = '';
$msgType = '';

$csrfToken = $_SESSION['csrf_token'];

/* Allowed MIME => extension */
$allowedMimes = [
    'application/pdf'  => 'pdf',
    'image/jpeg'       => 'jpg',
    'image/png'        => 'png',
];
const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB

/* ---------- Get user's program (assumes users.program_id exists) ---------- */
$stmt = $pdo->prepare("SELECT program_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$userProgramId = (int)($stmt->fetchColumn() ?: 0);

/* ---------- Handle Upload ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    if (!checkCsrf()) {
        $message = 'Your session expired. Please refresh and try again.';
        $msgType = 'error';
    } else {
        $req_id = (int)($_POST['requirement_id'] ?? 0);
        $file   = $_FILES['document'];

        // Validate requirement belongs to user's program
        $check = $pdo->prepare("
            SELECT pr.id
            FROM program_requirements pr
            WHERE pr.id = ? AND pr.program_id = ?
        ");
        $check->execute([$req_id, $userProgramId]);

        if (!$check->fetchColumn()) {
            $message = 'Invalid requirement selected.';
            $msgType = 'error';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $message = match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds the upload limit.',
                UPLOAD_ERR_NO_FILE => 'Please select a file.',
                default => 'Upload failed. Please try again.',
            };
            $msgType = 'error';
        } elseif ($file['size'] <= 0 || $file['size'] > MAX_FILE_SIZE) {
            $message = 'File must be between 1 byte and 5 MB.';
            $msgType = 'error';
        } elseif (!is_uploaded_file($file['tmp_name'])) {
            $message = 'Invalid upload.';
            $msgType = 'error';
        } else {
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);

            if (!isset($allowedMimes[$mimeType])) {
                $message = 'Only PDF, JPG, and PNG are allowed.';
                $msgType = 'error';
            } else {
                $uploadDir = __DIR__ . '/../uploads/user_docs/';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                    error_log('Cannot create upload dir: ' . $uploadDir);
                    $message = 'Upload location unavailable.';
                    $msgType = 'error';
                } else {
                    $filename      = bin2hex(random_bytes(16)) . '.' . $allowedMimes[$mimeType];
                    $destination   = $uploadDir . $filename;
                    $relativePath  = 'uploads/user_docs/' . $filename;

                    if (!move_uploaded_file($file['tmp_name'], $destination)) {
                        $message = 'Could not save the file. Please try again.';
                        $msgType = 'error';
                    } else {
                        try {
                            // Get previous file to delete after successful DB write
                            $old = $pdo->prepare("
                                SELECT file_path FROM user_documents
                                WHERE user_id = ? AND requirement_id = ?
                            ");
                            $old->execute([$user_id, $req_id]);
                            $oldPath = $old->fetchColumn();

                            // Upsert: one doc per (user, requirement)
                            $stmt = $pdo->prepare("
                                INSERT INTO user_documents (user_id, requirement_id, file_path, uploaded_at)
                                VALUES (?, ?, ?, NOW())
                                ON DUPLICATE KEY UPDATE
                                    file_path = VALUES(file_path),
                                    uploaded_at = VALUES(uploaded_at)
                            ");
                            $stmt->execute([$user_id, $req_id, $relativePath]);

                            // Delete old file
                            if (
                                is_string($oldPath) &&
                                str_starts_with($oldPath, 'uploads/user_docs/') &&
                                basename($oldPath) !== $filename
                            ) {
                                $oldFile = __DIR__ . '/../' . $oldPath;
                                if (is_file($oldFile) && !unlink($oldFile)) {
                                    error_log('Could not delete old doc: ' . $oldFile);
                                }
                            }

                            $message = 'Document uploaded successfully!';
                            $msgType = 'success';
                        } catch (PDOException $e) {
                            @unlink($destination);
                            error_log('user_documents upload error: ' . $e->getMessage());
                            $message = 'Database error. Please try again.';
                            $msgType = 'error';
                        }
                    }
                }
            }
        }
    }
}

/* ---------- Fetch requirements for THIS user's program, with upload status ---------- */
$stmt = $pdo->prepare("
    SELECT
        pr.id,
        pr.requirement_file       AS template_file,
        r.requirement_name,
        r.description,
        ud.file_path              AS uploaded_file,
        ud.uploaded_at
    FROM program_requirements pr
    INNER JOIN requirements r        ON pr.requirement_id = r.id
    LEFT JOIN user_documents ud      ON ud.requirement_id = pr.id AND ud.user_id = ?
    WHERE pr.program_id = ?
    ORDER BY r.requirement_name ASC
");
$stmt->execute([$user_id, $userProgramId]);
$requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="../asset/css/upload_docs.css">

<div class="content-card">
    <div class="page-header">
        <div class="page-header-icon">
            <i class="fa-solid fa-cloud-arrow-up"></i>
        </div>
        <div>
            <h2>Upload Documents</h2>
            <p>Manage and submit the required documents for your application.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= h($msgType) ?>">
            <i class="fa-solid <?= $msgType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <div class="checklist-wrapper">
        <h3>Application Checklist</h3>
        <p class="checklist-desc">
            Please upload clear copies of the following documents (PDF, JPG, or PNG, max 5 MB each).
        </p>

        <?php if (empty($requirements)): ?>
            <p class="checklist-desc" style="color:#94a3b8; padding: 24px 0; text-align:center;">
                No requirements have been assigned to your program yet. Please check back later.
            </p>
        <?php else: ?>
            <div class="checklist-grid">
                <?php foreach ($requirements as $req):
                    $isUploaded = !empty($req['uploaded_file']);
                ?>
                    <div class="checklist-item <?= $isUploaded ? 'completed' : '' ?>">
                        <div class="checklist-icon">
                            <i class="fa-solid <?= $isUploaded ? 'fa-circle-check' : 'fa-circle-notch' ?>"></i>
                        </div>

                        <div class="checklist-info">
                            <strong><?= h($req['requirement_name']) ?></strong>
                            <small><?= h($req['description'] ?? 'No description provided.') ?></small>

                            <?php if (!empty($req['template_file'])): ?>
                                <a href="dashboard.php?page=documents&download_type=template&id=<?= (int)$req['id'] ?>"
                                   class="view-file view-file--template">
                                    <i class="fa-solid fa-download"></i> Download template
                                </a>
                            <?php endif; ?>

                            <?php if ($isUploaded): ?>
                                <a href="dashboard.php?page=documents&download_type=user_doc&req=<?= (int)$req['id'] ?>"
                                   class="view-file" target="_blank" rel="noopener">
                                    <i class="fa-solid fa-eye"></i> View uploaded file
                                </a>
                                <small class="uploaded-date">
                                    Uploaded <?= date('M d, Y g:i A', strtotime($req['uploaded_at'])) ?>
                                </small>
                            <?php endif; ?>
                        </div>

                        <div class="checklist-action">
                            <form method="POST" enctype="multipart/form-data" class="upload-form">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="requirement_id" value="<?= (int)$req['id'] ?>">
                                <label class="upload-btn">
                                    <i class="fa-solid fa-upload"></i>
                                    <?= $isUploaded ? 'Replace' : 'Upload' ?>
                                    <input type="file" name="document"
                                           accept=".pdf,.jpg,.jpeg,.png"
                                           onchange="submitUpload(this)" required>
                                </label>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function submitUpload(input) {
    const form = input.closest('form');
    if (!input.files.length) return;

    // Basic client-side size check (server still validates)
    if (input.files[0].size > 5 * 1024 * 1024) {
        alert('File is larger than 5 MB. Please choose a smaller file.');
        input.value = '';
        return;
    }

    const btn = form.querySelector('.upload-btn');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading…';
    btn.style.pointerEvents = 'none';
    form.submit();
}
</script>