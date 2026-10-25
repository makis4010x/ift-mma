const schedule = () => {
  const scheduleElement = document.querySelector(".schedule");

  if (!scheduleElement) return;

  const table = scheduleElement.querySelector(".schedule__table");
  const mobile = scheduleElement.querySelector(".schedule__mobile");

  if (!table || !mobile) return;

  const title = mobile.querySelector(".schedule__day-title");
  const cards = mobile.querySelector(".schedule__cards");
  const dots = mobile.querySelector(".schedule__dots");

  const prev = mobile.querySelector(".schedule__arrow--prev");
  const next = mobile.querySelector(".schedule__arrow--next");

  if (!title || !cards || !dots || !prev || !next) return;

  const days = ["Δευτέρα", "Τρίτη", "Τετάρτη", "Πέμπτη", "Παρασκευή"];

  /* =========================================
     HELPERS
  ========================================= */

  function isEmptyActivity(text) {
    const value = text.trim();

    return value === "" || value === "-" || value === "—";
  }

  /* =========================================
     EMPTY CELLS - DESKTOP
  ========================================= */

  table.querySelectorAll(".schedule__cell").forEach((cell) => {
    const activities = [...cell.querySelectorAll(".schedule__activity")];

    // If the cell already contains an empty marker,
    // leave it unchanged.
    if (cell.classList.contains("schedule__cell--empty")) {
      return;
    }

    // Check whether both zones are empty.
    const allEmpty =
      activities.length === 0 ||
      activities.every((activity) => isEmptyActivity(activity.textContent));

    if (allEmpty) {
      cell.classList.add("schedule__cell--empty");

      const mark = document.createElement("span");
      mark.className = "schedule__empty-mark";
      mark.textContent = "—";

      cell.replaceChildren(mark);
    } else {
      // Remove empty activities while keeping
      // the non-empty training sessions.
      activities.forEach((activity) => {
        if (isEmptyActivity(activity.textContent)) {
          activity.remove();
        }
      });
    }
  });

  /* =========================================
     READ SCHEDULE DATA
  ========================================= */

  const rows = [...table.querySelectorAll("tbody tr")];

  const scheduleData = days.map((day, dayIndex) => {
    const sessions = rows
      .map((row) => {
        const time = row.querySelector("th")?.textContent.trim() || "";

        const cell = row.querySelectorAll("td")[dayIndex];

        const activities = cell
          ? [...cell.querySelectorAll(".schedule__activity")]
              .map((activity) => ({
                name: activity.textContent.trim(),
                zone: Number(activity.dataset.zone) || 0,
              }))
              .filter((activity) => !isEmptyActivity(activity.name))
          : [];

        return {
          time,
          activities,
        };
      })
      .filter((session) => session.activities.length > 0);

    return {
      day,
      sessions,
    };
  });

  /* =========================================
     DETECT CURRENT DAY
  ========================================= */

  // JavaScript:
  // Sunday = 0
  // Monday = 1
  // Tuesday = 2
  // Wednesday = 3
  // Thursday = 4
  // Friday = 5
  // Saturday = 6

  const today = new Date().getDay();

  // On weekends, show Monday.
  let currentDay = today >= 1 && today <= 5 ? today - 1 : 0;

  /* =========================================
     CREATE MOBILE ACTIVITY
  ========================================= */

  function createActivity(activityData) {
    const activity = document.createElement("div");

    activity.className = "schedule__activity";
    activity.textContent = activityData.name;

    // Preserve the original training zone
    // when available.
    if (activityData.zone) {
      activity.dataset.zone = activityData.zone;
    }

    return activity;
  }

  /* =========================================
     RENDER SELECTED DAY
  ========================================= */

  function renderDay(index) {
    currentDay = (index + days.length) % days.length;

    const day = scheduleData[currentDay];

    // Update title.
    title.textContent = day.day;

    // Clear previous cards.
    cards.replaceChildren();

    // Render sessions.
    day.sessions.forEach((session) => {
      const card = document.createElement("article");
      card.className = "schedule__card";

      const time = document.createElement("time");
      time.className = "schedule__card-time";
      time.textContent = session.time;

      const body = document.createElement("div");
      body.className = "schedule__card-body";

      session.activities.forEach((activityData) => {
        body.appendChild(createActivity(activityData));
      });

      card.append(time, body);
      cards.appendChild(card);
    });

    // No scheduled sessions.
    if (!day.sessions.length) {
      const empty = document.createElement("p");

      empty.className = "schedule__empty";
      empty.textContent = "Δεν υπάρχουν προπονήσεις.";

      cards.appendChild(empty);
    }

    // Update day indicators.
    [...dots.children].forEach((dot, i) => {
      const active = i === currentDay;

      dot.classList.toggle("schedule__dot--active", active);
      dot.setAttribute("aria-pressed", String(active));
    });
  }

  /* =========================================
     CREATE DAY INDICATORS
  ========================================= */

  days.forEach((day, index) => {
    const dot = document.createElement("button");

    dot.type = "button";
    dot.className = "schedule__dot";
    dot.setAttribute("aria-label", day);

    dot.addEventListener("click", () => {
      renderDay(index);
    });

    dots.appendChild(dot);
  });

  /* =========================================
     PREVIOUS / NEXT DAY
  ========================================= */

  prev.addEventListener("click", () => {
    renderDay(currentDay - 1);
  });

  next.addEventListener("click", () => {
    renderDay(currentDay + 1);
  });

  /* =========================================
     MOBILE SWIPE
  ========================================= */

  let startX = 0;
  let startY = 0;

  cards.addEventListener(
    "touchstart",
    (event) => {
      startX = event.changedTouches[0].screenX;
      startY = event.changedTouches[0].screenY;
    },
    { passive: true },
  );

  cards.addEventListener(
    "touchend",
    (event) => {
      const diffX = event.changedTouches[0].screenX - startX;
      const diffY = event.changedTouches[0].screenY - startY;

      // Ignore small movements.
      if (Math.abs(diffX) < 50) return;

      // Ignore vertical scrolling.
      if (Math.abs(diffX) <= Math.abs(diffY)) return;

      // Swipe left: next day.
      // Swipe right: previous day.
      renderDay(currentDay + (diffX < 0 ? 1 : -1));
    },
    { passive: true },
  );

  /* =========================================
     INITIALIZE
  ========================================= */

  renderDay(currentDay);

  mobile.hidden = false;
  scheduleElement.classList.add("schedule--ready");
  console.log("test");
};

export default schedule;
