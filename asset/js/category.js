/* asset/js/category.js — PSU Internalization Program Category */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // 1. Get CSRF and Endpoint
    const CFG  = window.PROGRAM_CATEGORY || {};
    const CSRF = CFG.csrf || '';
    const endpoint = window.location.pathname + window.location.search;

    // 2. Elements
    const overlay           = document.getElementById('categoryModalOverlay');
    const form              = document.getElementById('categoryForm');
    const titleEl           = document.getElementById('categoryModalTitle');
    const subtitleEl        = document.getElementById('categoryModalSubtitle');
    const idInput           = document.getElementById('categoryId');
    const nameInput         = document.getElementById('categoryName');
    const descInput         = document.getElementById('categoryDescription');
    const submitBtn         = document.getElementById('categorySubmitBtn');
    const closeBtn          = document.getElementById('categoryModalClose');
    const cancelBtn         = document.getElementById('categoryModalCancel');
    const addBtn            = document.getElementById('addCategoryBtn');
    const searchInput       = document.getElementById('searchInput');
    const tableBody         = document.getElementById('tableBody');
    const paginationSummary = document.getElementById('paginationSummary');
    const pagerNav          = document.getElementById('pagerNav');
    
    // 3. Pagination State
    const PAGE_SIZE = 5;
    let allRows = Array.from(tableBody.querySelectorAll("tr[data-search]"));
    let currentPage = 1;

    /* ---------------- Modal Helpers ---------------- */
    function toggleCategoryModal(open) {
        if (!overlay) return;
        overlay.classList.toggle("open", open);
        overlay.classList.toggle("visible", open);
        document.body.style.overflow = open ? "hidden" : "";
        if (open && nameInput) setTimeout(() => nameInput.focus(), 50);
    }

    function openAddModal() {
        if (!form) return;
        form.reset();
        if (idInput) idInput.value = '';
        if (titleEl) titleEl.textContent = 'Add Category';
        if (subtitleEl) subtitleEl.textContent = 'Create a new program category';
        toggleCategoryModal(true);
    }

    function openEditModal(id, name, description) {
        if (!form) return;
        form.reset();
        if (idInput)    idInput.value    = id || '';
        if (nameInput)  nameInput.value  = name || '';
        if (descInput)  descInput.value  = description || '';
        if (titleEl)    titleEl.textContent = 'Edit Category';
        if (subtitleEl) subtitleEl.textContent = 'Update this program category';
        toggleCategoryModal(true);
    }

    /* ---------------- Delete ---------------- */
    function deleteCategory(id, name) {
        if (!id) return;
        const label = name ? '"' + name + '"' : 'this category';
        if (!confirm('Are you sure you want to permanently delete ' + label + '?')) return;

        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        fd.append('csrf_token', CSRF);

        fetch(endpoint, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(r => r.json())
            .then(json => {
                if (json.ok) {
                    showToast("success", json.message || "Category deleted.");
                    setTimeout(() => location.reload(), 700);
                } else {
                    showToast("error", json.error || 'Failed to delete category.');
                }
            })
            .catch(() => showToast("error", "Server error. Please try again."));
    }

    /* ---------------- Form Submit ---------------- */
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const id   = idInput ? idInput.value.trim() : '';
            const name = nameInput ? nameInput.value.trim() : '';

            if (name === '') {
                alert('Category name is required.');
                if (nameInput) nameInput.focus();
                return;
            }

            const action = id ? 'edit' : 'add';

            const fd = new FormData(form);
            fd.append('action', action);
            if (!fd.get('csrf_token')) fd.append('csrf_token', CSRF);

            const orig = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

            fetch(endpoint, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(r => r.json())
                .then(json => {
                    if (json.ok) {
                        toggleCategoryModal(false);
                        showToast("success", json.message || "Saved successfully.");
                        setTimeout(() => { location.reload(); }, 700);
                    } else {
                        showToast("error", json.error || 'Failed to save category.');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = orig;
                    }
                })
                .catch(() => {
                    showToast("error", "Server error. Please try again.");
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = orig;
                });
        });
    }

    /* ---------------- Dynamic Pagination & Search ---------------- */
    function getFilteredRows() {
        const keyword = searchInput ? searchInput.value.toLowerCase().trim() : "";
        return allRows.filter(row => {
            const haystack = row.dataset.search || "";
            return haystack.includes(keyword);
        });
    }

    function renderTable() {
        const filtered  = getFilteredRows();
        const total     = filtered.length;
        const pageCount = Math.max(1, Math.ceil(total / PAGE_SIZE));

        if (currentPage > pageCount) currentPage = pageCount;

        allRows.forEach(row => row.style.display = "none");

        const start = (currentPage - 1) * PAGE_SIZE;
        const pageRows = filtered.slice(start, start + PAGE_SIZE);
        pageRows.forEach(row => row.style.display = "");

        if (paginationSummary) {
            if (total === 0) {
                paginationSummary.textContent = "Showing 0 category(s)";
            } else {
                paginationSummary.textContent = `Showing ${start + 1}–${Math.min(start + PAGE_SIZE, total)} of ${total} category(s)`;
            }
        }

        renderPager(pageCount);
    }

    function renderPager(pageCount) {
        if (!pagerNav) return;
        pagerNav.innerHTML = "";

        pagerNav.appendChild(makePagerBtn({
            label: '<i class="fa-solid fa-chevron-left"></i>',
            disabled: currentPage === 1,
            onClick: () => goToPage(currentPage - 1),
        }));

        const pages = getPageList(currentPage, pageCount);
        pages.forEach(p => {
            if (p === "...") {
                const span = document.createElement("span");
                span.className = "pager-ellipsis";
                span.textContent = "...";
                pagerNav.appendChild(span);
            } else {
                pagerNav.appendChild(makePagerBtn({
                    label: String(p),
                    active: p === currentPage,
                    onClick: () => goToPage(p),
                }));
            }
        });

        pagerNav.appendChild(makePagerBtn({
            label: '<i class="fa-solid fa-chevron-right"></i>',
            disabled: currentPage === pageCount,
            onClick: () => goToPage(currentPage + 1),
        }));
    }

    function makePagerBtn({ label, active = false, disabled = false, onClick }) {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "pager-btn" + (active ? " active" : "");
        btn.innerHTML = label;
        btn.disabled = disabled;
        btn.addEventListener("click", onClick);
        return btn;
    }

    function getPageList(current, total) {
        if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
        const pages = [1];
        if (current > 3) pages.push("...");
        const start = Math.max(2, current - 1);
        const end = Math.min(total - 1, current + 1);
        for (let i = start; i <= end; i++) pages.push(i);
        if (current < total - 2) pages.push("...");
        pages.push(total);
        return pages;
    }

    function goToPage(page) {
        currentPage = page;
        renderTable();
    }

    /* ---------------- Wire Events ---------------- */
    if (searchInput) {
        searchInput.addEventListener("input", function () {
            currentPage = 1;
            renderTable();
        });
    }

    if (addBtn) {
        addBtn.addEventListener('click', function(e) { e.preventDefault(); openAddModal(); });
    }

    if (closeBtn)  closeBtn.addEventListener('click', () => toggleCategoryModal(false));
    if (cancelBtn) cancelBtn.addEventListener('click', () => toggleCategoryModal(false));

    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) toggleCategoryModal(false);
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay && overlay.classList.contains('open')) {
            toggleCategoryModal(false);
        }
    });

    if (tableBody) {
        tableBody.addEventListener('click', function (e) {
            const editBtn = e.target.closest('.edit-btn');
            if (editBtn) {
                openEditModal(
                    parseInt(editBtn.dataset.id, 10),
                    editBtn.dataset.name || '',
                    editBtn.dataset.description || ''
                );
                return;
            }

            const delBtn = e.target.closest('.delete-btn');
            if (delBtn) {
                deleteCategory(
                    parseInt(delBtn.dataset.id, 10),
                    delBtn.dataset.name || ''
                );
                return;
            }
        });
    }

    /* ---------------- Toast ---------------- */
    function showToast(type, message) {
        const existing = document.querySelector(".toast");
        if (existing) existing.remove();

        const toast = document.createElement("div");
        toast.className = `toast toast--${type}`;
        toast.innerHTML = `<i class="fa fa-${type === "success" ? "check-circle" : "exclamation-circle"}"></i><span></span>`;
        toast.querySelector("span").textContent = message;

        document.body.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add("toast--show"));

        setTimeout(() => {
            toast.classList.remove("toast--show");
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // 4. Initialize Pagination on load
    renderTable();
});