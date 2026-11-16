<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit();
}

$pdo = db();
$userId = (int)$_SESSION['user_id'];

if (!function_exists('h')) {
    function h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/* =========================
   FETCH NOTIFICATIONS
   Only what the applicant should see:
     - admin applications updates (approved/rejected)
     - admin announcements
     - document reminders
========================= */
$stmt = $pdo->prepare("
    SELECT id, type, title, message, is_read, created_at
    FROM notifications
    WHERE user_id = ?
      AND type IN ('application', 'approval', 'rejected', 'document', 'reminder', 'announcement')
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   COUNTS
========================= */
$totalCount  = count($notifications);
$unreadCount = 0;
$typeCounts  = [
    'application'  => 0,
    'approval'     => 0,
    'rejected'     => 0,
    'document'     => 0,
    'reminder'     => 0,
    'announcement' => 0,
];

foreach ($notifications as $n) {
    if (!$n['is_read']) $unreadCount++;
    if (isset($typeCounts[$n['type']])) {
        $typeCounts[$n['type']]++;
    }
}

/* ICON MAP */
$typeMap = [
    'application'  => ['ti-clipboard-check',   'Application Update',   ''],
    'approval'     => ['ti-check',             'Approved',             'approval'],
    'rejected'     => ['ti-x',                 'Rejected',             'rejected'],
    'document'     => ['ti-file-alert',        'Document Requirement', ''],
    'reminder'     => ['ti-alarm',             'Travel Reminder',      'reminder'],
    'announcement' => ['ti-speakerphone',      'Announcement',         'announcement'],
    'default'      => ['ti-bell',              'Notification',         ''],
];

$tagLabels = [
    'application'  => 'Applications',
    'approval'     => 'Approvals',
    'rejected'     => 'Rejections',
    'document'     => 'Documents',
    'reminder'     => 'Reminders',
    'announcement' => 'Announcements',
];
?>

<link rel="stylesheet" href="../asset/css/notifications.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

<div class="content">

<div class="notification-card">

    <div class="notification-header">
        <div class="notification-header-left">
            <div class="notification-header-icon">
                <i class="ti ti-bell"></i>
            </div>
            <div>
                <h3>Notifications</h3>
                <p>Stay informed about important updates, alerts, and activities.</p>
            </div>
        </div>

        <button class="btn-mark-all" id="markAllBtn"
                onclick="markAllRead()"
                <?= $unreadCount === 0 ? 'disabled' : '' ?>>
            <i class="ti ti-checks"></i> Mark all as read
        </button>
    </div>

    <div class="notification-filters">
        <button class="active" data-filter="all" onclick="filter('all', this)">
            <i class="ti ti-layout-grid"></i> All
            <span class="filter-count"><?= $totalCount ?></span>
        </button>
        <button data-filter="unread" onclick="filter('unread', this)">
            <i class="ti ti-mail"></i> Unread
            <span class="filter-count"><?= $unreadCount ?></span>
        </button>
        <button data-filter="application" onclick="filter('application', this)">
            <i class="ti ti-file-description"></i> Applications
            <span class="filter-count"><?= $typeCounts['application'] ?></span>
        </button>
        <button data-filter="approval" onclick="filter('approval', this)">
            <i class="ti ti-circle-check"></i> Approved
            <span class="filter-count"><?= $typeCounts['approval'] ?></span>
        </button>
        <button data-filter="rejected" onclick="filter('rejected', this)">
            <i class="ti ti-circle-x"></i> Rejected
            <span class="filter-count"><?= $typeCounts['rejected'] ?></span>
        </button>
        <button data-filter="document" onclick="filter('document', this)">
            <i class="ti ti-file-alert"></i> Documents
            <span class="filter-count"><?= $typeCounts['document'] ?></span>
        </button>
        <button data-filter="reminder" onclick="filter('reminder', this)">
            <i class="ti ti-bell"></i> Reminders
            <span class="filter-count"><?= $typeCounts['reminder'] ?></span>
        </button>
        <button data-filter="announcement" onclick="filter('announcement', this)">
            <i class="ti ti-speakerphone"></i> Announcements
            <span class="filter-count"><?= $typeCounts['announcement'] ?></span>
        </button>
    </div>

    <div class="notification-toolbar">
        <button class="notification-sort" id="sortBtn" onclick="toggleSort()">
            <i class="ti ti-arrows-sort"></i> <span id="sortLabel">Latest first</span>
        </button>
        <button class="notification-toolbar-icon-btn" title="View options">
            <i class="ti ti-adjustments-horizontal"></i>
        </button>
    </div>

    <div class="notification-list" id="notificationList">

    <?php if (!empty($notifications)): ?>
        <?php foreach ($notifications as $n):
            $map      = $typeMap[$n['type']] ?? $typeMap['default'];
            $icon     = $map[0];
            $mod      = $map[2];
            $tagLabel = $tagLabels[$n['type']] ?? 'Notification';
            $isUnread = !$n['is_read'];
        ?>
        <div class="notification-item <?= $isUnread ? 'unread' : '' ?>"
             data-id="<?= (int)$n['id'] ?>"
             data-type="<?= h($n['type']) ?>"
             data-read="<?= $isUnread ? 'unread' : 'read' ?>"
             data-time="<?= (int)strtotime($n['created_at']) ?>"
             onclick="markRead(this)">

            <div class="notification-icon <?= $mod ? 'icon-' . h($mod) : '' ?>">
                <i class="ti <?= h($icon) ?>"></i>
            </div>

            <div class="notification-content">
                <div class="notification-title"><?= h($n['title']) ?></div>
                <div class="notification-message"><?= h($n['message']) ?></div>
                <div class="notification-tag <?= $mod ? 'tag-' . h($mod) : '' ?>">
                    <?= h($tagLabel) ?>
                </div>
            </div>

            <div class="notification-meta">
                <span class="notification-time">
                    <?= date('M d, Y g:i A', strtotime($n['created_at'])) ?>
                </span>
                <span class="notification-status-dot"></span>
            </div>

        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="notification-empty">
            <div class="notification-empty-icon">
                <i class="ti ti-bell-off"></i>
            </div>
            <h3>No notifications yet</h3>
            <p>You're all caught up. New alerts will appear here.</p>
        </div>
    <?php endif; ?>

    </div>

</div>
</div>

<script>
let sortAscending = false;

/* ── Mark single as read ── */
function markRead(el) {
    if (el.dataset.read === 'read') return;

    fetch(window.NOTIF_CONFIG.baseUrl + '/markread.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: el.dataset.id, csrf_token: window.NOTIF_CONFIG.csrf })
    })
    .then(async res => {
        const text = await res.text();
        try { return JSON.parse(text); }
        catch { throw new Error('Server did not return JSON'); }
    })
    .then(res => {
        if (res.success) {
            el.classList.remove('unread');
            el.dataset.read = 'read';
            updateCounts();
        } else {
            showToast('error', res.message || 'Could not mark notification.');
        }
    })
    .catch(() => showToast('error', 'Could not reach the server.'));
}

/* ── Mark all as read ── */
function markAllRead() {
    fetch(window.NOTIF_CONFIG.baseUrl + '/markallread.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: window.NOTIF_CONFIG.csrf })
    })
    .then(async res => {
        const text = await res.text();
        try { return JSON.parse(text); }
        catch { throw new Error('Server did not return JSON'); }
    })
    .then(res => {
        if (res.success) {
            document.querySelectorAll('.notification-item').forEach(el => {
                el.classList.remove('unread');
                el.dataset.read = 'read';
            });
            updateCounts();
            showToast('success', 'All notifications marked as read.');
        } else {
            showToast('error', res.message || 'Could not mark all notifications.');
        }
    })
    .catch(() => showToast('error', 'Could not reach the server.'));
}

