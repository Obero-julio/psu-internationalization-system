<?php
require_once __DIR__ . '/../config/db.php';

$pdo = db();

$limit  = 10;
$page   = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $limit;

if (!function_exists('h')) {
    function h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/* DELETE PROGRAM */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM programs WHERE id = ?")->execute([$id]);
    echo "<script>window.location.href='dashboard.php?page=programs';</script>";
    exit();
}

/* LOAD DROPDOWN DATA */
$programTypes = $pdo->query("SELECT id, category_name FROM program_category WHERE status = 'Active' ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch partners
$partners = $pdo->query("SELECT id, country_id, institution_name FROM partners WHERE status = 'Active' ORDER BY institution_name")->fetchAll(PDO::FETCH_ASSOC);

// Get ONLY the unique country_ids that exist in the partners table
$partnerCountryIds = array_unique(array_column($partners, 'country_id'));

// Fetch the names of those specific countries
$countries = [];
if (!empty($partnerCountryIds)) {
    $inQuery = implode(',', array_fill(0, count($partnerCountryIds), '?'));
    $stmtCountries = $pdo->prepare("SELECT id, country_name FROM countries WHERE id IN ($inQuery) ORDER BY country_name");
    $stmtCountries->execute($partnerCountryIds);
    $countries = $stmtCountries->fetchAll(PDO::FETCH_ASSOC);
}

// Build a lookup map: [ 5 => 'Vietnam', 174 => 'Philippines' ]
$countryMap = [];
foreach ($countries as $c) {
    $countryMap[$c['id']] = $c['country_name'];
}

// Count ONLY programs that have a valid country
$totalPrograms = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM programs p 
    INNER JOIN countries c ON p.country_id = c.id
")->fetchColumn();
$totalPages = max(1, ceil($totalPrograms / $limit));

// Fetch the programs using INNER JOIN
// If the country_id is broken, the program will NOT show up here.
$stmt = $pdo->prepare("
    SELECT
        p.*,
        pr.institution_name
    FROM programs p
    INNER JOIN countries c ON p.country_id = c.id
    LEFT JOIN partners pr ON p.partner_id = pr.id
    ORDER BY p.id DESC
    LIMIT :limit OFFSET :offset
");

$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="../asset/css/programs.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<div class="content">
    <div class="page-header">
        <h2>Program Management</h2>
        <p>Manage academic programs and course offerings</p>
    </div>

    <div class="toolbar">
        <div class="search-wrap">
            <i class="fa fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search programs...">
        </div>
        <button type="button" class="create-btn" id="btnAddProgram">
            <i class="fa fa-plus"></i> Add Program
        </button>
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Program Name</th>
                    <th>Type</th>
                    <th>Country</th>
                    <th>Partner</th>
                    <th>Status</th>
                    <th>Duration</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="tableBody">
            <?php if ($programs): ?>
                <?php foreach ($programs as $p): ?>
                    <tr data-name="<?= strtolower(h($p['program_name'])) ?>">
                        <td>
                            <div class="program-name">
                                <div class="program-icon">
                                    <i class="fa fa-graduation-cap"></i>
                                </div>
                                <div class="program-info">
                                    <strong><?= h($p['program_name']) ?></strong>
                                </div>
                            </div>
                        </td>
                        <td><?= h($p['program_type']) ?></td>
                        
                        <!-- DISPLAYS THE COUNTRY NAME MAPPED FROM THE ID -->
                        <td><?= h($countryMap[$p['country_id']] ?? '') ?></td>
                        
                        <td><?= h($p['institution_name']) ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower(h($p['status'])) ?>">
                                <?= h($p['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?= date('M d, Y', strtotime($p['start_date'])) ?> - <?= date('M d, Y', strtotime($p['end_date'])) ?>
                        </td>
                        <td>
                            <button type="button" class="action-btn edit-btn" data-program='<?= htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8") ?>'>
                                <i class="fa fa-pen"></i>
                            </button>
                            <a href="?page=programs&delete=<?= $p['id'] ?>" onclick="return confirm('Delete this program?')" class="action-btn delete-btn">
                                <i class="fa fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                                    <path d="M6 12v5c3 3 9 3 12 0v-5"/>
                                </svg>
                            </div>
                            <h3>No programs yet</h3>
                            <p>Click "Add Program" to create your first one.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>

        <div class="pagination">
            <span>Showing <?= count($programs) ?> of <?= $totalPrograms ?> programs</span>
            <div class="pages">
                <?php $baseQ = array_merge($_GET, []); unset($baseQ['p'], $baseQ['delete']); ?>
                <?php if ($page > 1): ?>
                    <a class="page-arrow" href="?<?= h(http_build_query(array_merge($baseQ, ['p' => $page - 1]))) ?>"><i class="fa fa-angle-left"></i></a>
                <?php else: ?>
                    <button class="page-arrow" disabled><i class="fa fa-angle-left"></i></button>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?<?= h(http_build_query(array_merge($baseQ, ['p' => $i]))) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a class="page-arrow" href="?<?= h(http_build_query(array_merge($baseQ, ['p' => $page + 1]))) ?>"><i class="fa fa-angle-right"></i></a>
                <?php else: ?>
                    <button class="page-arrow" disabled><i class="fa fa-angle-right"></i></button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ========================= EDIT PROGRAM MODAL ========================= -->
<div id="editProgramModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-icon"><i class="fa fa-pen-to-square"></i></div>
                <div>
                    <h3>Edit Program</h3>
                    <p class="modal-subtitle">Update program details</p>
                </div>
            </div>
            <button type="button" class="modal-close" data-close="editProgramModal"><i class="fa fa-times"></i></button>
        </div>

        <form id="editProgramForm">
            <input type="hidden" name="id" id="edit_id">

            <div class="modal-body">
                <div class="form-group">
                    <label>Program Name</label>
                    <input type="text" name="program_name" id="edit_program_name" class="form-input" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Program Type</label>
                        <select name="program_type" id="edit_program_type" class="form-input" required>
                            <option value="">Select Program Type</option>
                            <?php foreach ($programTypes as $type): ?>
                                <option value="<?= h($type['category_name']) ?>"><?= h($type['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="edit_status" class="form-input" required>
                            <option value="Active">Active</option>
                            <option value="Upcoming">Upcoming</option>
                            <option value="Completed">Completed</option>
                            <option value="Suspended">Suspended</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Country</label>
                        <select name="country_id" id="edit_program_country" class="form-input" required>
                            <option value="">Select Country</option>
                            <?php foreach ($countries as $country): ?>
                                <option value="<?= h($country['id']) ?>"><?= h($country['country_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Partner Institution</label>
                        <select name="partner_id" id="edit_program_partner" class="form-input" required>
                            <option value="">Select Country First</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" id="edit_start" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" id="edit_end" class="form-input" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description" class="form-input" required></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" data-close="editProgramModal">Cancel</button>
                <button type="submit" class="btn-confirm"><i class="fa fa-floppy-disk"></i> Update Program</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================= ADD PROGRAM MODAL ========================= -->
<div id="addProgramModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-icon"><i class="fa fa-graduation-cap"></i></div>
                <div>
                    <h3>Add Program</h3>
                    <p class="modal-subtitle">Fill in the details below to create a new program.</p>
                </div>
            </div>
            <button type="button" class="modal-close" data-close="addProgramModal"><i class="fa fa-times"></i></button>
        </div>

        <form id="addProgramForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label"><i class="fa fa-book"></i> Program Name</label>
                    <input type="text" name="program_name" class="form-input" placeholder="e.g. International Student Exchange" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fa fa-tag"></i> Program Type</label>
                        <select name="program_type" class="form-input" required>
                            <option value="">Select Program Type</option>
                            <?php foreach ($programTypes as $type): ?>
                                <option value="<?= h($type['category_name']) ?>"><?= h($type['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa fa-circle-dot"></i> Status</label>
                        <select name="status" class="form-input" required>
                            <option value="Active">Active</option>
                            <option value="Upcoming">Upcoming</option>
                            <option value="Completed">Completed</option>
                            <option value="Suspended">Suspended</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fa fa-globe"></i> Country</label>
                        <select name="country_id" id="add_program_country" class="form-input" required>
                            <option value="">Select Country</option>
                            <?php foreach ($countries as $country): ?>
                                <option value="<?= h($country['id']) ?>"><?= h($country['country_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa fa-building"></i> Partner Institution</label>
                        <select name="partner_id" id="add_program_partner" class="form-input" required>
                            <option value="">Select Country First</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fa fa-calendar"></i> Start Date</label>
                        <input type="date" name="start_date" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa fa-calendar-check"></i> End Date</label>
                        <input type="date" name="end_date" class="form-input" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fa fa-align-left"></i> Description</label>
                    <textarea name="description" class="form-input" placeholder="Brief description of the program..." required></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" data-close="addProgramModal">Cancel</button>
                <button type="submit" class="btn-confirm"><i class="fa fa-floppy-disk"></i> Save Program</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const allPartners = <?= json_encode($partners) ?>;

    // 2. SEARCH
    document.getElementById('searchInput').addEventListener('input', function () {
        const search = this.value.toLowerCase().trim();
        document.querySelectorAll('#tableBody tr').forEach(row => {
            const name = (row.dataset.name || '').toLowerCase();
            row.style.display = name.includes(search) ? '' : 'none';
        });
    });

    // 3. MODAL HELPERS
    function openModal(id) {
        document.getElementById(id).classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('[data-close]').forEach(btn => {
        btn.addEventListener('click', function() { closeModal(this.dataset.close); });
    });

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) closeModal(this.id);
        });
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(m => closeModal(m.id));
        }
    });

    // 4. POPULATE PARTNER DROPDOWN
    function populatePartners(countryId, selectId, selectedPartnerId = null) {
        const select = document.getElementById(selectId);
        select.innerHTML = '<option value="">Select Partner</option>';
        
        if (!countryId) {
            select.innerHTML = '<option value="">Select Country First</option>';
            return;
        }

        const cidStr = String(countryId).trim();
        
        const filtered = allPartners.filter(p => {
            return String(p.country_id).trim() === cidStr;
        });
        
        if (filtered.length === 0) {
            select.innerHTML = '<option value="">No partners in this country</option>';
            return;
        }

        filtered.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.institution_name;
            
            if (selectedPartnerId && String(p.id) === String(selectedPartnerId)) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
    }

    // 5. ADD PROGRAM
    document.getElementById('btnAddProgram').addEventListener('click', function() {
        document.getElementById('addProgramForm').reset();
        document.getElementById('add_program_partner').innerHTML = '<option value="">Select Country First</option>';
        openModal('addProgramModal');
    });

    document.getElementById('add_program_country').addEventListener('change', function() {
        populatePartners(this.value, 'add_program_partner');
    });

    document.getElementById('addProgramForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const start = this.querySelector('[name="start_date"]').value;
        const end = this.querySelector('[name="end_date"]').value;
        if (start && end && end < start) {
            showToast('error', 'End Date cannot be before Start Date.');
            return; 
        }

        submitForm(this, 'add_program_modal.php', 'addProgramModal');
    });

    // 6. EDIT PROGRAM
    document.getElementById('tableBody').addEventListener('click', function(e) {
        const editBtn = e.target.closest('.edit-btn');
        if (!editBtn) return;

        const data = JSON.parse(editBtn.dataset.program);
        
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_program_name').value = data.program_name;
        document.getElementById('edit_program_type').value = data.program_type;
        document.getElementById('edit_status').value = data.status;
        document.getElementById('edit_start').value = data.start_date;
        document.getElementById('edit_end').value = data.end_date;
        document.getElementById('edit_description').value = data.description;

        document.getElementById('edit_program_country').value = data.country_id;
        
        populatePartners(data.country_id, 'edit_program_partner', data.partner_id);

        openModal('editProgramModal');
    });

    document.getElementById('edit_program_country').addEventListener('change', function() {
        populatePartners(this.value, 'edit_program_partner');
    });

    document.getElementById('editProgramForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const start = this.querySelector('[name="start_date"]').value;
        const end = this.querySelector('[name="end_date"]').value;
        if (start && end && end < start) {
            showToast('error', 'End Date cannot be before Start Date.');
            return; 
        }

        submitForm(this, 'update_program.php', 'editProgramModal');
    });

    // 7. SUBMIT FORM HELPER
    function submitForm(form, endpoint, modalId) {
        const btn = form.querySelector('[type="submit"]');
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';

        fetch(endpoint, { method: 'POST', body: new FormData(form) })
            .then(async res => {
                const text = await res.text();
                try { return JSON.parse(text); }
                catch { throw new Error('Invalid JSON response'); }
            })
            .then(data => {
                showToast(data.success ? 'success' : 'error', data.message);
                if (data.success) {
                    closeModal(modalId);
                    setTimeout(() => location.reload(), 1000);
                }
            })
            .catch((err) => {
                console.error("Error saving:", err);
                showToast('error', 'Server error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = original;
            });
    }

    // 8. TOAST
    function showToast(type, message) {
        const existing = document.querySelector('.toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = `toast toast--${type}`;
        toast.innerHTML = `<i class="fa fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i><span></span>`;
        toast.querySelector('span').textContent = message;

        document.body.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('toast--show'));

        setTimeout(() => {
            toast.classList.remove('toast--show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
});
</script>