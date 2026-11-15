<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   AUTHENTICATION
========================= */
if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit();
}

/* =========================
   AUTHORIZATION (ROLE CHECK)
========================= */
$role = $_SESSION['role'] ?? 'student';
$allowedRoles = ['admin', 'super_admin', 'supervisor', 'student'];

if (!in_array($role, $allowedRoles, true)) {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = db();

/* =========================
   PAGINATION SETUP
========================= */
$perPage = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

/* =========================
   FETCH ACTIVITY DATA (SCOPED BY ROLE)
========================= */
$where = '';
$params = [];

if ($role === 'student') {
    $where = ' WHERE am.student_id = :sid';
    $params['sid'] = $_SESSION['user_id'];
}

// Count total for pagination
$countSql = "SELECT COUNT(*) FROM activity_monitoring am" . $where;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $perPage));

// Clamp page to valid range
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$sql = "
    SELECT am.*, 
           s.first_name, s.last_name
    FROM activity_monitoring am
    LEFT JOIN students s ON s.id = am.student_id
    $where
    ORDER BY am.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $val) {
    $stmt->bindValue(':' . $key, $val);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   HELPERS
========================= */
function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function getStatus(int $progress, ?string $status): string {
    if ($status === 'completed' || $progress >= 100) return 'completed';
    if ($status === 'delayed') return 'delayed';
    return 'ongoing';
}

function studentName(array $a): string {
    $name = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
    return $name !== '' ? $name : 'Unknown student';
}

function getInitials(array $a): string {
    $first = $a['first_name'] ?? '';
    $last = $a['last_name'] ?? '';
    if (trim($first . $last) === '') return '?';
    return strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
}

/* =========================
   PRE-COMPUTE STATUSES & STATS
========================= */
$counts = ['ongoing' => 0, 'completed' => 0, 'delayed' => 0];

foreach ($activities as &$a) {
    $a['progress']        = max(0, min(100, (int)($a['progress'] ?? 0)));
    $a['computed_status'] = getStatus($a['progress'], $a['status'] ?? null);
    $counts[$a['computed_status']]++;
}
unset($a);

$total     = $totalRecords;
$ongoing   = $counts['ongoing'];
$completed = $counts['completed'];
$delayed   = $counts['delayed'];
?>

<link rel="stylesheet" href="../asset/css/activity_monitoring.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<div class="content">

<!-- ================= HEADER ================= -->
<div class="page-header">
    <h2>Activity Monitoring</h2>
    <p>Tracks student performance, weekly reports, and supervisor feedback.</p>
</div>

<!-- ================= STATS ================= -->
<div class="stats-grid">

    <div class="stat-card">
        <h3><?= $total ?></h3>
        <p>Total Activities</p>
    </div>

    <div class="stat-card">
        <h3><?= $ongoing ?></h3>
        <p>Ongoing</p>
    </div>

    <div class="stat-card">
        <h3><?= $completed ?></h3>
        <p>Completed</p>
    </div>

    <div class="stat-card">
        <h3><?= $delayed ?></h3>
        <p>Delayed</p>
    </div>

</div>

<!-- ================= TABLE ================= -->
<div class="table-card">

<table>

<thead>
<tr>
    <th>Student</th>
    <th>Week</th>
    <th>Report</th>
    <th>Supervisor Feedback</th>
    <th>Progress</th>
    <th>Status</th>
</tr>
</thead>

<tbody>

<?php if (!empty($activities)): ?>

<?php foreach ($activities as $a): ?>

<?php
    $status   = $a['computed_status'];
    $progress = $a['progress'];
    $initials = getInitials($a);
?>

<tr>
    <td>
        <div class="user-name-cell">
            <div class="user-avatar"><?= h($initials) ?></div>
            <span><?= h(studentName($a)) ?></span>
        </div>
    </td>

    <td style="font-weight:600;">Week <?= h($a['week_number'] ?? '—') ?></td>

    <td>
        <?= h(mb_strimwidth($a['report'] ?? '', 0, 80, '...')) ?>
    </td>

    <td>
        <?= h(mb_strimwidth($a['supervisor_feedback'] ?? '', 0, 80, '...')) ?>
    </td>

    <td>
        <div class="progress-bar"
             role="progressbar"
             aria-valuenow="<?= $progress ?>"
             aria-valuemin="0"
             aria-valuemax="100"
             aria-label="Progress <?= $progress ?> percent">
            <div class="progress-fill" style="width: <?= $progress ?>%"></div>
        </div>
        <small><?= $progress ?>%</small>
    </td>

    <td>
        <span class="status-badge status-<?= h($status) ?>">
            <?= ucfirst(h($status)) ?>
        </span>
    </td>
</tr>

<?php endforeach; ?>

<?php else: ?>

<tr class="empty-row">
    <td colspan="6">No activity records found.</td>
</tr>

<?php endif; ?>

</tbody>
</table>

<!-- ================= PAGINATION ================= -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>" class="page-link">&laquo; Prev</a>
    <?php else: ?>
        <span class="page-link disabled">&laquo; Prev</span>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i === $page): ?>
            <span class="page-link active"><?= $i ?></span>
        <?php else: ?>
            <a href="?page=<?= $i ?>" class="page-link"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>" class="page-link">Next &raquo;</a>
    <?php else: ?>
        <span class="page-link disabled">Next &raquo;</span>
    <?php endif; ?>
</div>
<?php endif; ?>

</div>
</div>