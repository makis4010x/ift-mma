const dateTimePicker = () => {
  const dateTimeInputs = document.querySelectorAll(
    ".personal__training-date, .personal__training-time",
  );

  dateTimeInputs.forEach((input) => {
    input.addEventListener("click", () => {
      if (typeof input.showPicker === "function") {
        input.showPicker();
      }
    });
  });
};

export default dateTimePicker;
