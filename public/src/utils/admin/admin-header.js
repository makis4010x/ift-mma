/* =========================================
   IFT MMA — ADMIN HEADER
   Mobile sidebar, desktop compact sidebar,
   profile menu and header actions.
========================================= */

const adminHeader = () => {
  /* =========================================
     ELEMENTS
  ========================================= */

  const sidebar = document.getElementById("adminSidebar");
  const overlay = document.getElementById("sidebarOverlay");
  const toggle = document.getElementById("toggleSidebar");
  const mobileClose = document.getElementById("closeMobileSidebar");

  const profileButton = document.getElementById("profileButton");
  const profileMenu = document.getElementById("profileMenu");

  const notificationsButton = document.getElementById("notificationsButton");
  const mobileSearchButton = document.getElementById("mobileSearchButton");

  const mobileQuery = window.matchMedia("(max-width: 1023px)");

  if (!sidebar || !overlay || !toggle || !mobileClose) return;

  /* =========================================
     MOBILE SIDEBAR
  ========================================= */

  function setMobileSidebar(open) {
    sidebar.classList.toggle("-translate-x-full", !open);
    overlay.classList.toggle("hidden", !open);

    document.body.classList.toggle("overflow-hidden", open);

    toggle.setAttribute("aria-expanded", String(open));
  }

  /* =========================================
     SIDEBAR TOGGLE
  ========================================= */

  toggle.addEventListener("click", () => {
    if (mobileQuery.matches) {
      const isClosed = sidebar.classList.contains("-translate-x-full");

      setMobileSidebar(isClosed);
    } else {
      document.body.classList.toggle("sidebar-collapsed");
    }
  });

  /* =========================================
     CLOSE MOBILE SIDEBAR
  ========================================= */

  mobileClose.addEventListener("click", () => {
    setMobileSidebar(false);
  });

  overlay.addEventListener("click", () => {
    setMobileSidebar(false);
  });

  /* =========================================
     RESPONSIVE BEHAVIOR
  ========================================= */

  window.addEventListener("resize", () => {
    if (!mobileQuery.matches) {
      setMobileSidebar(false);
    }
  });

  /* =========================================
     SIDEBAR LINKS
  ========================================= */

  sidebar.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => {
      if (mobileQuery.matches) {
        setMobileSidebar(false);
      }
    });
  });

  /* =========================================
     PROFILE MENU
  ========================================= */

  if (profileButton && profileMenu) {
    profileButton.addEventListener("click", () => {
      const willOpen = profileMenu.classList.contains("hidden");

      profileMenu.classList.toggle("hidden", !willOpen);

      profileButton.setAttribute("aria-expanded", String(willOpen));
    });

    // Close profile menu when clicking outside.
    document.addEventListener("click", (event) => {
      if (
        !profileButton.contains(event.target) &&
        !profileMenu.contains(event.target)
      ) {
        profileMenu.classList.add("hidden");

        profileButton.setAttribute("aria-expanded", "false");
      }
    });
  }

  /* =========================================
     ESCAPE KEY
  ========================================= */

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      if (mobileQuery.matches) {
        setMobileSidebar(false);
      }

      if (profileButton && profileMenu) {
        profileMenu.classList.add("hidden");
        profileButton.setAttribute("aria-expanded", "false");
      }
    }
  });

  /* =========================================
     NOTIFICATIONS BUTTON
     Demo action — connect to PHP MVC later.
  ========================================= */

  if (notificationsButton) {
    notificationsButton.addEventListener("click", () => {
      alert("Σύνδεσε εδώ τις ειδοποιήσεις του συστήματος.");
    });
  }

  /* =========================================
     MOBILE SEARCH BUTTON
     Demo action — connect to PHP MVC later.
  ========================================= */

  if (mobileSearchButton) {
    mobileSearchButton.addEventListener("click", () => {
      alert("Σύνδεσε εδώ την αναζήτηση μαθητών.");
    });
  }

  /* =========================================
     LUCIDE ICONS
  ========================================= */

  if (window.lucide) {
    window.lucide.createIcons();
  }
};

export default adminHeader;
