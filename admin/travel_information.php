<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = db();

/* CSRF TOKEN */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

/* ------------------------------------------------------------
   AJAX ENDPOINT (Delete)
   ------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');
    
    // CSRF Check
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token']);
        exit();
    }

    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM travel_information WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['ok' => true, 'message' => 'Travel record deleted.']);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Invalid ID.']);
    }
    exit();
}

/* ------------------------------------------------------------
   SEARCH
   ------------------------------------------------------------ */
$search = trim($_GET['q'] ?? '');

if ($search !== '') {
    $stmt = $pdo->prepare("
        SELECT *
        FROM travel_information
        WHERE applicant_name LIKE ?
           OR destination LIKE ?
        ORDER BY id DESC
    ");
    $like = '%' . $search . '%';
    $stmt->execute([$like, $like]);
} else {
    $stmt = $pdo->query("
        SELECT *
        FROM travel_information
        ORDER BY id DESC
    ");
}

$travels = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ------------------------------------------------------------
   HELPERS
   ------------------------------------------------------------ */
function travel_status(string $departure, string $return): array
{
    $today = new DateTimeImmutable('today');
    $dep   = new DateTimeImmutable($departure);
    $ret   = new DateTimeImmutable($return);

    if ($today < $dep) {
        return ['upcoming', 'Upcoming'];
    }

    if ($today >= $dep && $today <= $ret) {
        return ['active', 'Ongoing'];
    }

    return ['completed', 'Completed'];
}

function format_date(?string $date): string
{
    if (!$date) return '—';
    return (new DateTimeImmutable($date))->format('M d, Y');
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $letters = array_map(fn($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2));
    return implode('', $letters) ?: '?';
}
?>

<link rel="stylesheet" href="../asset/css/travel_information.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<div class="content">

    <div class="page-header">
        <p>Manage applicant travel records, including destinations and departure/return schedules.</p>
    </div>

    <div class="toolbar">
        <form class="search-wrap" method="get" action="dashboard.php">
            <input type="hidden" name="page" value="travel_information">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input
                type="text"
                name="q"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="Search by applicant or destination..."
            >
        </form>

        <button class="create-btn" onclick="openAddModal()">
            <i class="fa-solid fa-plus"></i>
            Add Travel Record
        </button>
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Destination</th>
                    <th>Departure Date</th>
                    <th>Return Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>

                <?php if (!empty($travels)): ?>

                    <?php foreach ($travels as $t): ?>
                        <?php
                        [$statusClass, $statusLabel] = travel_status(
                            $t['departure_date'],
                            $t['return_date']
                        );
                        $name = $t['applicant_name'] ?? '';
                        ?>
                        <tr>
                            <td>
                                <div class="user-name-cell">
                                    <div class="user-avatar"><?= htmlspecialchars(initials($name ?: '?')) ?></div>
                                    <span><?= htmlspecialchars($name) ?></span>
                                </div>
                            </td>

                            <td>
                                <span class="badge">
                                    <i class="fa-solid fa-location-dot"></i>
                                    <?= htmlspecialchars($t['destination']) ?>
                                </span>
                            </td>

                            <td><?= htmlspecialchars(format_date($t['departure_date'])) ?></td>
                            <td><?= htmlspecialchars(format_date($t['return_date'])) ?></td>

                            <td>
                                <span class="status-badge status-<?= htmlspecialchars($statusClass) ?>">
                                    <?= htmlspecialchars($statusLabel) ?>
                                </span>
                            </td>

                            <td>
                                <div class="actions">
                                    <button class="action-btn edit-btn" title="Edit" onclick='openEditModal(<?= json_encode($t) ?>)'>
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button class="action-btn delete-btn" title="Delete" onclick="deleteRecord(<?= (int)$t['id'] ?>)">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                </div>
                                <h3>No travel records found</h3>
                                <p>
                                    <?= $search !== ''
                                        ? 'Try adjusting your search terms.'
                                        : 'Add a travel record to get started.' ?>
                                </p>
                            </div>
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>
        </table>
    </div>

</div>

<!-- ===================== ADD / EDIT MODAL ===================== -->
<div class="modal-overlay" id="travelModal">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-header-left">
        <div class="modal-icon"><i class="fa-solid fa-plane" id="modalIcon"></i></div>
        <div>
          <h3 id="modalTitle">Add Travel Record</h3>
          <p class="modal-subtitle" id="modalSubtitle">Create a new travel record</p>
        </div>
      </div>
      <button type="button" class="modal-close" id="modalClose">&times;</button>
    </div>

    <form id="travelForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="action" id="formAction" value="save">
      <input type="hidden" name="id" id="formId" value="">

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="applicant_name">Applicant Name *</label>
          <input type="text" id="applicant_name" name="applicant_name" class="form-input" placeholder="e.g. John Doe" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="destination">Destination *</label>
          <input type="text" id="destination" name="destination" class="form-input" placeholder="e.g. Tokyo, Japan" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="departure_date">Departure Date *</label>
            <input type="date" id="departure_date" name="departure_date" class="form-input" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="return_date">Return Date *</label>
            <input type="date" id="return_date" name="return_date" class="form-input" required>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-secondary" id="modalCancel">Cancel</button>
        <button type="submit" class="btn-confirm" id="formSubmit">
          <i class="fa-solid fa-check"></i>
          <span id="formSubmitLabel">Save Record</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ===================== DELETE CONFIRM MODAL ===================== -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal-box" style="width:420px;">
    <div class="modal-header">
      <div class="modal-header-left">
        <div class="modal-icon" style="background:#fff5f5;color:#dc2626;">
          <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div>
          <h3>Delete Record</h3>
          <p class="modal-subtitle">This action cannot be undone</p>
        </div>
      </div>
      <button type="button" class="modal-close" id="deleteModalClose">&times;</button>
    </div>
    <div class="modal-body">
      <p style="font-size:13.5px;color:#475569;line-height:1.6;">
        Are you sure you want to permanently delete this travel record?
      </p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn-secondary" id="deleteCancel">Cancel</button>
      <button type="button" class="btn-confirm" id="deleteConfirm" style="background:#dc2626;">
        <i class="fa-solid fa-trash"></i> Delete
      </button>
    </div>
  </div>
</div>

<!-- ===================== TOAST ===================== -->
<div class="toast" id="toast">
  <i class="fa-solid fa-circle-check"></i>
  <span id="toastMessage">Saved successfully</span>
</div>

<script>
  window.TRAVEL_CSRF = <?= json_encode($csrfToken) ?>;

  document.addEventListener("DOMContentLoaded", () => {
    const csrfToken = window.TRAVEL_CSRF;
    
    // Modals
    const modal = document.getElementById("travelModal");
    const deleteModal = document.getElementById("deleteModal");
    
    // Form Elements
    const form = document.getElementById("travelForm");
    const formAction = document.getElementById("formAction");
    const formId = document.getElementById("formId");
    const submitLabel = document.getElementById("formSubmitLabel");
    const modalTitle = document.getElementById("modalTitle");
    const modalSubtitle = document.getElementById("modalSubtitle");
    const modalIcon = document.getElementById("modalIcon");

    const nameInput = document.getElementById("applicant_name");
    const destInput = document.getElementById("destination");
    const depInput = document.getElementById("departure_date");
    const retInput = document.getElementById("return_date");

    // Delete Elements
    let deleteId = null;
    
    // Toast
    const toast = document.getElementById("toast");
    const toastMessage = document.getElementById("toastMessage");
    let toastTimer = null;

    function showToast(message, type = "success") {
      toastMessage.textContent = message;
      toast.classList.remove("toast--success", "toast--error");
      toast.classList.add(type === "error" ? "toast--error" : "toast--success");
      toast.querySelector("i").className = type === "error" ? "fa-solid fa-circle-exclamation" : "fa-solid fa-circle-check";
      toast.classList.add("toast--show");
      clearTimeout(toastTimer);
      toastTimer = setTimeout(() => toast.classList.remove("toast--show"), 3200);
    }

    // Open/Close Modals
    const openModal = () => modal.classList.add("open");
    const closeModal = () => modal.classList.remove("open");
    const openDeleteModal = () => deleteModal.classList.add("open");
    const closeDeleteModal = () => deleteModal.classList.remove("open");

    // Reset Form
    function resetForm() {
      form.reset();
      formId.value = "";
      formAction.value = "save";
    }

    // Add Button
    window.openAddModal = () => {
      resetForm();
      modalTitle.textContent = "Add Travel Record";
      modalSubtitle.textContent = "Create a new travel record";
      modalIcon.className = "fa-solid fa-plane";
      submitLabel.textContent = "Save Record";
      openModal();
      nameInput.focus();
    };

    // Edit Button
    window.openEditModal = (data) => {
      resetForm();
      formAction.value = "save";
      formId.value = data.id;
      nameInput.value = data.applicant_name || "";
      destInput.value = data.destination || "";
      depInput.value = data.departure_date || "";
      retInput.value = data.return_date || "";
      
      modalTitle.textContent = "Edit Travel Record";
      modalSubtitle.textContent = "Update existing travel record";
      modalIcon.className = "fa-solid fa-pen";
      submitLabel.textContent = "Update Record";
      openModal();
      nameInput.focus();
    };

    // Close buttons
    document.getElementById("modalClose").onclick = closeModal;
    document.getElementById("modalCancel").onclick = closeModal;
    document.getElementById("deleteModalClose").onclick = closeDeleteModal;
    document.getElementById("deleteCancel").onclick = closeDeleteModal;

    [modal, deleteModal].forEach((m) => {
      m.addEventListener("click", (e) => {
        if (e.target === m) m.classList.remove("open");
      });
    });

    // Submit Add/Edit via AJAX
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const submitBtn = document.getElementById("formSubmit");
      submitBtn.disabled = true;

      try {
        // Note: Since we don't have a separate backend endpoint yet, we'll post to a new file or handle it here.
        // For this example, let's assume you will create a `travel_save.php` to handle this.
        const body = new URLSearchParams(new FormData(form));
        const res = await fetch('travel_save.php', { method: "POST", body });
        const data = await res.json();

        if (!data.ok) {
          showToast(data.message || "Something went wrong.", "error");
          return;
        }
        
        showToast(formAction.value === "edit" ? "Record updated." : "Travel record added.");
        setTimeout(() => location.reload(), 700);
      } catch (err) {
        showToast("Network error. Please try again.", "error");
      } finally {
        submitBtn.disabled = false;
      }
    });

    // Delete Record
    window.deleteRecord = (id) => {
      deleteId = id;
      openDeleteModal();
    };

    document.getElementById("deleteConfirm").addEventListener("click", async () => {
      if (!deleteId) return;
      const confirmBtn = document.getElementById("deleteConfirm");
      confirmBtn.disabled = true;

      try {
        const body = new URLSearchParams({ action: "delete", id: deleteId, csrf_token: csrfToken });
        const res = await fetch('', { method: "POST", body });
        const data = await res.json();
        
        if (data.ok) {
          closeDeleteModal();
          showToast("Record deleted.");
          setTimeout(() => location.reload(), 700);
        } else {
          showToast(data.message || "Could not delete.", "error");
        }
      } catch (err) {
        showToast("Network error. Please try again.", "error");
      } finally {
        confirmBtn.disabled = false;
        deleteId = null;
      }
    });
  });
</script>