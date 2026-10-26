/* IFT Academy — Greek calendars for Admin date fields. */

let assetsReady;
const instances = [];
const boundForms = new WeakSet();

function loadAsset(tag, attributes) {
  return new Promise((resolve, reject) => {
    const element = Object.assign(document.createElement(tag), attributes);

    const timer = setTimeout(() => {
      element.remove();
      reject(new Error("Calendar asset timed out."));
    }, 15000);

    element.onload = () => {
      clearTimeout(timer);
      resolve();
    };

    element.onerror = () => {
      clearTimeout(timer);
      element.remove();
      reject(new Error("Calendar asset not found."));
    };

    document.head.appendChild(element);
  });
}

function loadCalendarAssets() {
  assetsReady ??= Promise.all([
    loadAsset("link", {
      rel: "stylesheet",
      href: "/vendor/flatpickr/flatpickr.min.css",
    }),
    loadAsset("link", {
      rel: "stylesheet",
      href: "/styles/admin-calendar.css?v=1",
    }),
    loadAsset("script", {
      src: "/vendor/flatpickr/flatpickr.min.js",
    }),
  ]);

  return assetsReady;
}

const greek = {
  firstDayOfWeek: 1,

  weekdays: {
    shorthand: ["Κυρ", "Δευ", "Τρι", "Τετ", "Πεμ", "Παρ", "Σαβ"],
    longhand: [
      "Κυριακή",
      "Δευτέρα",
      "Τρίτη",
      "Τετάρτη",
      "Πέμπτη",
      "Παρασκευή",
      "Σάββατο",
    ],
  },

  months: {
    shorthand: [
      "Ιαν",
      "Φεβ",
      "Μαρ",
      "Απρ",
      "Μαϊ",
      "Ιουν",
      "Ιουλ",
      "Αυγ",
      "Σεπ",
      "Οκτ",
      "Νοε",
      "Δεκ",
    ],
    longhand: [
      "Ιανουάριος",
      "Φεβρουάριος",
      "Μάρτιος",
      "Απρίλιος",
      "Μάιος",
      "Ιούνιος",
      "Ιούλιος",
      "Αύγουστος",
      "Σεπτέμβριος",
      "Οκτώβριος",
      "Νοέμβριος",
      "Δεκέμβριος",
    ],
  },

  ordinal: () => "",
  rangeSeparator: " έως ",
  weekAbbreviation: "Εβδ.",
  scrollTitle: "Κύλιση για αλλαγή",
  toggleTitle: "Αλλαγή",
  yearAriaLabel: "Έτος",
  monthAriaLabel: "Μήνας",
  hourAriaLabel: "Ώρα",
  minuteAriaLabel: "Λεπτά",
  time_24hr: true,
};

const greekDate = new Intl.DateTimeFormat("el-GR", {
  day: "numeric",
  month: "long",
  year: "numeric",
});

function bindForm(form) {
  if (!form || boundForms.has(form)) {
    return;
  }

  boundForms.add(form);

  // Preserve required-field validation.
  form.addEventListener("submit", (event) => {
    const missing = instances.find(
      (calendar) =>
        calendar.input.form === form &&
        calendar.input.required &&
        !calendar.input.disabled &&
        !calendar.input.value,
    );

    if (!missing) {
      return;
    }

    event.preventDefault();

    missing.iftError.hidden = false;
    missing.altInput.setAttribute("aria-invalid", "true");
    missing.altInput.focus();
    missing.open();
  });

  // Keep the visible date correct after resetting a form.
  form.addEventListener("reset", () => {
    setTimeout(() => {
      instances
        .filter((calendar) => calendar.input.form === form)
        .forEach((calendar) => {
          calendar.setDate(calendar.iftInitialValue, false);
          calendar.iftError.hidden = true;
          calendar.altInput.removeAttribute("aria-invalid");
        });
    }, 0);
  });
}

