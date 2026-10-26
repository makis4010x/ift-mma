const tabButtons = [...document.querySelectorAll("[data-student-tab]")];

const tabPanels = [...document.querySelectorAll("[data-student-panel]")];

if (tabButtons.length === 4 && tabPanels.length === 4) {
  const allowed = new Set(["details", "attendance", "memberships", "barcode"]);

  function showTab(requested, updateUrl = true) {
    const tab = allowed.has(requested) ? requested : "details";

    for (const button of tabButtons) {
      const active = button.dataset.studentTab === tab;

      button.setAttribute("aria-pressed", String(active));

      button.classList.toggle("border-red-600", active);
      button.classList.toggle("bg-red-600", active);
      button.classList.toggle("text-white", active);

      button.classList.toggle("border-white/10", !active);
      button.classList.toggle("bg-[#18181c]", !active);
      button.classList.toggle("text-zinc-300", !active);
    }

    for (const panel of tabPanels) {
      const visible = panel.dataset.studentPanel === tab;

      panel.hidden = !visible;
      panel.classList.toggle("hidden", !visible);
    }

    if (updateUrl) {
      const url = new URL(window.location.href);
      url.searchParams.set("tab", tab);

      window.history.replaceState(null, "", url);
    }
  }

  for (const button of tabButtons) {
    button.addEventListener("click", () => {
      showTab(button.dataset.studentTab);
    });
  }

  const params = new URLSearchParams(window.location.search);

  const initialTab =
    params.get("tab") ||
    (params.has("attendance_page") ? "attendance" : "details");

  showTab(initialTab, false);

  window.addEventListener("popstate", () => {
    const urlParams = new URLSearchParams(window.location.search);
    showTab(urlParams.get("tab") || "details", false);
  });
}
