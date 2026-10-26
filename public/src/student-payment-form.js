const form = document.getElementById("coverage-payment-form");

if (form) {
  const plan = form.querySelector("#coverage-agreement");
  const months = form.querySelector("#coverage-months");
  const amount = form.querySelector("#coverage-amount");
  const special = form.querySelector("#coverage-special");
  const notes = form.querySelector("#coverage-notes");
  const hint = form.querySelector("#coverage-form-hint");

  // Integer cents, not floating-point calculations.
  const cents = (raw) => {
    const value = String(raw ?? "")
      .trim()
      .replace(",", ".");

    if (!/^\d{1,8}(?:\.\d{1,2})?$/.test(value)) {
      return null;
    }

    const [whole, fraction = ""] = value.split(".");

    return Number(whole) * 100 + Number(fraction.padEnd(2, "0"));
  };

  const money = (value) =>
    `${Math.floor(value / 100)},${String(value % 100).padStart(2, "0")}`;

  function update(fill = false, inferMonths = false) {
    amount.setCustomValidity("");
    notes.setCustomValidity("");

    const fee = cents(plan.selectedOptions[0]?.dataset.fee);

    if (fee === null || fee <= 0) {
      hint.textContent = "Επίλεξε συμφωνημένη συνδρομή μαθητή.";
      return;
    }

    if (fill && !special.checked) {
      amount.value = money(fee * Number(months.value));
    }

    const paid = cents(amount.value);

    if (paid === null || paid <= 0) {
      amount.setCustomValidity("Συμπλήρωσε θετικό ποσό με έως δύο δεκαδικά.");

      hint.textContent = "Συμπλήρωσε έγκυρο ποσό, π.χ. 45,00.";

      return;
    }

    // Example: typing 90 for a 45/month agreement selects 2 months.
    if (inferMonths && !special.checked && paid % fee === 0) {
      const count = paid / fee;

      if (count >= 1 && count <= 12) {
        months.value = String(count);
      }
    }

    const count = Number(months.value);
    const expected = count * fee;

    if (paid !== expected && !special.checked) {
      amount.setCustomValidity(
        "Το ποσό δεν αντιστοιχεί στους μήνες. " +
          "Επίλεξε ειδικό ποσό ή διόρθωσέ το.",
      );

      hint.textContent =
        `${count} μήνες × ${money(fee)} € = ${money(expected)} €. ` +
        "Διαφορετικό ποσό χρειάζεται ρητή εξαίρεση.";

      return;
    }

    if (paid !== expected && [...notes.value.trim()].length < 5) {
      notes.setCustomValidity(
        "Συμπλήρωσε αιτιολογία τουλάχιστον 5 χαρακτήρων.",
      );
    }

    hint.textContent =
      paid === expected
        ? `${count} ${count === 1 ? "μήνας" : "μήνες"} ` +
          `× ${money(fee)} € = ${money(paid)} €.`
        : `Ειδικό ποσό ${money(paid)} € για ${count} ` +
          `${count === 1 ? "μήνα" : "μήνες"}. ` +
          "Απαιτείται αιτιολογία.";
  }

  plan.addEventListener("change", () => update(true));

  months.addEventListener("change", () => update(true));

  amount.addEventListener("input", () => {
    update(false, true);
  });

  special.addEventListener("change", () => {
    update(false, true);
  });

  notes.addEventListener("input", () => update());

  form.addEventListener("submit", (event) => {
    update();

    if (!form.checkValidity()) {
      event.preventDefault();
      form.reportValidity();
    }
  });

  // Do not overwrite restored values after validation errors.
  update(amount.value.trim() === "");
}
