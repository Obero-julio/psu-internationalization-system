<?php
require_once __DIR__ . '/../config/db.php';

$pdo = db();

/*
|--------------------------------------------------------------------------
| Fetch Approved Students
|--------------------------------------------------------------------------
*/

$sql = "
SELECT
    a.id,

    CONCAT(
        u.first_name,
        ' ',
        IF(
            u.middle_name IS NULL OR u.middle_name='',
            '',
            CONCAT(LEFT(u.middle_name,1),'. ')
        ),
        u.last_name
    ) AS student_name,
    p.program_name,
    a.institution AS institution_name,
    a.mobility_type,
    a.semester,
    a.school_year,
    a.approval_date,
    a.status
FROM applications a
LEFT JOIN users u ON a.applicant_id = u.id
LEFT JOIN programs p ON a.program_id = p.id
WHERE a.status='approved'
AND a.documents_status = 'complete'
ORDER BY a.approval_date DESC
";

$students = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<link rel="stylesheet" href="../asset/css/applications.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<div class="content">

    <div class="page-header">
        <h2>Approved Students</h2>
        <p>List of students approved for international mobility programs with complete documents.</p>
    </div>

    <div class="toolbar">
        <div class="search-wrap">
            <i class="fa fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search student by name, program, or partner...">
        </div>
    </div>

    <div class="table-section">
        <table class="table-card">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Program</th>
                    <th>Partner Institution</th>
                    <th>Mobility</th>
                    <th>Semester</th>
                    <th>School Year</th>
                    <th>Approved Date</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody id="tableBody">
            <?php if($students): ?>
                <?php foreach($students as $row): ?>
                
                <?php
                    $initials = '';
                    $parts = explode(' ', trim($row['student_name'] ?? ''));
                    foreach ($parts as $p) {
                        if ($p !== '') $initials .= strtoupper(substr($p, 0, 1));
                        if (strlen($initials) >= 2) break;
                    }
                ?>

                <tr data-search="<?= h(strtolower(($row['student_name'] ?? '') . ' ' . ($row['program_name'] ?? '') . ' ' . ($row['institution_name'] ?? ''))) ?>">
                    
                    <td>
                        <div class="applicant-cell">
                            <div class="applicant-avatar"><?= h($initials ?: '?') ?></div>
                            <div class="applicant-info">
                                <strong><?= h($row['student_name']) ?></strong>
                            </div>
                        </div>
                    </td>

                    <td><?= h($row['program_name']) ?></td>
                    <td><?= h($row['institution_name']) ?></td>
                    
                    <td>
                        <span class="status-badge status-submitted">
                            <i class="fa-solid fa-plane"></i> <?= h($row['mobility_type']) ?>
                        </span>
                    </td>

                    <td><?= h($row['semester']) ?></td>
                    <td><?= h($row['school_year']) ?></td>

                    <td class="date-cell">
                        <?= !empty($row['approval_date']) ? date("M d, Y", strtotime($row['approval_date'])) : '—' ?>
                    </td>

                    <td>
                        <span class="status-badge status-approved">
                            <?= h(ucfirst($row['status'])) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fa fa-folder-open" style="font-size: 30px; color: var(--color-info);"></i>
                            </div>
                            <h3>No Approved Students</h3>
                            <p>No approved students with complete documents found at this time.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.getElementById("searchInput").addEventListener("input",function(){
    const keyword=this.value.toLowerCase();
    document.querySelectorAll("#tableBody tr").forEach(function(row){
        const text=row.dataset.search || "";
        row.style.display=text.includes(keyword) ? "" : "none";
    });
});
</script>