/* ── Rebuild filter counts from the DOM (including every type) ── */
function updateCounts() {
    const items = document.querySelectorAll('.notification-item');
    const counts = { all: items.length, unread: 0 };

    items.forEach(el => {
        const t = el.dataset.type;
        if (el.dataset.read === 'unread') counts.unread++;
        counts[t] = (counts[t] || 0) + 1;
    });

    document.querySelectorAll('.notification-filters button').forEach(btn => {
        const key = btn.dataset.filter;
        const span = btn.querySelector('.filter-count');
        if (!span) return;
        if (counts[key] !== undefined) span.textContent = counts[key];
    });

    const markAllBtn = document.getElementById('markAllBtn');
    if (markAllBtn) markAllBtn.disabled = counts.unread === 0;
}

/* ── Filter (uses single active state, works with any new type) ── */
function filter(type, btn) {
    document.querySelectorAll('.notification-item').forEach(el => {
        let show = false;
        if (type === 'all')         show = true;
        else if (type === 'unread') show = el.dataset.read === 'unread';
        else                        show = el.dataset.type === type;
        el.style.display = show ? '' : 'none';
    });

    document.querySelectorAll('.notification-filters button')
        .forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

/* ── Sort ── */
function toggleSort() {
    sortAscending = !sortAscending;
    document.getElementById('sortLabel').textContent =
        sortAscending ? 'Oldest first' : 'Latest first';

    const list  = document.getElementById('notificationList');
    const items = Array.from(list.querySelectorAll('.notification-item'));

    items.sort((a, b) => {
        const diff = Number(a.dataset.time) - Number(b.dataset.time);
        return sortAscending ? diff : -diff;
    });

    items.forEach(item => list.appendChild(item));
}

/* ── Toast ── */
function showToast(type, message) {
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = `toast toast--${type}`;
    toast.innerHTML = `
        <i class="fa fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span></span>
    `;
    toast.querySelector('span').textContent = message;

    document.body.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('toast--show'));

    setTimeout(() => {
        toast.classList.remove('toast--show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

window.NOTIF_CONFIG = {
    baseUrl: '.',
    csrf: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
};
</script>