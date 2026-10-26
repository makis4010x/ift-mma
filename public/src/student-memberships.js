const form = document.getElementById("student-membership-form");

if (form) {
  const plan = form.querySelector("#membership-plan");
  const fee = form.querySelector("#membership-fee");
  const hint = form.querySelector("#membership-price-hint");

  function showSuggestedPrice(fillAmount) {
    const option = plan.selectedOptions[0];
    const suggested = option?.dataset.fee;
    const currency = option?.dataset.currency || "EUR";

    if (!suggested) {
      hint.textContent =
        "Επίλεξε πρόγραμμα για να δεις την τρέχουσα ενδεικτική τιμή.";

      if (fillAmount) {
        fee.value = "";
      }

      return;
    }

    const formatted = Number(suggested).toLocaleString("el-GR", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });

    hint.textContent =
      `Τρέχουσα τιμή προγράμματος: ${formatted} ${currency}. ` +
      "Η συμφωνημένη τιμή του μαθητή μπορεί να διαφέρει.";

    if (fillAmount) {
      fee.value = suggested.replace(".", ",");
    }
  }

  plan.addEventListener("change", () => {
    showSuggestedPrice(true);
  });

  showSuggestedPrice(false);
}
