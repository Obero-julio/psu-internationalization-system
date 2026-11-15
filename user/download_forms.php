<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = db();

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// FIXED: Removed the INNER JOIN to programs because your table doesn't have program_id
// Dynamically checks for the requirement name column
$stmt = $pdo->query("
    SELECT *
    FROM program_requirements
    ORDER BY id ASC
");
$requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="../asset/css/download_form.css">

<div class="content-card">

    <div class="page-header">
        <div class="page-header-icon">
            <i class="fa-solid fa-file-lines"></i>
        </div>
        <div>
            <h2>Program Requirements & Forms</h2>
            <p>Review the documents required for your mobility program application.</p>
        </div>
    </div>

    <div class="form-list">

        <?php if (empty($requirements)): ?>

            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fa-regular fa-folder-open"></i>
                </div>
                <h3>No Requirements Found</h3>
                <p>No requirements have been configured by the administrator yet. Please check back later.</p>
            </div>

        <?php else: ?>

            <?php foreach ($requirements as $req): 
                // Safely grab the name column whether it's requirement_name, name, title, or label
                $reqName = $req['requirement_name'] ?? $req['name'] ?? $req['title'] ?? $req['label'] ?? 'Unnamed Requirement';
            ?>

                <div class="form-item">
                    <div class="form-icon">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>

                    <div class="form-info">
                        <h3><?= e($reqName) ?></h3>
                        <p><?= e($req['description'] ?? 'No description provided for this requirement.') ?></p>
                    </div>

                    <div class="form-action">
                        <button class="btn-status" disabled>
                            <i class="fa-solid fa-circle-check"></i>
                            Required
                        </button>
                        <a href="?page=upload_documents" class="btn-upload">
                            <i class="fa-solid fa-upload"></i> Upload
                        </a>
                    </div>
                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>