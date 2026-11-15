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

function formatContactName(?string $first, ?string $mi, ?string $last): string {
    $first = trim((string)$first);
    $mi    = trim((string)$mi);
    $last  = trim((string)$last);
    $parts = [];
    if ($first !== '') $parts[] = $first;
    if ($mi !== '')    $parts[] = strtoupper($mi) . '.';
    if ($last !== '')  $parts[] = $last;
    return $parts ? implode(' ', $parts) : 'N/A';
}

/* SAFE DELETE */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];
    $pdo->prepare("DELETE FROM partners WHERE id = ?")->execute([$id]);
    header("Location: dashboard.php?page=partners");
    exit();
}

$totalPartners = (int)$pdo->query("SELECT COUNT(*) FROM partners")->fetchColumn();
$totalPages = max(1, ceil($totalPartners / $limit));

$countryStmt = $pdo->query("
    SELECT id, country_name
    FROM countries_addresses
    WHERE status = 'active'
    ORDER BY country_name
");
$countries = $countryStmt->fetchAll(PDO::FETCH_ASSOC);

/* FETCH PARTNERS */
$stmt = $pdo->prepare("
SELECT
    p.id, p.institution_name, c.country_name,
    p.contact_first_name, p.contact_middle_name, p.contact_last_name, p.contact_email,
    a.agreement_name, p.expiry_date, p.status, p.notes
FROM partners p
LEFT JOIN countries c ON p.country_id = c.id
LEFT JOIN agreement_types a ON p.agreement_type_id = a.id
ORDER BY p.id DESC
LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$partners = $stmt->fetchAll(PDO::FETCH_ASSOC);

$agreementTypes = $pdo->query("
    SELECT id, agreement_name
    FROM agreement_types
    WHERE status = 'Active'
    ORDER BY agreement_name
")->fetchAll(PDO::FETCH_ASSOC);

function expiryClass(?string $date): string {
    if (empty($date)) return '';
    $timestamp = strtotime($date);
    if ($timestamp === false) return '';
    $days = ($timestamp - time()) / 86400;
    if ($days < 0) return 'expiry-expired';
    if ($days <= 30) return 'expiry-soon';
    return '';
}
?>

<link rel="stylesheet" href="../asset/css/partners.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<div class="content">
    <div class="page-header">
        <h2>Partner Institution Management</h2>
        <p>Manage international partner database, MOA/MOU agreements, collaboration records, and institutional profiles.</p>
    </div>

    <div class="toolbar">
        <div class="search-wrap">
            <i class="fa fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search institutions...">
        </div>
        <button type="button" class="create-btn" id="btnAddPartner">
            <i class="fa fa-plus"></i> Add Partner
        </button>
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Institution</th>
                    <th>Country</th>
                    <th>Contact Person</th>
                    <th>Agreement Type</th>
                    <th>Expiry</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="tableBody">
            <?php if (!empty($partners)): ?>
                <?php foreach ($partners as $partner):
                    $contactName = formatContactName(
                        $partner['contact_first_name'] ?? '',
                        $partner['contact_middle_name'] ?? '',
                        $partner['contact_last_name'] ?? ''
                    );
                    $searchData = strtolower(
                        $partner['institution_name'] . ' ' .
                        $partner['country_name'] . ' ' .
                        $contactName
                    );
                    $status = strtolower($partner['status'] ?? 'active');
                    $expClass = expiryClass($partner['expiry_date'] ?? null);
                    $jsonData = htmlspecialchars(json_encode($partner), ENT_QUOTES, 'UTF-8');
                ?>
                <tr data-name="<?= h($searchData) ?>">
                    <td>
                        <div class="institution-cell">
                            <div class="institution-icon">
                                <i class="fa fa-handshake"></i>
                            </div>
                            <div class="institution-info">
                                <strong><?= h($partner['institution_name']) ?></strong>
                            </div>
                        </div>
                    </td>
                    <td><?= h($partner['country_name']) ?></td>
                    <td>
                        <div class="contact-cell">
                            <strong><?= h($contactName) ?></strong>
                            <?php if (!empty($partner['contact_email'])): ?>
                                <small><?= h($partner['contact_email']) ?></small>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><?= h($partner['agreement_name'] ?: 'N/A') ?></td>
                    <td>
                        <span class="expiry-cell <?= $expClass ?>">
                            <?= !empty($partner['expiry_date']) ? date('M d, Y', strtotime($partner['expiry_date'])) : 'N/A' ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge status-<?= h($status) ?>">
                            <?= ucfirst($status) ?>
                        </span>
                    </td>
                    <td>
                        <div class="actions">
                            <button type="button" class="action-btn view-btn" title="View" data-partner='<?= $jsonData ?>'>
                                <i class="fa fa-eye"></i>
                            </button>
                            <button type="button" class="action-btn edit-btn" title="Edit" data-partner='<?= $jsonData ?>'>
                                <i class="fa fa-pen"></i>
                            </button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this partner?');">
                                <input type="hidden" name="delete_id" value="<?= (int)$partner['id'] ?>">
                                <button type="submit" class="action-btn delete-btn" title="Delete">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/>
                                </svg>
                            </div>
                            <h3>No partner institutions found</h3>
                            <p>Click "Add Partner" to register your first international partner.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>

        <div class="pagination">
            <span id="paginationInfo">Showing <?= count($partners) ?> of <?= $totalPartners ?> partners</span>
            <div class="pages">
                <?php $baseQ = array_merge($_GET, []); unset($baseQ['p']); ?>
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

<!-- ===================== ADD MODAL ===================== -->
<div id="addModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-icon"><i class="fa fa-handshake"></i></div>
                <div>
                    <h3>Add Partner Institution</h3>
                    <p class="modal-subtitle">Register a new international partner.</p>
                </div>
            </div>
            <button class="modal-close" data-close="addModal"><i class="fa fa-times"></i></button>
        </div>

        <form id="addForm">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fa fa-globe"></i> Country *</label>
                        <select name="country_id" id="addCountrySelect" class="form-input" required>
                            <option value="">Select Country</option>
                            <?php foreach ($countries as $country): ?>
                                <option value="<?= (int)$country['id'] ?>"><?= h($country['country_name']) ?></option>
                            <?php endforeach; ?>
                            <option value="__other__">Other (enter manually)</option>
                        </select>
                        <input type="text" name="country_manual" id="addCountryManual" class="form-input" placeholder="Enter country name" style="display:none;margin-top:8px">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa fa-building"></i> Institution Name *</label>
                        <input type="text" name="institution_name" class="form-input" placeholder="Enter Institution Name" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fa fa-file-contract"></i> Agreement Type</label>
                        <select name="agreement_type" class="form-input">
                            <option value="">Select Agreement Type</option>
                            <?php foreach ($agreementTypes as $type): ?>
                                <option value="<?= h($type['agreement_name']) ?>"><?= h($type['agreement_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa fa-circle-dot"></i> Status</label>
                        <select name="status" class="form-input" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fa fa-user"></i> Contact Person</label>
                    <div class="form-row" style="grid-template-columns: 1fr 70px 1fr;">
                        <input type="text" name="contact_first_name" class="form-input" placeholder="First name" required maxlength="100">
                        <input type="text" name="contact_mi" class="form-input" placeholder="M.I." maxlength="1" style="text-align:center;">
                        <input type="text" name="contact_last_name" class="form-input" placeholder="Last name" required maxlength="100">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fa fa-envelope"></i> Contact Email</label>
                    <input type="email" name="contact_email" class="form-input" placeholder="e.g. jsmith@univ.edu">
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fa fa-calendar-xmark"></i> Expiry Date</label>
                    <input type="date" name="expiry_date" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fa fa-note-sticky"></i> Notes</label>
                    <textarea name="notes" class="form-input" placeholder="Any additional notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" data-close="addModal">Cancel</button>
                <button type="submit" class="btn-confirm"><i class="fa fa-floppy-disk"></i> Save Partner</button>
            </div>
        </form>
    </div>
</div>

<!-- ===================== EDIT MODAL ===================== -->
<div id="editModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-icon"><i class="fa fa-pen-to-square"></i></div>
                <div>
                    <h3>Edit Partner Institution</h3>
                    <p class="modal-subtitle" id="editModalSubtitle">Update partner record details.</p>
                </div>
            </div>
            <button class="modal-close" data-close="editModal"><i class="fa fa-times"></i></button>
        </div>

        <form id="editForm">
            <input type="hidden" name="edit_id" id="editId">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fa fa-globe"></i> Country *</label>
                        <select id="editCountry" name="country_id" class="form-input" required>
                            <option value="">Select Country</option>
                            <?php foreach ($countries as $country): ?>
                                <option value="<?= (int)$country['id'] ?>"><?= h($country['country_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa fa-building"></i> Institution Name *</label>
                        <input type="text" id="editInstitution" name="institution_name" class="form-input" placeholder="Enter Institution Name" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fa fa-file-contract"></i> Agreement Type</label>
                        <select name="agreement_type" id="editAgreement" class="form-input">
                            <option value="">Select Agreement Type</option>
                            <?php foreach ($agreementTypes as $type): ?>
                                <option value="<?= h($type['agreement_name']) ?>"><?= h($type['agreement_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa fa-circle-dot"></i> Status</label>
                        <select name="status" id="editStatus" class="form-input" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fa fa-user"></i> Contact Person</label>
                    <div class="form-row" style="grid-template-columns: 1fr 70px 1fr;">
                        <input type="text" name="contact_first_name" id="editContactFirst" class="form-input" placeholder="First name" required maxlength="100">
                        <input type="text" name="contact_mi" id="editContactMi" class="form-input" placeholder="M.I." maxlength="1" style="text-align:center;">
                        <input type="text" name="contact_last_name" id="editContactLast" class="form-input" placeholder="Last name" required maxlength="100">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fa fa-envelope"></i> Contact Email</label>
                    <input type="email" name="contact_email" id="editEmail" class="form-input" placeholder="e.g. jsmith@univ.edu">
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fa fa-calendar-xmark"></i> Expiry Date</label>
                    <input type="date" name="expiry_date" id="editExpiry" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fa fa-note-sticky"></i> Notes</label>
                    <textarea name="notes" id="editNotes" class="form-input" placeholder="Any additional notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" data-close="editModal">Cancel</button>
                <button type="submit" class="btn-confirm"><i class="fa fa-floppy-disk"></i> Update Partner</button>
            </div>
        </form>
    </div>
</div>

<!-- ===================== VIEW MODAL ===================== -->
<div id="viewModal" class="modal-overlay">
    <div class="modal-box" style="max-width: 600px;">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-icon" style="background: #e0f2fe; color: #0284c7;"><i class="fa fa-eye"></i></div>
                <div>
                    <h3>Partner Details</h3>
                    <p class="modal-subtitle">Full information for this partner.</p>
                </div>
            </div>
            <button class="modal-close" data-close="viewModal"><i class="fa fa-times"></i></button>
        </div>

        <div class="modal-body" style="padding: 24px;">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <small style="color:var(--gray-500); font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Institution</small>
                    <p id="viewInstitution" style="font-size:1.1rem; font-weight:600; margin-top:4px; color:var(--navy);"></p>
                </div>
                <div>
                    <small style="color:var(--gray-500); font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Country</small>
                    <p id="viewCountry" style="font-size:1.1rem; font-weight:600; margin-top:4px; color:var(--navy);"></p>
                </div>
                <div>
                    <small style="color:var(--gray-500); font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Agreement Type</small>
                    <p id="viewAgreement" style="margin-top:4px;"></p>
                </div>
                <div>
                    <small style="color:var(--gray-500); font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Status</small>
                    <p id="viewStatus" style="margin-top:4px;"></p>
                </div>
                <div>
                    <small style="color:var(--gray-500); font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Expiry Date</small>
                    <p id="viewExpiry" style="margin-top:4px;"></p>
                </div>
            </div>

            <hr style="border:0; border-top:1px solid var(--gray-200); margin: 20px 0;">

            <div>
                <small style="color:var(--gray-500); font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Contact Person</small>
                <p id="viewContactName" style="font-weight:600; margin-top:4px;"></p>
                <p id="viewContactEmail" style="color:var(--gray-500); font-size:0.9rem;"></p>
            </div>

            <div style="margin-top: 20px;">
                <small style="color:var(--gray-500); font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Notes</small>
                <p id="viewNotes" style="margin-top:4px; color:var(--gray-700); line-height:1.6;"></p>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn-secondary" data-close="viewModal">Close</button>
        </div>
    </div>
</div>

<script src="../asset/js/partners.js"></script>