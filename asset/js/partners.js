document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    /* ── Search Filter ── */
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const search  = this.value.toLowerCase().trim();
            const rows    = document.querySelectorAll('#tableBody tr[data-name]');
            let   visible = 0;

            rows.forEach(row => {
                const text = (row.dataset.name || '').toLowerCase();
                const show = !search || text.includes(search);
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            const info = document.getElementById('paginationInfo');
            if (info) info.innerText = `Showing ${visible} partner(s)`;
        });
    }

    /* ── Modal Helpers ── */
    function openModal(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeModal(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.remove('open');
            document.body.style.overflow = '';
        }
    }

    document.querySelectorAll('[data-close]').forEach(btn => {
        btn.addEventListener('click', function() {
            closeModal(this.dataset.close);
        });
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

    /* ── ADD Modal ── */
    const btnAdd = document.getElementById('btnAddPartner');
    if (btnAdd) {
        btnAdd.addEventListener('click', function() {
            document.getElementById('addForm').reset();
            const manual = document.getElementById('addCountryManual');
            if (manual) {
                manual.style.display = 'none';
                manual.required = false;
            }
            openModal('addModal');
        });
    }

    const addCountrySelect = document.getElementById('addCountrySelect');
    if (addCountrySelect) {
        addCountrySelect.addEventListener('change', function() {
            const manual = document.getElementById('addCountryManual');
            const isOther = this.value === '__other__';
            manual.style.display = isOther ? '' : 'none';
            manual.required = isOther;
        });
    }

    // INTERCEPT ADD FORM SUBMIT
    const addForm = document.getElementById('addForm');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevents redirect!
            submitForm(this, 'add_partner.php', 'addModal');
        });
    }

    /* ── EDIT Modal ── */
    document.getElementById('tableBody')?.addEventListener('click', function(e) {
        const editBtn = e.target.closest('.edit-btn');
        if (!editBtn) return;

        const data = JSON.parse(editBtn.dataset.partner);
        
        document.getElementById('editId').value = data.id;
        document.getElementById('editInstitution').value = data.institution_name || '';
        document.getElementById('editCountry').value = data.country_id || '';
        
        document.getElementById('editContactFirst').value = data.contact_first_name || '';
        document.getElementById('editContactMi').value = data.contact_middle_name || '';
        document.getElementById('editContactLast').value = data.contact_last_name || '';
        document.getElementById('editEmail').value = data.contact_email || '';
        document.getElementById('editExpiry').value = data.expiry_date || '';
        document.getElementById('editNotes').value = data.notes || '';

        setSelectValue('editAgreement', data.agreement_name || '');
        setSelectValue('editStatus', (data.status || 'Active'));

        document.getElementById('editModalSubtitle').textContent = `Editing: ${data.institution_name}`;
        openModal('editModal');
    });

    // INTERCEPT EDIT FORM SUBMIT
    const editForm = document.getElementById('editForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevents redirect!
            submitForm(this, 'update_partner.php', 'editModal');
        });
    }

    /* ── VIEW Modal ── */
    document.getElementById('tableBody')?.addEventListener('click', function(e) {
        const viewBtn = e.target.closest('.view-btn');
        if (!viewBtn) return;

        const data = JSON.parse(viewBtn.dataset.partner);

        document.getElementById('viewInstitution').textContent = data.institution_name || 'N/A';
        document.getElementById('viewCountry').textContent = data.country_name || 'N/A';
        document.getElementById('viewAgreement').textContent = data.agreement_name || 'N/A';
        document.getElementById('viewExpiry').textContent = data.expiry_date ? new Date(data.expiry_date).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) : 'N/A';
        
        const statusLower = (data.status || 'active').toLowerCase();
        document.getElementById('viewStatus').innerHTML = `<span class="status-badge status-${statusLower}">${ucfirst(statusLower)}</span>`;

        const fullName = [data.contact_first_name, data.contact_middle_name, data.contact_last_name].filter(Boolean).join(' ');
        document.getElementById('viewContactName').textContent = fullName || 'N/A';
        document.getElementById('viewContactEmail').textContent = data.contact_email || 'No email provided';
        document.getElementById('viewNotes').textContent = data.notes || 'No notes provided.';

        openModal('viewModal');
    });

    /* ── Helpers ── */
    function setSelectValue(id, value) {
        const el = document.getElementById(id);
        if (!el) return;
        for (let i = 0; i < el.options.length; i++) {
            if (el.options[i].value.toLowerCase() === value.toLowerCase()) {
                el.selectedIndex = i;
                return;
            }
        }
        el.selectedIndex = 0;
    }

    function ucfirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function submitForm(form, endpoint, modalId) {
        const btn = form.querySelector('[type="submit"]');
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';

        fetch(endpoint, { method: 'POST', body: new FormData(form) })
            .then(async res => {
                const text = await res.text();
                try { return JSON.parse(text); }
                catch { throw new Error('Server did not return JSON'); }
            })
            .then(data => {
                showToast(data.success ? 'success' : 'error', data.message);
                if (data.success) {
                    closeModal(modalId);
                    setTimeout(() => location.reload(), 1200);
                }
            })
            .catch(err => {
                console.error("FETCH ERROR DETAILS:", err);
                showToast('error', 'Check console for details.');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = original;
            });
    }

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