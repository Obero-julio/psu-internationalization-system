<?php
require_once __DIR__ . '/../config/db.php';

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* ---------------------------------------------------------------------
 * Read search / filter / sort / paging state from the query string
 * ------------------------------------------------------------------- */

$search        = trim((string) ($_GET['q'] ?? ''));
$filterType    = trim((string) ($_GET['type'] ?? ''));
$filterCountry = trim((string) ($_GET['country'] ?? ''));
$filterTerm    = trim((string) ($_GET['term'] ?? ''));

$sortOptions = [
    'start_asc'   => 'Start Date (Soonest)',
    'start_desc'  => 'Start Date (Latest)',
    'name_asc'    => 'Program Name (A-Z)',
    'country_asc' => 'Country (A-Z)',
    'slots_desc'  => 'Most Slots Available',
];
$sort = array_key_exists($_GET['sort'] ?? '', $sortOptions) ? $_GET['sort'] : 'start_asc';

$perPageOptions = [8, 12, 16];
$requestedPerPage = (int) ($_GET['pp'] ?? 12);
$perPage = in_array($requestedPerPage, $perPageOptions, true) ? $requestedPerPage : 12;

$view        = ($_GET['view'] ?? 'grid') === 'list' ? 'list' : 'grid';
$currentPage = max(1, (int) ($_GET['ppage'] ?? 1));

/* ---------------------------------------------------------------------
 * Base dataset
 * ------------------------------------------------------------------- */

$baseSql = "
    SELECT
        p.id,
        p.program_name,
        p.program_type,
        p.status,
        p.start_date,
        p.end_date,
        p.description,
        pr.institution_name AS partner_institution,
        c.country_name AS country,
        CASE
            WHEN MONTH(p.start_date) BETWEEN 1 AND 4 THEN CONCAT('Spring ', YEAR(p.start_date))
            WHEN MONTH(p.start_date) BETWEEN 5 AND 7 THEN CONCAT('Summer ', YEAR(p.start_date))
            ELSE CONCAT('Fall ', YEAR(p.start_date))
        END AS term_label,
        COALESCE(p.slots, 0) AS total_slots,
        GREATEST(COALESCE(p.slots, 0) - COALESCE(taken.filled, 0), 0) AS slots_available
    FROM programs p
    LEFT JOIN partners pr ON p.partner_id = pr.id
    LEFT JOIN countries c ON pr.country_id = c.id
    LEFT JOIN (
        SELECT program_id, COUNT(*) AS filled
        FROM applications
        WHERE status IN ('Approved','Accepted','Enrolled')
        GROUP BY program_id
    ) taken ON taken.program_id = p.id
    WHERE p.status IN ('Active','Upcoming')
";

$where  = [];
$params = [];

