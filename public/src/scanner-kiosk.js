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

  function focusScanner() {
    if (!busy) {
      input.focus({ preventScroll: true });
    }
  }

  function reset() {
    busy = false;

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
    name.textContent = result.name || "";

    switch (result.status) {
      case "saved":
        symbol.textContent = "✓";
        title.textContent = "Καλώς ήρθες!";
        title.className = "saved";

        message.textContent = "Η παρουσία καταχωρίστηκε.";
        break;

      case "duplicate":
        symbol.textContent = "!";
        title.textContent = "Έχεις ήδη καταχωριστεί";
        title.className = "duplicate";

        message.textContent = "Δεν προστέθηκε δεύτερη παρουσία.";
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

    input.value = "";

    // Return to standby after 3.5 seconds.
    resetTimer = setTimeout(() => {
      if (result.status === "unauthorized") {
        window.location.reload();
      } else {
        reset();
      }
    }, 3500);
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
