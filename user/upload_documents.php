<?php
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../public/login.php');
    exit;
}

$pdo = db();
$message = '';
$msgType = '';

// Handle File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $req_id = (int)($_POST['requirement_id'] ?? 0);
    $file = $_FILES['document'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($file['type'], $allowedTypes)) {
            $message = 'Invalid file type. Only PDF, JPG, and PNG are allowed.';
            $msgType = 'error';
        } else {
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('doc_' . $user_id . '_') . '.' . $ext;
            $filepath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Save to database (Assuming you have a user_documents table)
                $stmt = $pdo->prepare("
                    INSERT INTO user_documents (user_id, requirement_id, file_path, uploaded_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$user_id, $req_id, 'uploads/' . $filename]);
                
                $message = 'Document uploaded successfully!';
                $msgType = 'success';
            } else {
                $message = 'Failed to move uploaded file.';
                $msgType = 'error';
            }
        }
    } else {
        $message = 'Please select a valid file to upload.';
        $msgType = 'error';
    }
}

// Fetch global requirements
$requirements = $pdo->query("SELECT * FROM program_requirements ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
// Fetch what the user has already uploaded
$stmt = $pdo->prepare("SELECT requirement_id, file_path FROM user_documents WHERE user_id = ?");
$stmt->execute([$user_id]);
$uploadedDocs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Returns [requirement_id => file_path]
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
        <div class="alert alert-<?= $msgType ?>">
            <i class="fa-solid <?= $msgType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="checklist-wrapper">
        <h3>Application Checklist</h3>
        <p class="checklist-desc">Please upload clear copies of the following documents (PDF, JPG, or PNG).</p>

        <div class="checklist-grid">
            <?php foreach ($requirements as $req): 
                $isUploaded = isset($uploadedDocs[$req['id']]);
            ?>
                <div class="checklist-item <?= $isUploaded ? 'completed' : '' ?>">
                    <div class="checklist-icon">
                        <i class="fa-solid <?= $isUploaded ? 'fa-circle-check' : 'fa-circle-notch' ?>"></i>
                    </div>
                    
                    <div class="checklist-info">
<strong><?= htmlspecialchars($req['requirement_name'] ?? $req['name'] ?? $req['title'] ?? $req['label'] ?? 'Unnamed Requirement') ?></strong>                        <small><?= htmlspecialchars($req['description'] ?? 'No description provided.') ?></small>
                        
                        <?php if ($isUploaded): ?>
                            <a href="../<?= htmlspecialchars($uploadedDocs[$req['id']]) ?>" target="_blank" class="view-file">
                                <i class="fa-solid fa-eye"></i> View Uploaded File
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="checklist-action">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="requirement_id" value="<?= $req['id'] ?>">
                            <label class="upload-btn">
                                <i class="fa-solid fa-upload"></i> 
                                <?= $isUploaded ? 'Replace' : 'Upload' ?>
                                <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" onchange="this.form.submit()" required>
                            </label>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>