if ($filterType !== '') {
    $where[]         = 'program_type = :type';
    $params[':type'] = $filterType;
}
if ($filterCountry !== '') {
    $where[]            = 'country = :country';
    $params[':country'] = $filterCountry;
}
if ($filterTerm !== '') {
    $where[]         = 'term_label = :term';
    $params[':term'] = $filterTerm;
}
if ($search !== '') {
    $where[]       = '(program_name LIKE :q1 OR partner_institution LIKE :q2 OR country LIKE :q3)';
    $like          = '%' . $search . '%';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$orderSql = match ($sort) {
    'start_desc'  => 'start_date DESC',
    'name_asc'    => 'program_name ASC',
    'country_asc' => 'country ASC, start_date ASC',
    'slots_desc'  => 'slots_available DESC, start_date ASC',
    default       => 'start_date ASC',
};

$pdo = db();

// Total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM ({$baseSql}) t {$whereSql}");
$countStmt->execute($params);
$totalPrograms = (int) $countStmt->fetchColumn();
$totalPages    = max(1, (int) ceil($totalPrograms / $perPage));
$currentPage   = min($currentPage, $totalPages);
$offset        = ($currentPage - 1) * $perPage;

$dataStmt = $pdo->prepare(
    "SELECT * FROM ({$baseSql}) t {$whereSql} ORDER BY {$orderSql} LIMIT :limit OFFSET :offset"
);
foreach ($params as $key => $value) {
    $dataStmt->bindValue($key, $value, PDO::PARAM_STR);
}
$dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$dataStmt->execute();
$programs = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------------------------------------------------------------------
 * Fetch requirements (TEMPORARILY DISABLED TO PREVENT CRASH)
 * ------------------------------------------------------------------- */

/* ---------------------------------------------------------------------
 * Fetch requirements assigned to each program
 * ------------------------------------------------------------------- */

$requirementsByProgram = [];

if (!empty($programs)) {

    // Collect program IDs
    $programIds = array_values(array_unique(
        array_map('intval', array_column($programs, 'id'))
    ));

    // Prepare placeholders for the SQL IN clause
    $placeholders = implode(',', array_fill(0, count($programIds), '?'));

    $requirementSql = "
        SELECT
            pr.program_id,
            r.id AS requirement_id,
            r.requirement_name,
            r.description,
            pr.is_required
        FROM program_requirements pr
        INNER JOIN requirements r
            ON pr.requirement_id = r.id
        WHERE pr.program_id IN ($placeholders)
        ORDER BY pr.program_id ASC, r.requirement_name ASC
    ";

    $requirementStmt = $pdo->prepare($requirementSql);
    $requirementStmt->execute($programIds);

    // Initialize an empty requirements list for every program
    foreach ($programs as $program) {
        $requirementsByProgram[$program['id']] = [];
    }

    // Group requirements by program ID
    while ($requirement = $requirementStmt->fetch(PDO::FETCH_ASSOC)) {

        $programId = $requirement['program_id'];

        $requirementsByProgram[$programId][] = $requirement;
    }
}

/* ---------------------------------------------------------------------
 * Filter options
 * ------------------------------------------------------------------- */

$programTypes = $pdo->query(
    "SELECT DISTINCT program_type FROM programs WHERE status IN ('Active','Upcoming') ORDER BY program_type"
)->fetchAll(PDO::FETCH_COLUMN);

$countries = $pdo->query("
    SELECT DISTINCT c.country_name
    FROM countries c
    JOIN partners pr ON pr.country_id = c.id
    JOIN programs p ON p.partner_id = pr.id
    WHERE p.status IN ('Active','Upcoming')
    ORDER BY c.country_name
")->fetchAll(PDO::FETCH_COLUMN);

$terms = $pdo->query("
    SELECT
        CASE
            WHEN MONTH(start_date) BETWEEN 1 AND 4 THEN CONCAT('Spring ', YEAR(start_date))
            WHEN MONTH(start_date) BETWEEN 5 AND 7 THEN CONCAT('Summer ', YEAR(start_date))
            ELSE CONCAT('Fall ', YEAR(start_date))
        END AS term_label,
        MIN(start_date) AS earliest
    FROM programs
    WHERE status IN ('Active','Upcoming')
    GROUP BY term_label
    ORDER BY earliest ASC
")->fetchAll(PDO::FETCH_COLUMN);

function withQuery(array $overrides): string
{
    $current = $_GET;
    $current['page'] = 'available_programs';
    foreach ($overrides as $key => $value) {
        if ($value === null) unset($current[$key]);
        else $current[$key] = $value;
    }
    return '?' . http_build_query($current);
}

$hasFilters = $search !== '' || $filterType !== '' || $filterCountry !== '' || $filterTerm !== '';

$statusMeta = [
    'Active'   => ['label' => 'Active',   'bg' => '#dcfce7', 'fg' => '#166534'],
    'Upcoming' => ['label' => 'Upcoming', 'bg' => '#fef3c7', 'fg' => '#b45309'],
];
?>

<style>
/* Program Page Specific Styles */
.programs-page { padding: 32px 0 48px; }
.pp-header { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; }
.pp-header-icon { width: 48px; height: 48px; border-radius: 12px; background: var(--navy-800); color: var(--gold); display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
.pp-header h2 { margin: 0; color: var(--navy-800); font-family: var(--font-display); font-weight: 800; font-size: 1.4rem; }
.pp-header p { margin: 4px 0 0; color: var(--gray-500); font-size: 0.9rem; }

/* Filter bar */
.filter-bar { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; background: var(--white); border: 1px solid var(--gray-200); border-radius: 16px; padding: 16px 20px; margin-bottom: 24px; box-shadow: var(--shadow-sm); }
.filter-field { display: flex; flex-direction: column; gap: 6px; }
.filter-field label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-500); }
.filter-field select { border: 1px solid var(--gray-200); border-radius: 8px; padding: 10px 32px 10px 12px; font-size: 0.85rem; color: var(--navy-800); background: var(--white); min-width: 150px; appearance: none; background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='6'><path d='M0 0l5 6 5-6z' fill='%2364748B'/></svg>"); background-repeat: no-repeat; background-position: right 12px center; cursor: pointer; font-weight: 500; }
.filter-field select:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(250,204,21,.15); }
.filter-search { margin-left: auto; position: relative; }
.filter-search input { border: 1px solid var(--gray-200); border-radius: 99px; padding: 10px 40px 10px 16px; font-size: 0.85rem; width: 240px; color: var(--navy-800); transition: all 0.2s; }
.filter-search input:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(250,204,21,.15); }
.filter-search button { position: absolute; right: 6px; top: 6px; width: 32px; height: 32px; border: 0; background: none; color: var(--gray-400); cursor: pointer; border-radius: 50%; }
.filter-search button:hover { color: var(--navy-800); background: var(--gray-50); }
.filter-clear { font-size: 0.8rem; color: var(--navy-800); font-weight: 600; display: inline-flex; align-items: center; gap: 6px; padding: 10px 2px; }
.filter-clear:hover { text-decoration: underline; color: var(--gold-dark); }

/* Toolbar */
.pp-toolbar { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
.result-meta { font-size: 0.85rem; color: var(--gray-500); }
.result-meta b { color: var(--navy-800); font-weight: 700; }
.view-toggle { margin-left: auto; display: inline-flex; border: 1px solid var(--gray-200); background: var(--white); border-radius: 10px; overflow: hidden; box-shadow: var(--shadow-sm); }
.view-toggle a { width: 38px; height: 36px; display: grid; place-items: center; color: var(--gray-400); font-size: 0.9rem; transition: all 0.2s; }
.view-toggle a.active { background: var(--navy-800); color: var(--white); }
.pp-perpage select { border: 1px solid var(--gray-200); border-radius: 10px; padding: 8px 30px 8px 12px; font-size: 0.8rem; color: var(--navy-800); background: var(--white); appearance: none; background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='6'><path d='M0 0l5 6 5-6z' fill='%2364748B'/></svg>"); background-repeat: no-repeat; background-position: right 12px center; cursor: pointer; font-weight: 600; box-shadow: var(--shadow-sm); }

/* Layout */
.programs-layout { display: grid; grid-template-columns: minmax(0, 1fr) 280px; gap: 24px; align-items: start; }
.programs-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
.programs-container.list { grid-template-columns: 1fr; }

/* Card */
.program-card { background: var(--white); border: 1px solid var(--gray-200); border-radius: 16px; padding: 20px; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; gap: 14px; transition: all .25s cubic-bezier(0.16, 1, 0.3, 1); }
.program-card:hover { transform: translateY(-4px); box-shadow: var(--shadow); border-color: var(--gray-300); }
.card-top { display: flex; align-items: flex-start; gap: 8px; }
.type-chip { background: #eff6ff; color: var(--blue); font-size: 0.7rem; font-weight: 700; padding: 4px 10px; border-radius: 6px; text-transform: uppercase; letter-spacing: .04em; }
.bookmark-btn { margin-left: auto; border: 0; background: none; color: var(--gray-300); cursor: pointer; font-size: 1.1rem; padding: 0; line-height: 1; transition: color 0.2s; }
.bookmark-btn:hover, .bookmark-btn.saved { color: var(--gold); }
.card-title { margin: 0; font-family: var(--font-display); font-weight: 800; font-size: 1.1rem; line-height: 1.35; color: var(--navy-800); }
.card-meta { display: flex; flex-direction: column; gap: 8px; }
.card-meta span { display: flex; align-items: flex-start; gap: 10px; font-size: 0.8rem; color: var(--gray-600); font-weight: 500; }
.card-meta i { color: var(--gold-dark); width: 14px; margin-top: 3px; font-size: 0.8rem; flex-shrink: 0; }
.card-status { display: flex; align-items: center; gap: 12px; padding-top: 6px; }
.status-dot { font-size: 0.7rem; font-weight: 700; padding: 4px 10px; border-radius: 6px; }
.slot-note { margin-left: auto; font-size: 0.75rem; font-weight: 700; color: var(--blue); }
.slot-note.none { color: var(--red); }
.card-actions { display: flex; gap: 10px; margin-top: auto; padding-top: 12px; border-top: 1px solid var(--gray-100); }
.link-view { flex: 1; border: 1px solid var(--gray-200); background: var(--white); border-radius: 8px; padding: 10px; font-size: 0.8rem; font-weight: 600; color: var(--navy-800); cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
.link-view:hover { background: var(--gray-50); border-color: var(--gold); color: var(--gold-dark); }
.link-view i { color: var(--gold); }

/* List view overrides */
.programs-container.list .program-card { flex-direction: row; align-items: center; flex-wrap: wrap; gap: 16px; }
.programs-container.list .card-top { order: 1; flex: 0 0 auto; }
.programs-container.list .card-title { order: 2; flex: 1 1 200px; }
.programs-container.list .card-meta { order: 3; flex-direction: row; flex-wrap: wrap; gap: 16px; flex: 2 1 300px; }
.programs-container.list .card-status { order: 4; padding-top: 0; }
.programs-container.list .card-actions { order: 5; padding-top: 0; border-top: none; flex: 0 0 auto; }

/* Sidebar */
.pp-aside { display: flex; flex-direction: column; gap: 16px; position: sticky; top: 24px; }
.aside-card { background: var(--white); border: 1px solid var(--gray-200); border-radius: 16px; padding: 20px; box-shadow: var(--shadow-sm); }
.aside-card h4 { margin: 0 0 12px; font-family: var(--font-display); font-weight: 800; font-size: 0.95rem; color: var(--navy-800); display: flex; align-items: center; gap: 8px; }
.aside-card h4 i { color: var(--gold); font-size: 1rem; }
.aside-card p { margin: 0 0 12px; font-size: 0.8rem; color: var(--gray-500); line-height: 1.6; }
.aside-card a.aside-link { font-size: 0.8rem; font-weight: 700; color: var(--blue); display: inline-flex; align-items: center; gap: 6px; transition: gap 0.2s; }
.aside-card a.aside-link:hover { gap: 10px; }
.aside-list { list-style: none; margin: 0 0 16px; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.aside-list li { display: flex; gap: 10px; font-size: 0.8rem; color: var(--gray-600); font-weight: 500; }
.aside-list i { color: var(--green); font-size: 0.85rem; margin-top: 2px; }

/* Empty state */
.programs-empty { text-align: center; padding: 64px 20px; background: var(--white); border: 1px solid var(--gray-200); border-radius: 16px; color: var(--gray-500); box-shadow: var(--shadow-sm); }
.programs-empty i { font-size: 2.5rem; color: var(--gray-300); margin-bottom: 16px; display: block; }
.programs-empty p { margin: 0 0 16px; font-size: 0.9rem; }

/* Pagination */
.pagination { display: flex; align-items: center; gap: 8px; justify-content: center; margin-top: 32px; }
.page-btn { min-width: 36px; height: 36px; padding: 0 12px; border-radius: 8px; border: 1px solid var(--gray-200); background: var(--white); color: var(--navy-800); font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; gap: 6px; box-shadow: var(--shadow-sm); transition: all 0.2s; }
.page-btn:hover { background: var(--gray-50); border-color: var(--gold); }
.page-btn.active { background: var(--navy-800); color: var(--white); border-color: var(--navy-800); }
.page-btn[aria-disabled="true"] { opacity: .4; pointer-events: none; }
.page-ellipsis { color: var(--gray-400); font-size: 0.9rem; padding: 0 4px; }
.pp-footnote { text-align: center; font-size: 0.75rem; color: var(--gray-500); margin-top: 24px; display: flex; align-items: center; justify-content: center; gap: 8px; }

/* Modal Specific Styles */
.modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 9999; padding: 20px; opacity: 0; transition: opacity 0.2s; }
.modal-overlay.active { display: flex; opacity: 1; }
.modal { background: var(--white); width: 100%; max-width: 760px; border-radius: 20px; overflow: hidden; max-height: 90vh; display: flex; flex-direction: column; transform: translateY(20px) scale(0.95); transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25); }
.modal-overlay.active .modal { transform: translateY(0) scale(1); }
.modal-header { background: var(--navy-800); color: var(--white); padding: 24px; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
.modal-header h3 { margin: 0; font-family: var(--font-display); font-size: 1.2rem; font-weight: 800; }
.modal-hero-meta { display: flex; gap: 12px; margin-top: 8px; font-size: 0.8rem; color: rgba(255,255,255,0.7); }
.modal-hero-meta span { display: inline-flex; align-items: center; gap: 6px; }
.modal-close { border: none; background: rgba(255,255,255,0.1); color: var(--white); width: 32px; height: 32px; border-radius: 8px; font-size: 1rem; cursor: pointer; flex-shrink: 0; display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
.modal-close:hover { background: rgba(255,255,255,0.2); }
.modal-body { padding: 24px; overflow-y: auto; }
.modal-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: 16px; }
.modal-field { background: var(--gray-50); padding: 16px; border-radius: 12px; font-size: 0.9rem; color: var(--navy-800); border: 1px solid var(--gray-100); }
.modal-label { display: block; font-size: 0.7rem; color: var(--gray-500); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .06em; font-weight: 700; }
.modal-description { background: var(--gray-50); padding: 20px; border-radius: 12px; margin-top: 16px; font-size: 0.9rem; color: var(--gray-600); line-height: 1.6; border: 1px solid var(--gray-100); }
.modal-footer { margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--gray-100); display: flex; justify-content: space-between; align-items: center; }
.modal-slot-summary { font-size: 0.85rem; color: var(--gray-600); font-weight: 500; display: flex; align-items: center; gap: 8px; }
.btn-apply { display: inline-flex; align-items: center; gap: 8px; padding: 14px 28px; border-radius: 10px; background: var(--gold); color: var(--navy-800); font-weight: 800; font-size: 0.95rem; text-decoration: none; transition: all 0.2s; box-shadow: var(--shadow-sm); }
.btn-apply:hover { background: var(--gold-dark); color: var(--white); transform: translateY(-1px); box-shadow: var(--shadow); }
.btn-apply.disabled { background: var(--gray-200); color: var(--gray-400); pointer-events: none; box-shadow: none; }

/* Requirements List */
.requirement-item { display: flex; align-items: flex-start; gap: 14px; padding: 16px; margin-bottom: 12px; border: 1px solid var(--gray-200); border-radius: 12px; background: var(--white); }
.requirement-icon-box { width: 36px; height: 36px; border-radius: 8px; background: #eff6ff; color: var(--blue); display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0; }
.requirement-info { flex: 1; min-width: 0; }
.requirement-info strong { display: block; color: var(--navy-800); font-size: 0.9rem; font-weight: 700; }
.requirement-info small { display: block; margin-top: 4px; color: var(--gray-500); font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
.requirement-download { display: inline-flex; align-items: center; gap: 6px; margin-top: 10px; color: var(--blue); font-size: 0.8rem; font-weight: 700; text-decoration: none; }
.requirement-download:hover { text-decoration: underline; color: var(--navy-800); }
.requirements-empty { padding: 32px 20px; text-align: center; background: var(--gray-50); border-radius: 12px; color: var(--gray-500); font-size: 0.85rem; border: 1px dashed var(--gray-300); }

@media(max-width:1024px) {
    .programs-layout { grid-template-columns: 1fr; }
    .pp-aside { position: static; display: none; }
}
@media(max-width:600px) {
    .modal-grid { grid-template-columns: 1fr; }
    .filter-bar { flex-direction: column; align-items: stretch; }
    .filter-field select, .filter-search input { min-width: 0; width: 100%; }
    .filter-search { margin-left: 0; }
    .pp-header { flex-wrap: wrap; }
    .modal-footer { flex-direction: column; gap: 16px; align-items: stretch; text-align: center; }
    .btn-apply { justify-content: center; }
}
</style>

<div class="programs-page">

    <div class="pp-header">
        <div class="pp-header-icon"><i class="fa-solid fa-graduation-cap"></i></div>
        <div>
            <h2>Available Programs</h2>
            <p>Browse international mobility opportunities offered by PSU.</p>
        </div>
    </div>

    <form class="filter-bar" method="get">
        <input type="hidden" name="page" value="available_programs">
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <input type="hidden" name="pp" value="<?= (int) $perPage ?>">

        <div class="filter-field">
            <label for="f-type">Program Type</label>
            <select id="f-type" name="type" onchange="this.form.submit()">
                <option value="">All Types</option>
                <?php foreach ($programTypes as $type): ?>
                    <option value="<?= e($type) ?>" <?= $filterType === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-field">
            <label for="f-country">Country</label>
            <select id="f-country" name="country" onchange="this.form.submit()">
                <option value="">All Countries</option>
                <?php foreach ($countries as $country): ?>
                    <option value="<?= e($country) ?>" <?= $filterCountry === $country ? 'selected' : '' ?>><?= e($country) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-field">
            <label for="f-term">Term</label>
            <select id="f-term" name="term" onchange="this.form.submit()">
                <option value="">All Terms</option>
                <?php foreach ($terms as $term): ?>
                    <option value="<?= e($term) ?>" <?= $filterTerm === $term ? 'selected' : '' ?>><?= e($term) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-field">
            <label for="f-sort">Sort by</label>
            <select id="f-sort" name="sort" onchange="this.form.submit()">
                <?php foreach ($sortOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-search">
            <label class="sr-only" for="f-q" hidden>Search programs</label>
            <input id="f-q" type="search" name="q" value="<?= e($search) ?>" placeholder="Search programs...">
            <button type="submit" aria-label="Search"><i class="fa-solid fa-magnifying-glass"></i></button>
        </div>

        <?php if ($hasFilters): ?>
            <a class="filter-clear" href="<?= e(withQuery(['q' => null, 'type' => null, 'country' => null, 'term' => null, 'ppage' => null])) ?>">
                <i class="fa-solid fa-xmark"></i> Clear
            </a>
        <?php endif; ?>
    </form>

    <div class="pp-toolbar">
        <p class="result-meta">
            <?php if ($totalPrograms > 0): ?>
                Showing <b><?= $offset + 1 ?>&ndash;<?= min($offset + $perPage, $totalPrograms) ?></b> of <b><?= $totalPrograms ?></b> program<?= $totalPrograms === 1 ? '' : 's' ?>
            <?php else: ?>
                No matching programs
            <?php endif; ?>
        </p>

        <div class="view-toggle" role="group" aria-label="Layout">
            <a class="<?= $view === 'grid' ? 'active' : '' ?>" href="<?= e(withQuery(['view' => 'grid'])) ?>" aria-label="Grid view"><i class="fa-solid fa-table-cells-large"></i></a>
            <a class="<?= $view === 'list' ? 'active' : '' ?>" href="<?= e(withQuery(['view' => 'list'])) ?>" aria-label="List view"><i class="fa-solid fa-list"></i></a>
        </div>

        <form class="pp-perpage" method="get">
            <?php foreach (['page' => 'available_programs', 'q' => $search, 'type' => $filterType, 'country' => $filterCountry, 'term' => $filterTerm, 'sort' => $sort, 'view' => $view] as $k => $v): ?>
                <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
            <?php endforeach; ?>
            <label class="sr-only" for="f-pp" hidden>Programs per page</label>
            <select id="f-pp" name="pp" onchange="this.form.submit()">
                <?php foreach ($perPageOptions as $option): ?>
                    <option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= $option ?> per page</option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="programs-layout">
        <div>
            <?php if (empty($programs)): ?>
                <div class="programs-empty">
                    <i class="fa-regular fa-compass"></i>
                    <p>No programs match your search. Try clearing your filters to see everything that's open.</p>
                    <?php if ($hasFilters): ?>
                        <a class="filter-clear" href="<?= e(withQuery(['q' => null, 'type' => null, 'country' => null, 'term' => null, 'ppage' => null])) ?>">
                            <i class="fa-solid fa-xmark"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="programs-container <?= $view === 'list' ? 'list' : '' ?>">
                    <?php foreach ($programs as $program):
                        $meta      = $statusMeta[$program['status']] ?? ['label' => $program['status'], 'bg' => '#f1f2f5', 'fg' => '#555'];
                        $slotsLeft = (int) $program['slots_available'];
                        $isFull    = (int) $program['total_slots'] > 0 && $slotsLeft === 0;
                    ?>
                    <article class="program-card">
                        <div class="card-top">
                            <span class="type-chip"><?= e($program['program_type']) ?></span>
                            <button type="button" class="bookmark-btn" data-save-id="<?= (int) $program['id'] ?>" aria-label="Save program" aria-pressed="false">
                                <i class="fa-regular fa-bookmark"></i>
                            </button>
                        </div>

                        <h3 class="card-title"><?= e($program['program_name']) ?></h3>

                        <div class="card-meta">
                            <span><i class="fa-solid fa-building"></i><?= e($program['partner_institution'] ?? 'Partner TBD') ?></span>
                            <span><i class="fa-solid fa-location-dot"></i><?= e($program['country'] ?? 'TBD') ?></span>
                            <span><i class="fa-solid fa-layer-group"></i><?= e($program['term_label']) ?></span>
                            <span><i class="fa-regular fa-clock"></i>
                                <?= date('M d, Y', strtotime($program['start_date'])) ?> &ndash;
                                <?= $program['end_date'] ? date('M d, Y', strtotime($program['end_date'])) : 'TBD' ?>
                            </span>
                        </div>

                        <div class="card-status">
                            <span class="status-dot" style="background:<?= e($meta['bg']) ?>;color:<?= e($meta['fg']) ?>"><?= e($meta['label']) ?></span>
                            <?php if ((int) $program['total_slots'] > 0): ?>
                                <span class="slot-note <?= $isFull ? 'none' : '' ?>">
                                    <?= $isFull ? 'Slots full' : $slotsLeft . ' slot' . ($slotsLeft === 1 ? '' : 's') . ' available' ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="card-actions">
                            <button type="button" class="link-view" data-program='<?= e(json_encode($program, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG)) ?>' data-requirements='<?= e(json_encode($requirementsByProgram[$program['id']] ?? [], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG)) ?>' onclick="viewProgram(this)">
                                <i class="fa-solid fa-eye"></i> View Details
                            </button>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="pagination" aria-label="Program results pages">
                        <a class="page-btn" aria-disabled="<?= $currentPage <= 1 ? 'true' : 'false' ?>" href="<?= e(withQuery(['ppage' => max(1, $currentPage - 1)])) ?>" aria-label="Previous page">
                            <i class="fa-solid fa-arrow-left"></i>
                        </a>
                        <?php
                        $window = 1;
                        for ($i = 1; $i <= $totalPages; $i++):
                            $show = $i === 1 || $i === $totalPages || abs($i - $currentPage) <= $window;
                            if (!$show):
                                if ($i === 2 || $i === $totalPages - 1): ?>
                                    <span class="page-ellipsis">&hellip;</span>
                                <?php endif;
                                continue;
                            endif;
                        ?>
                            <a class="page-btn <?= $i === $currentPage ? 'active' : '' ?>" href="<?= e(withQuery(['ppage' => $i])) ?>" <?= $i === $currentPage ? 'aria-current="page"' : '' ?>><?= $i ?></a>
                        <?php endfor; ?>
                        <a class="page-btn" aria-disabled="<?= $currentPage >= $totalPages ? 'true' : 'false' ?>" href="<?= e(withQuery(['ppage' => min($totalPages, $currentPage + 1)])) ?>">
                            Next <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>

            <p class="pp-footnote">
                <i class="fa-regular fa-circle-question"></i>
                Programs and slot availability are subject to change. Please check back regularly for updates.
            </p>
        </div>

        <aside class="pp-aside">
            <div class="aside-card">
                <h4><i class="fa-regular fa-clipboard"></i> Application Reminders</h4>
                <ul class="aside-list">
                    <li><i class="fa-solid fa-check"></i> Check program requirements</li>
                    <li><i class="fa-solid fa-check"></i> Prepare required documents</li>
                    <li><i class="fa-solid fa-check"></i> Submit before the deadline</li>
                    <li><i class="fa-solid fa-check"></i> Wait for confirmation</li>
                </ul>
                <a class="aside-link" href="?page=upload_documents">Upload documents <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </aside>
    </div>
</div>

<!-- PROGRAM DETAILS MODAL -->
<div class="modal-overlay" id="programModal" role="dialog" aria-modal="true" aria-labelledby="modalProgramName">
    <div class="modal">
        <div class="modal-header">
            <div>
                <h3 id="modalProgramName"></h3>
                <div class="modal-hero-meta">
                    <span id="modalHeroType"></span>
                    <span id="modalHeroCountry"></span>
                    <span id="modalHeroTerm"></span>
                </div>
            </div>
            <button type="button" class="modal-close" onclick="closeModal()" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">
            <h4 style="color:var(--navy-800);margin:0 0 16px;font-family:var(--font-display);font-weight:800;font-size:1rem;">Program Information</h4>

            <div class="modal-grid">
                <div class="modal-field">
                    <span class="modal-label">Partner Institution</span>
                    <span id="modalPartner"></span>
                </div>
                <div class="modal-field">
                    <span class="modal-label">Country</span>
                    <span id="modalCountry"></span>
                </div>
                <div class="modal-field">
                    <span class="modal-label">Program Type</span>
                    <span id="modalType"></span>
                </div>
                <div class="modal-field">
                    <span class="modal-label">Term</span>
                    <span id="modalTerm"></span>
                </div>
                <div class="modal-field">
                    <span class="modal-label">Duration</span>
                    <span id="modalDuration"></span>
                </div>
                <div class="modal-field">
                    <span class="modal-label">Available Slots</span>
                    <span id="modalSlots"></span>
                </div>
            </div>

            <div class="modal-description">
                <strong style="display:block;color:var(--navy-800);margin-bottom:8px;font-family:var(--font-display);">Program Description</strong>
                <div id="modalDescription"></div>
            </div>

            <div style="margin-top:24px;">
                <h4 style="color:var(--navy-800);margin:0 0 16px;font-family:var(--font-display);font-weight:800;font-size:1rem;"><i class="fa-solid fa-clipboard-list"></i> Program Requirements</h4>
                <div id="modalRequirements"></div>
            </div>

            <div class="modal-footer">
                <div class="modal-slot-summary" id="modalSlotSummary"></div>
                <a href="#" id="applyLink" class="btn-apply">
                    Apply Now <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const overlay = document.getElementById('programModal');
    let lastFocused = null;

    window.viewProgram = function (button) {
        let program = {};
        let requirements = [];

        try {
            program = JSON.parse(button.getAttribute('data-program') || '{}');
            requirements = JSON.parse(button.getAttribute('data-requirements') || '[]');
        } catch (error) {
            console.error('Unable to load program information:', error);
            return;
        }

        const set = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        };

        const total = Number(program.total_slots) || 0;
        const left = Number(program.slots_available) || 0;
        const isFull = total > 0 && left <= 0;
        const isLow = total > 0 && left > 0 && left <= 3;

        // Hero header
        set('modalProgramName', program.program_name || 'Program');
        set('modalHeroType', program.program_type || 'Program');
        
        const heroCountry = document.getElementById('modalHeroCountry');
        heroCountry.innerHTML = '<i class="fa-solid fa-location-dot"></i> ' + escapeHtml(program.country || 'TBD');

        const heroTerm = document.getElementById('modalHeroTerm');
        heroTerm.innerHTML = '<i class="fa-solid fa-layer-group"></i> ' + escapeHtml(program.term_label || 'TBD');

        // Details grid
        set('modalPartner', program.partner_institution || 'TBD');
        set('modalCountry', program.country || 'TBD');
        set('modalType', program.program_type || 'TBD');
        set('modalTerm', program.term_label || 'TBD');
        set('modalDuration', formatDate(program.start_date) + ' – ' + formatDate(program.end_date));
        set('modalSlots', total > 0 ? left + ' of ' + total + ' available' : 'Not specified');
        set('modalDescription', program.description || 'No description available.');

        // Slot summary in footer
        const summary = document.getElementById('modalSlotSummary');
        if (total > 0) {
            if (isFull) {
                summary.innerHTML = '<i class="fa-solid fa-ban" style="color:#dc2626;"></i> <strong>All slots filled</strong>';
            } else if (isLow) {
                summary.innerHTML = '<i class="fa-solid fa-triangle-exclamation" style="color:#b45309;"></i> Only <strong>' + left + '</strong> slot' + (left === 1 ? '' : 's') + ' left';
            } else {
                summary.innerHTML = '<i class="fa-solid fa-users" style="color:#3730a3;"></i> <strong>' + left + '</strong> of ' + total + ' slots available';
            }
        } else {
            summary.textContent = '';
        }

        // Requirements
        const requirementsList = document.getElementById('modalRequirements');
        requirementsList.replaceChildren();

        if (requirements.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'requirements-empty';
            empty.textContent = 'No requirements have been assigned to this program yet.';
            requirementsList.appendChild(empty);
        } else {
            requirements.forEach(requirement => {
                const item = document.createElement('div');
                item.className = 'requirement-item';

                const iconContainer = document.createElement('div');
                iconContainer.className = 'requirement-icon-box';
                const icon = document.createElement('i');
                icon.className = 'fa-solid fa-file-lines';
                iconContainer.appendChild(icon);

                const info = document.createElement('div');
                info.className = 'requirement-info';

                const name = document.createElement('strong');
                name.textContent = requirement.requirement_name || 'Unnamed Requirement';

                const status = document.createElement('small');
                // If your database doesn't have is_required, this defaults to Required visually
                status.textContent = Number(requirement.is_required) === 0 ? 'Optional' : 'Required';

                info.append(name, status);

                // Check if description exists to show it
                if (requirement.description) {
                    const desc = document.createElement('div');
                    desc.style.fontSize = '0.8rem';
                    desc.style.color = 'var(--gray-500)';
                    desc.style.marginTop = '6px';
                    desc.textContent = requirement.description;
                    info.appendChild(desc);
                }

                if (requirement.requirement_file) {
                    const download = document.createElement('a');
                    download.className = 'requirement-download';
                    download.href = requirement.requirement_file;
                    download.innerHTML = '<i class="fa-solid fa-download"></i> Download Requirement';
                    info.appendChild(download);
                }

                item.append(iconContainer, info);
                requirementsList.appendChild(item);
            });
        }

        // Apply Button
        const applyButton = document.getElementById('applyLink');
        if (isFull) {
            applyButton.removeAttribute('href');
            applyButton.textContent = 'Slots Full';
            applyButton.classList.add('disabled');
        } else {
            applyButton.href = '?page=apply_program&program_id=' + encodeURIComponent(program.id);
            applyButton.innerHTML = 'Apply Now <i class="fa-solid fa-arrow-right"></i>';
            applyButton.classList.remove('disabled');
        }

        lastFocused = button;
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        document.getElementById('modalProgramName').focus();
    };

    window.closeModal = function () {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
        if (lastFocused) lastFocused.focus();
    };

    function formatDate(date) {
        if (!date) return 'TBD';
        return new Date(date + 'T00:00:00').toLocaleDateString('en-US', {
            year: 'numeric', month: 'short', day: 'numeric'
        });
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    overlay.addEventListener('click', function (event) {
        if (event.target === overlay) closeModal();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && overlay.classList.contains('active')) closeModal();
    });

    // SAVED PROGRAMS
    const STORE = 'psu_saved_programs';
    let saved = new Set();
    try { saved = new Set(JSON.parse(localStorage.getItem(STORE) || '[]')); } catch (error) { saved = new Set(); }

    document.querySelectorAll('[data-save-id]').forEach(btn => {
        const id = btn.dataset.saveId;
        paint(btn, saved.has(id));
        btn.addEventListener('click', function () {
            if (saved.has(id)) saved.delete(id);
            else saved.add(id);
            localStorage.setItem(STORE, JSON.stringify([...saved]));
            paint(btn, saved.has(id));
        });
    });

    function paint(btn, on) {
        btn.classList.toggle('saved', on);
        btn.setAttribute('aria-pressed', String(on));
        btn.querySelector('i').className = on ? 'fa-solid fa-bookmark' : 'fa-regular fa-bookmark';
    }
})();
</script>