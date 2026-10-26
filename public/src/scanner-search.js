const input = document.getElementById("scanner-student-search");
const results = document.getElementById("scanner-search-results");
const status = document.getElementById("scanner-search-status");

const selected = document.getElementById("scanner-selected-student");
const selectedName = document.getElementById("scanner-selected-name");
const selectedCode = document.getElementById("scanner-selected-code");
const studentId = document.getElementById("scanner-student-id");
const clearButton = document.getElementById("scanner-clear-selection");

if (
  input &&
  results &&
  status &&
  selected &&
  selectedName &&
  selectedCode &&
  studentId
) {
  let timeoutId;
  let activeRequest;
  let sequence = 0;

  const closeResults = () => {
    results.replaceChildren();
    results.classList.add("hidden");
    input.setAttribute("aria-expanded", "false");
  };

  const clearSelection = () => {
    studentId.value = "";
    selected.classList.add("hidden");
  };

  const pickStudent = (student) => {
    sequence++;
    clearTimeout(timeoutId);
    activeRequest?.abort();
    closeResults();

    const fullName = `${student.first_name} ${student.last_name}`.trim();

    selectedName.textContent = fullName;
    selectedCode.textContent = `ID: ${student.id} · Barcode: ${student.barcode || "—"}`;

    studentId.value = String(student.id);
    selected.classList.remove("hidden");
    input.value = fullName;

    status.textContent =
      "Επιλεγμένος μαθητής. Η παρουσία δεν έχει καταχωριστεί ακόμα.";
  };

  input.addEventListener("input", () => {
    clearSelection();
    closeResults();

    clearTimeout(timeoutId);
    activeRequest?.abort();

    const current = ++sequence;
    const query = input.value.trim().replace(/\s+/g, " ");

    if (!query || ([...query].length < 2 && !/^\d+$/.test(query))) {
      status.textContent =
        "Γράψε τουλάχιστον δύο χαρακτήρες ή έναν αριθμητικό κωδικό.";
      return;
    }

    status.textContent = "Αναζήτηση...";

    // Wait 300ms before querying MySQL.
    timeoutId = setTimeout(async () => {
      activeRequest = new AbortController();

      try {
        const response = await fetch(
          `/admin/scanner/search?q=${encodeURIComponent(query)}`,
          {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
            signal: activeRequest.signal,
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
          throw new Error("Search request failed");
        }

        const students = await response.json();

        // Ignore results from an older search.
        if (current !== sequence) return;

        if (!Array.isArray(students)) {
          throw new Error("Invalid results");
        }

        closeResults();

        if (students.length === 0) {
          status.textContent = "Δεν βρέθηκαν μαθητές.";
          return;
        }

        status.textContent = `Βρέθηκαν ${students.length} αποτελέσματα. Επίλεξε μαθητή.`;

        for (const student of students) {
          const button = document.createElement("button");
          button.type = "button";
          button.className =
            "block w-full border-b border-white/5 px-4 py-3 " +
            "text-left hover:bg-white/5 focus:bg-white/5";

          const name = document.createElement("span");
          name.className = "block font-semibold text-sm text-white";
          name.textContent =
            `${student.first_name} ${student.last_name}`.trim();

          const details = document.createElement("span");
          details.className = "block text-xs text-zinc-400 mt-1";
          details.textContent = `ID: ${student.id} · ${student.barcode || "Χωρίς barcode"}`;

          button.append(name, details);
          button.addEventListener("click", () => pickStudent(student));
          results.append(button);
        }

        results.classList.remove("hidden");
        input.setAttribute("aria-expanded", "true");
      } catch (error) {
        if (error.name !== "AbortError" && current === sequence) {
          status.textContent = "Η αναζήτηση απέτυχε. Δοκίμασε ξανά.";
        }
      }
    }, 300);
  });

  clearButton?.addEventListener("click", () => {
    sequence++;
    clearSelection();
    closeResults();

    input.value = "";
    status.textContent = "Αναζήτησε μαθητή για να καταχωρίσεις παρουσία.";

    input.focus();
  });
}
