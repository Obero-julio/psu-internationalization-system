document.addEventListener("DOMContentLoaded", () => {
  /* ---- Config ---- */
  const csrfToken = window.COUNTRIES_CSRF;
  const endpoint = window.location.pathname + window.location.search;

  /* ---- Elements ---- */
  const addBtn = document.getElementById("btnAddCountry");
  const tableBody = document.getElementById("countriesBody");
  const searchInput = document.getElementById("searchInput");
  
  // Safe lookup for the pagination text span
  const paginationSpan = document.querySelector(".pagination span");

  const form = document.getElementById("countryForm");
  const formAction = document.getElementById("formAction");
  const formId = document.getElementById("formId");
  const submitLabel = document.getElementById("formSubmitLabel");

  const countryInput = document.getElementById("country_name");
  const regionInput = document.getElementById("region");
  const provinceInput = document.getElementById("province");
  const cityInput = document.getElementById("city");
  const postalInput = document.getElementById("postal_code");
  const addressInput = document.getElementById("address");

  const modal = document.getElementById("countryModal");
  const deleteModal = document.getElementById("deleteModal");
  const modalTitle = document.getElementById("modalTitle");
  const modalSubtitle = document.getElementById("modalSubtitle");
  const modalIcon = document.getElementById("modalIcon");

  const toast = document.getElementById("toast");
  const toastMessage = document.getElementById("toastMessage");

  let deleteId = null;
  let toastTimer = null;

  /* ---- Toast ---- */
  function showToast(message, type = "success") {
    toastMessage.textContent = message;

    toast.classList.remove("toast--success", "toast--error");
    toast.classList.add(type === "error" ? "toast--error" : "toast--success");

    toast.querySelector("i").className =
      type === "error"
        ? "fa-solid fa-circle-exclamation"
        : "fa-solid fa-circle-check";

    toast.classList.add("toast--show");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove("toast--show"), 3200);
  }

  /* ---- Modal helpers ---- */
  const openModal = () => modal.classList.add("open");
  const closeModal = () => modal.classList.remove("open");
  const openDeleteModal = () => deleteModal.classList.add("open");
  const closeDeleteModal = () => deleteModal.classList.remove("open");

  function resetForm() {
    form.reset();
    formId.value = "";
    formAction.value = "add";
  }

  /* ---- Add button ---- */
  addBtn.addEventListener("click", () => {
    resetForm();
    modalTitle.textContent = "Add Country / Address";
    modalSubtitle.textContent = "Create a new country record";
    modalIcon.className = "fa-solid fa-earth-americas";
    submitLabel.textContent = "Save";
    openModal();
    countryInput.focus();
  });

  /* ---- Table actions (delegated) ---- */
  tableBody.addEventListener("click", (e) => {
    const editBtn = e.target.closest(".edit-btn");
    const deleteBtn = e.target.closest(".delete-btn");
    const statusBtn = e.target.closest(".status-toggle");

    if (editBtn) {
      resetForm();
      formAction.value = "edit";
      formId.value = editBtn.dataset.id;
      countryInput.value = editBtn.dataset.country || "";
      regionInput.value = editBtn.dataset.region || "";
      provinceInput.value = editBtn.dataset.province || "";
      cityInput.value = editBtn.dataset.city || "";
      postalInput.value = editBtn.dataset.postal || "";
      addressInput.value = editBtn.dataset.address || "";

      modalTitle.textContent = "Edit Country / Address";
      modalSubtitle.textContent = "Update existing record";
      modalIcon.className = "fa-solid fa-pen";
      submitLabel.textContent = "Update";
      openModal();
      countryInput.focus();
      return;
    }

    if (deleteBtn) {
      deleteId = deleteBtn.dataset.id;
      openDeleteModal();
      return;
    }

    if (statusBtn) {
      toggleStatus(statusBtn);
    }
  });

  /* ---- Toggle status ---- */
  async function toggleStatus(button) {
    button.disabled = true;

    try {
      const body = new URLSearchParams({
        action: "toggle_status",
        id: button.dataset.id,
        csrf_token: csrfToken,
      });

      const res = await fetch(endpoint, {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
        body,
      });
      
      const text = await res.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch {
        console.error("PHP Error Output:", text);
        showToast("Server returned invalid format.", "error");
        return;
      }

      if (!data.ok) {
        showToast(data.error || "Could not update status.", "error");
        return;
      }

      button.textContent = data.status;
      button.dataset.status = data.status;
      button.classList.toggle("status-active", data.status === "Active");
      button.classList.toggle("status-inactive", data.status !== "Active");
      showToast(`Status changed to ${data.status}.`);
    } catch {
      showToast("Network error. Please try again.", "error");
    } finally {
      button.disabled = false;
    }
  }

  /* ---- Submit Add / Edit ---- */
  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const submitBtn = document.getElementById("formSubmit");
    submitBtn.disabled = true;

    try {
      const body = new URLSearchParams(new FormData(form));
      const res = await fetch(endpoint, {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
        body,
      });
      
      const text = await res.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch {
        console.error("PHP Error Output:", text);
        showToast("Server error. Check console.", "error");
        return;
      }

      if (!data.ok) {
        showToast(data.error || "Something went wrong.", "error");
        return;
      }

      showToast(
        formAction.value === "edit" ? "Record updated." : "Country added.",
      );
      setTimeout(() => location.reload(), 700);
    } catch {
      showToast("Network error. Please try again.", "error");
    } finally {
      submitBtn.disabled = false;
    }
  });

  /* ---- Confirm delete ---- */
  document.getElementById("deleteConfirm").addEventListener("click", async () => {
      if (!deleteId) return;

      const confirmBtn = document.getElementById("deleteConfirm");
      confirmBtn.disabled = true;

      try {
        const body = new URLSearchParams({
          action: "delete",
          id: deleteId,
          csrf_token: csrfToken,
        });

        const res = await fetch(endpoint, {
          method: "POST",
          headers: { "X-Requested-With": "XMLHttpRequest" },
          body,
        });

        const text = await res.text();
        let data;
        try {
          data = JSON.parse(text);
        } catch {
          console.error("PHP Error Output:", text);
          showToast("Server returned invalid response.", "error");
          return;
        }

        if (!data.ok) {
          showToast(data.error || "Could not delete record.", "error");
          return;
        }

        closeDeleteModal();
        showToast("Record deleted.");
        setTimeout(() => location.reload(), 700);
      } catch {
        showToast("Network error. Please try again.", "error");
      } finally {
        confirmBtn.disabled = false;
        deleteId = null;
      }
    });

  /* ---- Close buttons ---- */
  document.getElementById("modalClose").onclick = closeModal;
  document.getElementById("modalCancel").onclick = closeModal;
  document.getElementById("deleteModalClose").onclick = closeDeleteModal;
  document.getElementById("deleteCancel").onclick = closeDeleteModal;

  [modal, deleteModal].forEach((m) => {
    m.addEventListener("click", (e) => {
      if (e.target === m) m.classList.remove("open");
    });
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeModal();
      closeDeleteModal();
    }
  });

  /* ---- Live search ---- */
  searchInput.addEventListener("input", () => {
    const term = searchInput.value.trim().toLowerCase();
    let count = 0;

    tableBody.querySelectorAll("tr[data-search]").forEach((row) => {
      const match = row.dataset.search.includes(term);
      row.style.display = match ? "" : "none";
      if (match) count++;
    });

    if (paginationSpan) {
      paginationSpan.textContent = term
        ? `Showing ${count} matching countr${count === 1 ? "y" : "ies"}`
        : `Showing ${count} countr${count === 1 ? "y" : "ies"}`;
    }
  });
});