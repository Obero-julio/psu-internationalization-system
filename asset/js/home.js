/* ===================================================
   PSU × IZN — Applicant Portal
   Dashboard interactions (account menu + sidebar mobile)
   =================================================== */

document.addEventListener("DOMContentLoaded", () => {
  const accountMenu = document.querySelector(".account-menu");
  const accountTrigger = document.querySelector(".account-trigger");
  const accountDropdown = document.getElementById("accountDropdown");
  const mobileMenuBtn = document.querySelector(".mobile-menu");
  const sidebar = document.getElementById("sidebar");
  const sidebarCloseBtn = document.querySelector(".sidebar-close");
  const mobileViewport = window.matchMedia("(max-width: 860px)");

  const setAccountOpen = (open) => {
    if (!accountMenu || !accountTrigger) return;
    accountMenu.classList.toggle("open", open);
    accountTrigger.setAttribute("aria-expanded", String(open));
    accountDropdown?.setAttribute("aria-hidden", String(!open));
  };

  if (accountMenu && accountTrigger) {
    accountTrigger.addEventListener("click", (e) => {
      e.stopPropagation();
      setAccountOpen(!accountMenu.classList.contains("open"));
    });

    document.addEventListener("click", (e) => {
      if (!accountMenu.contains(e.target)) {
        setAccountOpen(false);
      }
    });
  }

  if (mobileMenuBtn && sidebar) {
    const setSidebarOpen = (open, moveFocus = false) => {
      const shouldOpen = mobileViewport.matches && open;
      sidebar.classList.toggle("is-open", shouldOpen);
      document.body.classList.toggle("sidebar-open", shouldOpen);
      mobileMenuBtn.setAttribute("aria-expanded", String(shouldOpen));
      mobileMenuBtn.setAttribute("aria-label", shouldOpen ? "Close navigation" : "Open navigation");
      sidebar.setAttribute("aria-hidden", String(mobileViewport.matches && !shouldOpen));
      sidebar.inert = mobileViewport.matches && !shouldOpen;

      if (shouldOpen && moveFocus) {
        (sidebarCloseBtn || sidebar.querySelector("a[href]"))?.focus();
      } else if (!shouldOpen && moveFocus) {
        mobileMenuBtn.focus();
      }
    };

    const syncSidebarToViewport = () => {
      setSidebarOpen(false);
    };

    mobileMenuBtn.addEventListener("click", () => {
      setSidebarOpen(!sidebar.classList.contains("is-open"), true);
    });

    sidebarCloseBtn?.addEventListener("click", () => setSidebarOpen(false, true));

    sidebar.addEventListener("click", (event) => {
      if (event.target instanceof Element
        && event.target.closest("a[href]")
        && mobileViewport.matches) {
        setSidebarOpen(false);
      }
    });

    document.body.addEventListener("click", (event) => {
      if (sidebar.classList.contains("is-open")
        && !sidebar.contains(event.target)
        && !mobileMenuBtn.contains(event.target)) {
        setSidebarOpen(false);
      }
    });

    if (typeof mobileViewport.addEventListener === "function") {
      mobileViewport.addEventListener("change", syncSidebarToViewport);
    } else {
      mobileViewport.addListener(syncSidebarToViewport);
    }
    syncSidebarToViewport();

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        if (accountMenu?.classList.contains("open")) {
          setAccountOpen(false);
          accountTrigger?.focus();
        }
        if (sidebar.classList.contains("is-open")) {
          setSidebarOpen(false, true);
        }
        return;
      }

      if (event.key !== "Tab" || !sidebar.classList.contains("is-open")) return;

      const focusable = [...sidebar.querySelectorAll('a[href], button:not([disabled])')]
        .filter((element) => element.getClientRects().length > 0);
      if (focusable.length === 0) return;

      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (!sidebar.contains(document.activeElement)) {
        event.preventDefault();
        (event.shiftKey ? last : first).focus();
      } else if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });
  }
});