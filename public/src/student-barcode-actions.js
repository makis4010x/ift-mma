(() => {
  "use strict";

  const card = document.getElementById("student-barcode-card");
  const enlargeButton = document.getElementById("student-barcode-enlarge");
  const printButton = document.getElementById("student-barcode-print");
  const dialog = document.getElementById("student-barcode-dialog");
  const dialogContent = document.getElementById(
    "student-barcode-dialog-content",
  );
  const closeButton = document.getElementById("student-barcode-close");

  if (!card || !enlargeButton || !printButton || !dialog || !dialogContent) {
    return;
  }

  enlargeButton.addEventListener("click", () => {
    const enlarged = card.cloneNode(true);
    enlarged.removeAttribute("id");
    enlarged.classList.remove("inline-flex", "max-w-full", "overflow-x-auto");
    enlarged.classList.add("flex", "w-full");

    const svg = enlarged.querySelector("svg");

    if (svg) {
      svg.setAttribute("width", "100%");
      svg.style.maxWidth = "680px";
      svg.style.height = "auto";
    }

    dialogContent.replaceChildren(enlarged);

    if (!dialog.open) {
      dialog.showModal();
    }
  });

  closeButton?.addEventListener("click", () => dialog.close());

  dialog.addEventListener("click", (event) => {
    if (event.target === dialog) {
      dialog.close();
    }
  });

  // Create a separate print-only area.
  const printRoot = document.createElement("div");
  printRoot.id = "student-barcode-print-root";
  document.body.appendChild(printRoot);

  const printStyle = document.createElement("style");

  printStyle.textContent = `
    #student-barcode-print-root {
      display: none;
    }

    @media print {
      @page {
        size: A4;
        margin: 20mm;
      }

      body > :not(#student-barcode-print-root) {
        display: none !important;
      }

      #student-barcode-print-root {
        display: flex !important;
        flex-direction: column;
        align-items: center;
        justify-content: flex-start;
        gap: 18px;
        padding-top: 25mm;
        color: #111 !important;
        background: white !important;
        font-family: Arial, sans-serif;
        text-align: center;
      }

      #student-barcode-print-root h1 {
        font-size: 22pt;
        margin: 0;
      }

      #student-barcode-print-root p {
        font-size: 14pt;
        margin: 0;
      }

      #student-barcode-print-root .barcode-print-card {
        background: white !important;
        color: #111 !important;
        overflow: visible !important;
        padding: 12mm !important;
        max-width: 100% !important;
      }

      #student-barcode-print-root svg {
        display: block;
        max-width: 100% !important;
        height: auto !important;
      }
    }
  `;

  document.head.appendChild(printStyle);

  printButton.addEventListener("click", () => {
    const brand = document.createElement("h1");
    brand.textContent = "IFT ACADEMY";

    const name = document.createElement("p");
    name.textContent = card.dataset.studentName || "";

    const printableCard = card.cloneNode(true);
    printableCard.removeAttribute("id");
    printableCard.classList.add("barcode-print-card");

    printRoot.replaceChildren(brand, name, printableCard);

    window.print();
  });
})();
