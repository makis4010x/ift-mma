/* =========================================
   IFT MMA — ADMIN
   Independent initialisation of each module.
========================================= */

function refreshAdminIcons() {
  if (typeof window.lucide?.createIcons !== "function") {
    return;
  }

  try {
    window.lucide.createIcons();
  } catch (error) {
    console.error("IFT: icon initialisation failed.", error);
  }
}

function startAdmin() {
  refreshAdminIcons();

  // Date-picker failures must not disable the sidebar.
  import("./utils/admin/admin-header.js")
    .then(({ default: adminHeader }) => {
      adminHeader();
      refreshAdminIcons();
    })
    .catch((error) => {
      console.error("IFT: admin header failed to load.", error);
    });

  import("./utils/admin/admin-date-pickers.js?v=3")
    .then(({ default: adminDatePickers }) => {
      return adminDatePickers();
    })
    .catch((error) => {
      console.error("IFT: date controls failed to load.", error);
    });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", startAdmin, { once: true });
} else {
  startAdmin();
}

window.addEventListener("load", refreshAdminIcons, { once: true });
