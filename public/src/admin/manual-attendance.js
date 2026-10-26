const searchInput = document.getElementById("manualSearch");
const results = document.getElementById("manualResults");
const status = document.getElementById("manualSearchStatus");

const selected = document.getElementById("manualSelected");
const selectedName = document.getElementById("manualSelectedName");
const selectedBarcode = document.getElementById("manualSelectedBarcode");
const studentIdField = document.getElementById("manualStudentId");
const changeButton = document.getElementById("manualChangeStudent");

if (searchInput && results && status && selected && studentIdField) {
  let timer;
  let pendingRequest;
  let generation = 0;

  function hideResults() {
    results.replaceChildren();
    results.classList.add("hidden");
    searchInput.setAttribute("aria-expanded", "false");
  }

  function clearSelection() {
    selected.classList.add("hidden");
    studentIdField.value = "";
  }

  function chooseStudent(student) {
    clearTimeout(timer);
    pendingRequest?.abort();
    generation++;

    hideResults();

    const name = `${student.first_name} ${student.last_name}`.trim();

    selectedName.textContent = name;
    selectedBarcode.textContent = `ID: ${student.id} · Barcode: ${student.barcode || "—"}`;

    studentIdField.value = String(student.id);

    selected.classList.remove("hidden");

    status.textContent =
      "Επίλεξες μαθητή. Πάτησε «Καταχώριση παρουσίας» για αποθήκευση.";

    searchInput.value = name;
  }

  function renderResults(students) {
    hideResults();

    if (!students.length) {
      status.textContent = "Δεν βρέθηκαν μαθητές.";
      return;
    }

    status.textContent = `Βρέθηκαν ${students.length} αποτελέσματα. Επίλεξε έναν μαθητή.`;

    for (const student of students) {
      const button = document.createElement("button");

      button.type = "button";
      button.className =
        "block w-full border-b border-white/5 px-4 py-3 " +
        "text-left hover:bg-white/5 focus:bg-white/5";

      const name = document.createElement("span");
      name.className = "block text-sm font-semibold text-white";
      name.textContent = `${student.first_name} ${student.last_name}`.trim();

      const details = document.createElement("span");
      details.className = "mt-1 block text-xs text-zinc-400";
      details.textContent =
        `ID: ${student.id} · ${student.barcode || "Χωρίς barcode"}` +
        (student.email ? ` · ${student.email}` : "");

      button.append(name, details);

      button.addEventListener("click", () => chooseStudent(student));

      results.append(button);
    }

    results.classList.remove("hidden");
    searchInput.setAttribute("aria-expanded", "true");
  }

  searchInput.addEventListener("input", () => {
    clearSelection();
    hideResults();

    clearTimeout(timer);
    pendingRequest?.abort();

    const thisGeneration = ++generation;

    const query = searchInput.value.trim().replace(/\s+/g, " ");

    if (!query || (Array.from(query).length < 2 && !/^\d+$/.test(query))) {
      status.textContent = "Πληκτρολόγησε τουλάχιστον δύο χαρακτήρες ή ένα ID.";
      return;
    }

    status.textContent = "Αναζήτηση...";

    // Avoid a database request after every keystroke.
    timer = setTimeout(async () => {
      pendingRequest = new AbortController();

      try {
        const response = await fetch(
          `/admin/attendance/manual/search?q=${encodeURIComponent(query)}`,
          {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
            signal: pendingRequest.signal,
          },
        );

        if (
          response.redirected &&
          new URL(response.url).pathname === "/login"
        ) {
          window.location.assign("/login");
          return;
        }

        if (
          !response.ok ||
          !response.headers.get("content-type")?.includes("application/json")
        ) {
          throw new Error("Search failed");
        }

        const students = await response.json();

        if (thisGeneration !== generation) return;

        if (!Array.isArray(students)) {
          throw new Error("Invalid results");
        }

        renderResults(students);
      } catch (error) {
        if (error.name === "AbortError" || thisGeneration !== generation) {
          return;
        }

        status.textContent = "Η αναζήτηση απέτυχε. Δοκίμασε ξανά.";
      }
    }, 300);
  });

  changeButton?.addEventListener("click", () => {
    clearSelection();
    searchInput.value = "";

    status.textContent = "Πληκτρολόγησε όνομα, επώνυμο, ID ή barcode.";

    searchInput.focus();
  });
}
