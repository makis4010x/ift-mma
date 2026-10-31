const form = document.getElementById("scanForm");
const input = document.getElementById("scanInput");
const symbol = document.getElementById("scanSymbol");
const title = document.getElementById("scanTitle");
const name = document.getElementById("scanName");
const message = document.getElementById("scanMessage");
const fullscreen = document.getElementById("fullscreen");

if (form && input && symbol && title && name && message) {
  let busy = false;
  let resetTimer;

  // ========================================
  // IFT Academy - MP3 Scanner Sounds
  // ========================================

  const paidSound = new Audio("/sounds/paid.mp3");
  const unpaidSound = new Audio("/sounds/unpaid.mp3");

  paidSound.preload = "auto";
  unpaidSound.preload = "auto";

  paidSound.volume = 1.0;
  unpaidSound.volume = 1.0;

  function playScanSound(result) {
    // Stop any previously playing sound
    paidSound.pause();
    unpaidSound.pause();

    paidSound.currentTime = 0;
    unpaidSound.currentTime = 0;

    // Only play payment sounds for recognized students
    if (!["saved", "duplicate"].includes(result.status)) {
      return;
    }

    let sound = null;

    // Student has paid coverage
    if (result.membership_status === "covered") {
      sound = paidSound;
    }

    // Student has no paid coverage
    else if (result.membership_status === "uncovered") {
      sound = unpaidSound;
    }

    // Unknown or review status: no misleading payment sound
    if (!sound) {
      return;
    }

    sound.play().catch((error) => {
      console.warn("IFT Scanner audio error:", error);
    });
  }

  // Membership coverage notification
  const coverageBox = document.createElement("div");

  coverageBox.style.cssText = `
    display: none;
    margin-top: 16px;
    padding: 16px;
    border-radius: 12px;
    border: 1px solid transparent;
    font-weight: 600;
    line-height: 1.6;
    white-space: pre-line;
    text-align: center;
  `;

  coverageBox.setAttribute("role", "status");
  coverageBox.setAttribute("aria-live", "polite");

  message.insertAdjacentElement("afterend", coverageBox);

  function formatGreekDate(value) {
    if (typeof value !== "string" || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
      return null;
    }

    const [year, month, day] = value.split("-");
    return `${day}/${month}/${year}`;
  }

  // Coverage end is exclusive in our database.
  function lastCoveredDay(value) {
    if (typeof value !== "string" || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
      return null;
    }

    const date = new Date(`${value}T00:00:00Z`);
    date.setUTCDate(date.getUTCDate() - 1);

    const day = String(date.getUTCDate()).padStart(2, "0");
    const month = String(date.getUTCMonth() + 1).padStart(2, "0");

    return `${day}/${month}/${date.getUTCFullYear()}`;
  }

  function showCoverage(result) {
    coverageBox.style.display = "none";
    coverageBox.textContent = "";

    // Show membership status on first and duplicate scans.
    if (!["saved", "duplicate"].includes(result.status)) {
      return;
    }

    if (result.membership_status === "covered") {
      coverageBox.style.backgroundColor = "#112c1d";
      coverageBox.style.borderColor = "#15803d";
      coverageBox.style.color = "#86efac";

      const until = lastCoveredDay(result.covered_until);
      const renewal = formatGreekDate(result.covered_until);

      coverageBox.textContent = until
        ? `ΕΝΕΡΓΗ ΠΛΗΡΩΜΕΝΗ ΚΑΛΥΨΗ ΕΩΣ ${until}\nΕπόμενη ανανέωση: ${renewal}`
        : "ΕΝΕΡΓΗ ΠΛΗΡΩΜΕΝΗ ΚΑΛΥΨΗ";
    } else if (result.membership_status === "uncovered") {
      coverageBox.style.backgroundColor = "#421719";
      coverageBox.style.borderColor = "#dc2626";
      coverageBox.style.color = "#fca5a5";

      const since = formatGreekDate(result.uncovered_since);

      coverageBox.textContent = since
        ? `ΧΩΡΙΣ ΠΛΗΡΩΜΕΝΗ ΚΑΛΥΨΗ ΑΠΟ ${since}\nΧρειάζεται ανανέωση συνδρομής.`
        : "ΔΕΝ ΥΠΑΡΧΕΙ ΚΑΤΑΧΩΡΙΣΜΕΝΗ ΠΛΗΡΩΜΕΝΗ ΚΑΛΥΨΗ\nΧρειάζεται ενημέρωση συνδρομής.";
    } else if (result.membership_status === "review") {
      coverageBox.style.backgroundColor = "#292929";
      coverageBox.style.borderColor = "#71717a";
      coverageBox.style.color = "#e4e4e7";

      coverageBox.textContent = "ΑΠΑΙΤΕΙΤΑΙ ΕΛΕΓΧΟΣ ΣΥΝΔΡΟΜΗΣ";
    } else {
      return;
    }

    coverageBox.style.display = "block";
  }

  function focusScanner() {
    if (!busy) {
      input.focus({ preventScroll: true });
    }
  }

  function reset() {
    busy = false;
    coverageBox.style.display = "none";
    coverageBox.textContent = "";

    symbol.textContent = "▥";
    title.textContent = "Περάστε το barcode σας";
    title.className = "";

    name.textContent = "";
    message.textContent = "Ο scanner είναι έτοιμος για την επόμενη παρουσία.";

    input.value = "";

    focusScanner();
  }

  function showResult(result) {
    clearTimeout(resetTimer);

    title.className = "";
    name.textContent = "";

    const fullName = result.name || "Μαθητής";

    switch (result.status) {
      case "saved":
        if (result.membership_status === "covered") {
          symbol.textContent = "✓";
          title.textContent = `Καλώς ήρθες, ${fullName}!`;
          title.className = "saved";

          message.textContent = "Η είσοδος καταχωρίστηκε.";
        } else if (result.membership_status === "uncovered") {
          symbol.textContent = "!";
          title.textContent = `Προσοχή, ${fullName}!`;
          title.className = "error";

          message.textContent = "Η είσοδος καταχωρίστηκε κανονικά.";
        } else {
          symbol.textContent = "✓";
          title.textContent = `Καλώς ήρθες, ${fullName}!`;
          title.className = "saved";

          message.textContent =
            "Η είσοδος καταχωρίστηκε. " + "Χρειάζεται έλεγχος της συνδρομής.";
        }
        break;

      case "duplicate":
        name.textContent = fullName;

        title.textContent = "Έχεις ήδη καταχωριστεί";

        if (result.membership_status === "covered") {
          symbol.textContent = "✓";
          title.className = "saved";
        } else if (result.membership_status === "uncovered") {
          symbol.textContent = "!";
          title.className = "error";
        } else {
          symbol.textContent = "!";
          title.className = "duplicate";
        }

        message.textContent = "Δεν προστέθηκε δεύτερη παρουσία (εντός 6 ωρών).";

        break;

      case "unknown":
        symbol.textContent = "×";
        title.textContent = "Άγνωστο barcode";
        title.className = "error";

        message.textContent = "Ζήτησε βοήθεια από τη γραμματεία.";
        break;

      default:
        symbol.textContent = "!";
        title.textContent = "Δεν έγινε καταχώριση";
        title.className = "error";

        message.textContent =
          result.status === "unauthorized"
            ? "Απαιτείται σύνδεση διαχειριστή."
            : "Δοκίμασε ξανά ή ζήτησε βοήθεια.";
    }

    showCoverage(result);
    playScanSound(result);

    input.value = "";

    resetTimer = setTimeout(
      () => {
        if (result.status === "unauthorized") {
          window.location.reload();
        } else {
          reset();
        }
      },
      result.status === "saved" ? 6000 : 3500,
    );
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    if (busy) return;

    const barcode = input.value.trim();

    if (!barcode) return;

    busy = true;

    title.textContent = "Έλεγχος παρουσίας...";
    name.textContent = "";
    message.textContent = "";

    try {
      const response = await fetch("/scanner/check-in", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
        },
        body: new FormData(form),
      });

      if (response.status === 401 || response.status === 403) {
        showResult({
          status: "unauthorized",
          name: "",
        });
        return;
      }

      const result = await response.json();

      if (!response.ok) {
        throw new Error("Request failed");
      }

      showResult(result);
    } catch (error) {
      showResult({
        status: "error",
        name: "",
      });
    }
  });

  // Fullscreen button.
  fullscreen?.addEventListener("click", async () => {
    try {
      await document.documentElement.requestFullscreen();
    } catch (error) {
      // Browser may not allow fullscreen.
    }

    focusScanner();
  });

  // Keep keyboard scanner ready.
  document.addEventListener("click", (event) => {
    if (!event.target.closest("button")) {
      focusScanner();
    }
  });

  document.addEventListener("visibilitychange", () => {
    if (!document.hidden) {
      focusScanner();
    }
  });

  reset();
}
