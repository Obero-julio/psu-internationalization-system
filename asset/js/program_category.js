/* asset/js/category.js — PSU Internalization Program Category */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // 1. Get CSRF from the global object set in the PHP file
    const CFG  = window.PROGRAM_CATEGORY || {};
    const CSRF = CFG.csrf || '';

    // 2. Grab all elements (ONLY ONCE)
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
    const paginationSummary = document.getElementById('paginationSummary'); // <-- NEW

    /* ---------------- Modal Helpers ---------------- */
    function openModal() {
        if (!overlay) return;
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
        setTimeout(() => { if (nameInput) nameInput.focus(); }, 50);
    }

    function closeModal() {
        if (!overlay) return;
        overlay.classList.remove('open');
        document.body.style.overflow = '';
    }

    /* ---------------- Add ---------------- */
    function openAddModal() {
        if (!form) return;
        form.reset();
        if (idInput) idInput.value = '';
        if (titleEl) titleEl.textContent = 'Add Category';
        if (subtitleEl) subtitleEl.textContent = 'Create a new program category';
        openModal();
    }

    /* ---------------- Edit ---------------- */
    function openEditModal(id, name, description) {
        if (!form) return;
        form.reset();
        if (idInput)    idInput.value    = id || '';
        if (nameInput)  nameInput.value  = name || '';
        if (descInput)  descInput.value  = description || '';
        if (titleEl)    titleEl.textContent = 'Edit Category';
        if (subtitleEl) subtitleEl.textContent = 'Update this program category';
        openModal();
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

        fetch('', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(r => r.json())
            .then(json => {
                if (json.ok) {
                    location.reload();
                } else {
                    alert(json.error || 'Failed to delete category.');
                }
            })
            .catch(() => alert('Server error. Please try again.'));
    }

    /* ---------------- Wire Buttons ---------------- */
    if (addBtn) {
        addBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openAddModal();
        });
    }

    if (closeBtn)  closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeModal();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay && overlay.classList.contains('open')) {
            closeModal();
        }
    });

    /* ---------------- Row actions (Edit/Delete) ---------------- */
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

            fetch('', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(r => r.json())
                .then(json => {
                    if (json.ok) {
                        closeModal();
                        setTimeout(() => { location.reload(); }, 300);
                    } else {
                        alert(json.error || 'Failed to save category.');
                    }
                })
                .catch(() => alert('Server error. Please try again.'))
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = orig;
                });
        });
    }

    /* ---------------- Live Search ---------------- */
    if (searchInput && tableBody) {
        searchInput.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            let visibleCount = 0;
            
            tableBody.querySelectorAll('tr[data-search]').forEach(function (tr) {
                const haystack = tr.getAttribute('data-search') || '';
                const isMatch = (!q || haystack.indexOf(q) !== -1);
                
                tr.style.display = isMatch ? '' : 'none';
                
                if (isMatch) visibleCount++;
            });

            if (paginationSummary) {
                if (visibleCount === 0) {
                    paginationSummary.textContent = "Showing 0 category(s)";
                } else {
                    paginationSummary.textContent = `Showing ${visibleCount} category(s) (filtered)`;
                }
            }
        });
    }
});