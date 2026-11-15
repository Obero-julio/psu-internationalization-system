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
$userId = (int) $_SESSION['user_id'];

/* =========================
   FETCH ALL NOTIFICATIONS
========================= */
$stmt = $pdo->prepare("
    SELECT id, type, title, message, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   ESCAPE HELPER
========================= */
if (!function_exists('h')) {
    function h($value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

/* =========================
   COUNTS
========================= */
$totalCount  = count($notifications);
$unreadCount = 0;

$typeCounts = [
    'application'  => 0,
    'approval'     => 0,
    'document'     => 0,
    'reminder'     => 0,
    'announcement' => 0
];

foreach ($notifications as $notification) {

    if (!(int) $notification['is_read']) {
        $unreadCount++;
    }

    $type = $notification['type'];

    if (isset($typeCounts[$type])) {
        $typeCounts[$type]++;
    }
}

/* =========================
   TYPE MAP
========================= */
$typeMap = [
    'application' => [
        'icon'  => 'ti-clipboard-check',
        'label' => 'Application Update',
        'mod'   => ''
    ],

    'approval' => [
        'icon'  => 'ti-circle-check',
        'label' => 'Approval Update',
        'mod'   => 'approval'
    ],

    'document' => [
        'icon'  => 'ti-file-alert',
        'label' => 'Document Requirement',
        'mod'   => 'document'
    ],

    'reminder' => [
        'icon'  => 'ti-alarm',
        'label' => 'Travel Reminder',
        'mod'   => 'reminder'
    ],

    'announcement' => [
        'icon'  => 'ti-speakerphone',
        'label' => 'System Announcement',
        'mod'   => 'announcement'
    ],

    'default' => [
        'icon'  => 'ti-bell',
        'label' => 'Notification',
        'mod'   => ''
    ]
];

$tagLabels = [
    'application'  => 'Applications',
    'approval'     => 'Approvals',
    'document'     => 'Documents',
    'reminder'     => 'Reminders',
    'announcement' => 'Announcements'
];
?>

<link rel="stylesheet" href="../asset/css/notifications.css">
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">


<div class="content">

    <div class="notification-card">

        <!-- =========================
             HEADER
        ========================== -->
        <div class="notification-header">

            <div class="notification-header-left">

                <div class="notification-header-icon">
                    <i class="ti ti-bell"></i>
                </div>

                <div>
                    <h3>All Notifications</h3>

                    <p>
                        View and manage all your notifications and updates.
                    </p>
                </div>

            </div>

            <button
                class="btn-mark-all"
                id="markAllBtn"
                onclick="markAllRead()"
                <?= $unreadCount === 0 ? 'disabled' : '' ?>
            >
                <i class="ti ti-checks"></i>
                Mark all as read
            </button>

        </div>


        <!-- =========================
             FILTERS
        ========================== -->
        <div class="notification-filters">

            <button
                class="active"
                data-filter="all"
                onclick="filterNotifications('all', this)"
            >
                <i class="ti ti-layout-grid"></i>
                All
                <span class="filter-count">
                    <?= $totalCount ?>
                </span>
            </button>


            <button
                data-filter="unread"
                onclick="filterNotifications('unread', this)"
            >
                <i class="ti ti-mail"></i>
                Unread
                <span class="filter-count">
                    <?= $unreadCount ?>
                </span>
            </button>


            <button
                data-filter="application"
                onclick="filterNotifications('application', this)"
            >
                <i class="ti ti-file-description"></i>
                Applications
                <span class="filter-count">
                    <?= $typeCounts['application'] ?>
                </span>
            </button>


            <button
                data-filter="approval"
                onclick="filterNotifications('approval', this)"
            >
                <i class="ti ti-circle-check"></i>
                Approvals
                <span class="filter-count">
                    <?= $typeCounts['approval'] ?>
                </span>
            </button>


            <button
                data-filter="document"
                onclick="filterNotifications('document', this)"
            >
                <i class="ti ti-file-alert"></i>
                Documents
                <span class="filter-count">
                    <?= $typeCounts['document'] ?>
                </span>
            </button>


            <button
                data-filter="reminder"
                onclick="filterNotifications('reminder', this)"
            >
                <i class="ti ti-alarm"></i>
                Reminders
                <span class="filter-count">
                    <?= $typeCounts['reminder'] ?>
                </span>
            </button>


            <button
                data-filter="announcement"
                onclick="filterNotifications('announcement', this)"
            >
                <i class="ti ti-speakerphone"></i>
                Announcements
                <span class="filter-count">
                    <?= $typeCounts['announcement'] ?>
                </span>
            </button>

        </div>


        <!-- =========================
             TOOLBAR
        ========================== -->
        <div class="notification-toolbar">

            <button
                class="notification-sort"
                id="sortBtn"
                onclick="toggleSort()"
            >
                <i class="ti ti-arrows-sort"></i>

                <span id="sortLabel">
                    Latest first
                </span>
            </button>


            <div class="notification-search">

                <i class="ti ti-search"></i>

                <input
                    type="text"
                    id="notificationSearch"
                    placeholder="Search notifications..."
                    oninput="searchNotifications()"
                >

            </div>

        </div>


        <!-- =========================
             NOTIFICATION LIST
        ========================== -->
        <div
            class="notification-list"
            id="notificationList"
        >

            <?php if (!empty($notifications)): ?>

                <?php foreach ($notifications as $n): ?>

                    <?php
                        $type = $n['type'];

                        $map = $typeMap[$type] ?? $typeMap['default'];

                        $icon = $map['icon'];
                        $mod  = $map['mod'];

                        $tagLabel =
                            $tagLabels[$type]
                            ?? 'Notification';

                        $isUnread =
                            !(int) $n['is_read'];

                        $timestamp =
                            strtotime($n['created_at']);
                    ?>


                    <div
                        class="notification-item <?= $isUnread ? 'unread' : '' ?>"

                        data-id="<?= (int) $n['id'] ?>"

                        data-type="<?= h($type) ?>"

                        data-read="<?= $isUnread ? 'unread' : 'read' ?>"

                        data-time="<?= $timestamp ?>"

                        data-search="<?= h(
                            strtolower(
                                $n['title'] . ' ' .
                                $n['message'] . ' ' .
                                $tagLabel
                            )
                        ) ?>"

                        onclick="markRead(this)"
                    >

                        <!-- ICON -->
                        <div
                            class="notification-icon
                            <?= $mod ? 'icon-' . h($mod) : '' ?>"
                        >
                            <i class="ti <?= h($icon) ?>"></i>
                        </div>


                        <!-- CONTENT -->
                        <div class="notification-content">

                            <div class="notification-title">
                                <?= h($n['title']) ?>
                            </div>


                            <div class="notification-message">
                                <?= h($n['message']) ?>
                            </div>


                            <div
                                class="notification-tag
                                <?= $mod ? 'tag-' . h($mod) : '' ?>"
                            >
                                <?= h($tagLabel) ?>
                            </div>

                        </div>


                        <!-- META -->
                        <div class="notification-meta">

                            <span class="notification-time">
                                <?= date(
                                    'M d, Y g:i A',
                                    $timestamp
                                ) ?>
                            </span>


                            <span
                                class="notification-status-dot"
                            ></span>

                        </div>

                    </div>

                <?php endforeach; ?>


            <?php else: ?>

                <div class="notification-empty">

                    <div class="notification-empty-icon">
                        <i class="ti ti-bell-off"></i>
                    </div>

                    <h3>
                        No notifications yet
                    </h3>

                    <p>
                        You're all caught up.
                        New alerts will appear here.
                    </p>

                </div>

            <?php endif; ?>

        </div>


        <!-- =========================
             NO SEARCH RESULTS
        ========================== -->
        <div
            class="notification-empty"
            id="noSearchResults"
            style="display:none;"
        >

            <div class="notification-empty-icon">
                <i class="ti ti-search-off"></i>
            </div>

            <h3>
                No notifications found
            </h3>

            <p>
                Try a different search term or filter.
            </p>

        </div>


        <!-- =========================
             FOOTER
        ========================== -->
        <div class="notification-footer">

            <span>
                Showing
                <strong id="visibleCount">
                    <?= $totalCount ?>
                </strong>
                notifications
            </span>

        </div>

    </div>

</div>


<script>

let sortAscending = false;
let currentFilter = 'all';


/* ==========================================
   MARK SINGLE NOTIFICATION AS READ
========================================== */

function markRead(el) {

    // Already read, nothing to do
    if (el.dataset.read === 'read') {
        return;
    }

    fetch('../admin/markread.php', {
        method: 'POST',

        headers: {
            'Content-Type': 'application/json'
        },

        body: JSON.stringify({
            id: el.dataset.id
        })
    })

    .then(async response => {

        const text = await response.text();

        try {
            return JSON.parse(text);
        } catch (error) {
            console.error('Server response:', text);
            throw new Error('Server did not return JSON');
        }

    })

    .then(result => {

        if (result.success) {

            // Remove unread styling
            el.classList.remove('unread');

            // Change status to read
            el.dataset.read = 'read';

            // Update unread counter
            updateCounts();

        } else {

            showToast(
                'error',
                result.message || 'Could not mark notification.'
            );

        }

    })

    .catch(error => {

        console.error(error);

        showToast(
            'error',
            'Could not reach the server.'
        );

    });
}


/* ==========================================
   MARK ALL AS READ
========================================== */

function markAllRead() {

    const button =
        document.getElementById('markAllBtn');

    if (button.disabled) {
        return;
    }

    button.disabled = true;

    fetch('../admin/markallread.php', {
        method: 'POST'
    })

    .then(async response => {

        const text = await response.text();

        try {
            return JSON.parse(text);
        } catch {
            throw new Error(
                'Server did not return JSON'
            );
        }

    })

    .then(result => {

        if (result.success) {

            document
                .querySelectorAll('.notification-item')
                .forEach(el => {

                    el.classList.remove('unread');

                    el.dataset.read = 'read';

                });

            updateCounts();

            showToast(
                'success',
                'All notifications marked as read.'
            );

        } else {

            button.disabled = false;

            showToast(
                'error',
                result.message ||
                'Could not mark all notifications.'
            );

        }

    })

    .catch(() => {

        button.disabled = false;

        showToast(
            'error',
            'Could not reach the server.'
        );

    });
}


/* ==========================================
   UPDATE COUNTS
========================================== */

function updateCounts() {

    const items =
        document.querySelectorAll(
            '.notification-item'
        );

    const counts = {
        all: items.length,
        unread: 0,
        application: 0,
        approval: 0,
        document: 0,
        reminder: 0,
        announcement: 0
    };


    items.forEach(el => {

        if (el.dataset.read === 'unread') {
            counts.unread++;
        }

        const type =
            el.dataset.type;

        if (
            Object.prototype.hasOwnProperty
            .call(counts, type)
        ) {
            counts[type]++;
        }

    });


    Object.keys(counts).forEach(key => {

        const element =
            document.querySelector(
                `.notification-filters
                 button[data-filter="${key}"]
                 .filter-count`
            );

        if (element) {
            element.textContent =
                counts[key];
        }

    });


    const markAllBtn =
        document.getElementById(
            'markAllBtn'
        );

    if (markAllBtn) {

        markAllBtn.disabled =
            counts.unread === 0;

    }

}


/* ==========================================
   FILTER
========================================== */

function filterNotifications(type, btn) {

    currentFilter = type;

    document
        .querySelectorAll(
            '.notification-filters button'
        )
        .forEach(button => {

            button.classList.remove(
                'active'
            );

        });

    btn.classList.add('active');

    applyFilters();
}


/* ==========================================
   SEARCH
========================================== */

function searchNotifications() {

    applyFilters();

}


/* ==========================================
   APPLY FILTER + SEARCH
========================================== */

function applyFilters() {

    const searchInput =
        document.getElementById(
            'notificationSearch'
        );

    const search =
        searchInput
            ? searchInput.value
                .trim()
                .toLowerCase()
            : '';


    const items =
        document.querySelectorAll(
            '.notification-item'
        );

    let visible = 0;


    items.forEach(el => {

        const type =
            el.dataset.type;

        const read =
            el.dataset.read;

        const text =
            el.dataset.search || '';


        /* TYPE FILTER */

        let typeMatch = true;

        if (
            currentFilter !== 'all' &&
            currentFilter !== 'unread'
        ) {

            typeMatch =
                type === currentFilter;

        }


        /* UNREAD FILTER */

        let readMatch = true;

        if (currentFilter === 'unread') {

            readMatch =
                read === 'unread';

        }


        /* SEARCH FILTER */

        const searchMatch =
            search === '' ||
            text.includes(search);


        const show =
            typeMatch &&
            readMatch &&
            searchMatch;


        el.style.display =
            show ? '' : 'none';


        if (show) {
            visible++;
        }

    });


    const visibleCount =
        document.getElementById(
            'visibleCount'
        );

    if (visibleCount) {
        visibleCount.textContent =
            visible;
    }


    const noResults =
        document.getElementById(
            'noSearchResults'
        );

    if (noResults) {

        noResults.style.display =
            visible === 0
                ? 'block'
                : 'none';

    }

}


/* ==========================================
   SORT
========================================== */

function toggleSort() {

    sortAscending =
        !sortAscending;


    const label =
        document.getElementById(
            'sortLabel'
        );


    label.textContent =
        sortAscending
            ? 'Oldest first'
            : 'Latest first';


    const list =
        document.getElementById(
            'notificationList'
        );


    const items =
        Array.from(
            list.querySelectorAll(
                '.notification-item'
            )
        );


    items.sort((a, b) => {

        const timeA =
            Number(a.dataset.time);

        const timeB =
            Number(b.dataset.time);

        return sortAscending
            ? timeA - timeB
            : timeB - timeA;

    });


    items.forEach(item => {

        list.appendChild(item);

    });


    applyFilters();

}


/* ==========================================
   TOAST
========================================== */

function showToast(type, message) {

    const existing =
        document.querySelector('.toast');

    if (existing) {
        existing.remove();
    }


    const toast =
        document.createElement('div');


    toast.className =
        `toast toast--${type}`;


    toast.innerHTML = `
        <i class="fa ${
            type === 'success'
                ? 'fa-check-circle'
                : 'fa-exclamation-circle'
        }"></i>

        <span></span>
    `;


    toast
        .querySelector('span')
        .textContent = message;


    document.body.appendChild(toast);


    requestAnimationFrame(() => {

        toast.classList.add(
            'toast--show'
        );

    });


    setTimeout(() => {

        toast.classList.remove(
            'toast--show'
        );


        setTimeout(() => {

            toast.remove();

        }, 300);

    }, 3000);

}


/* ==========================================
   INITIALIZE
========================================== */

document.addEventListener(
    'DOMContentLoaded',
    () => {

        updateCounts();

        applyFilters();

    }
);

</script>