export default async function adminDatePickers() {
  const inputs = [
    ...document.querySelectorAll(
      'input[type="date"], input[type="datetime-local"]',
    ),
  ].filter((input) => !input._flatpickr && !input.disabled && !input.readOnly);

  if (!inputs.length) {
    return;
  }

  // Keep native inputs untouched unless all assets load.
  await loadCalendarAssets();

  if (typeof window.flatpickr !== "function") {
    throw new Error("Flatpickr did not initialise.");
  }

  for (const input of inputs) {
    const labels = [...(input.labels || [])];
    const initialValue = input.value;
    const withTime = input.type === "datetime-local";

    const storedFormat = withTime ? "Y-m-d\\TH:i" : "Y-m-d";

    const shownFormat = withTime ? "j F Y, H:i" : "j F Y";

    const suffix = input.id || `ift-date-${instances.length + 1}`;

    const calendar = window.flatpickr(input, {
      locale: greek,

      // Greek text is visible; the original input keeps ISO format.
      altInput: true,
      altInputClass: "ift-date-display",
      dateFormat: storedFormat,
      altFormat: shownFormat,
      ariaDateFormat: "j F Y",

      enableTime: withTime,
      time_24hr: true,
      minuteIncrement: 1,

      // Use our calendar on mobile as well.
      disableMobile: true,
      animate: false,

      allowInput: false,
      clickOpens: true,

      // Respect constraints already present in the PHP view.
      minDate: input.min || undefined,
      maxDate: input.max || undefined,

      formatDate(date, format, locale) {
        if (format === "j F Y") {
          return greekDate.format(date);
        }

        if (format === "j F Y, H:i") {
          const hours = String(date.getHours()).padStart(2, "0");
          const minutes = String(date.getMinutes()).padStart(2, "0");

          return `${greekDate.format(date)}, ${hours}:${minutes}`;
        }

        return window.flatpickr.formatDate(date, format, locale);
      },

      onReady(_dates, _value, instance) {
        instance.iftInitialValue = initialValue;

        const visible = instance.altInput;

        visible.id = `${suffix}-display`;
        visible.placeholder = "Επιλογή ημερομηνίας";
        visible.setAttribute("aria-haspopup", "dialog");
        visible.setAttribute("aria-expanded", "false");

        labels.forEach((label) => {
          label.htmlFor = visible.id;
        });

        const popup = instance.calendarContainer;

        popup.classList.add("ift-calendar");
        popup.id = `${suffix}-calendar`;
        popup.setAttribute("role", "dialog");
        popup.setAttribute("aria-label", "Επιλογή ημερομηνίας");

        visible.setAttribute("aria-controls", popup.id);

        const error = document.createElement("p");

        error.id = `${suffix}-date-error`;
        error.className = "ift-date-error";
        error.textContent = "Επίλεξε ημερομηνία.";
        error.hidden = true;
        error.setAttribute("role", "alert");

        visible.insertAdjacentElement("afterend", error);
        instance.iftError = error;

        visible.setAttribute(
          "aria-describedby",
          [input.getAttribute("aria-describedby"), error.id]
            .filter(Boolean)
            .join(" "),
        );

        const footer = document.createElement("div");
        footer.className = "ift-calendar-footer";

        const addButton = (text, handler) => {
          const button = document.createElement("button");

          button.type = "button";
          button.textContent = text;
          button.addEventListener("click", handler);

          footer.appendChild(button);

          return button;
        };

        instance.iftToday = addButton("Σήμερα", () => {
          instance.setDate(new Date(), true);
          instance.close();
        });

        addButton("Καθαρισμός", () => {
          instance.clear();
          instance.close();
        });

        addButton("Κλείσιμο", () => {
          instance.close();
        });

        popup.appendChild(footer);
      },

      onOpen(_dates, _value, instance) {
        instance.altInput.setAttribute("aria-expanded", "true");

        const now = window.flatpickr.formatDate(new Date(), storedFormat);

        instance.iftToday.disabled = Boolean(
          (input.min && now < input.min) || (input.max && now > input.max),
        );
      },

      onClose(_dates, _value, instance) {
        instance.altInput.setAttribute("aria-expanded", "false");
      },

      onChange(_dates, _value, instance) {
        if (instance.iftError) {
          instance.iftError.hidden = true;
        }

        instance.altInput.removeAttribute("aria-invalid");
      },
    });

    instances.push(calendar);
    bindForm(input.form);
  }
}
