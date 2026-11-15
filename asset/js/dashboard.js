/* asset/js/dashboard.js — PSU Internalization admin dashboard */

(function () {
    'use strict';

    // ----- Sidebar toggle (mobile) -----
    const toggle = document.getElementById('sidebarToggle');

    if (toggle) {
        toggle.addEventListener('click', function () {
            document.body.classList.toggle('sidebar-open');
        });
    }

    // Close sidebar when clicking the scrim (the ::after on body)
    document.addEventListener('click', function (e) {
        if (
            document.body.classList.contains('sidebar-open') &&
            !e.target.closest('.sidebar') &&
            !e.target.closest('#sidebarToggle')
        ) {
            document.body.classList.remove('sidebar-open');
        }
    });

    // Close on Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.body.classList.remove('sidebar-open');
        }
    });

    // ----- Auto-dismiss alerts after 5s -----
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.3s ease';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 300);
        }, 5000);
    });

    // ----- Modal helpers (exposed globally so inline handlers work) -----
    window.openModal = function (id) {
        const el = document.getElementById(id);
        if (el) el.classList.add('show');
    };

    window.closeModal = function (id) {
        const el = document.getElementById(id);
        if (el) el.classList.remove('show');
    };

    // Click on overlay background closes the modal
    document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.classList.remove('show');
        });
    });
})();