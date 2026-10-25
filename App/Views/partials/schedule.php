      <section class="container section schedule">
        <p class="schedule__tagline">ΠΡΟΓΡΑΜΜΑ</p>
        <h1 class="schedule__title">ΤΟ ΠΡΟΓΡΑΜΜΑ ΜΑΣ</h1>

        <!-- MOBILE SCHEDULE -->
        <div class="schedule__mobile" aria-label="Πρόγραμμα ανά ημέρα" hidden>
          <div class="schedule__navigation">
            <button
              class="schedule__arrow schedule__arrow--prev"
              type="button"
              aria-label="Προηγούμενη ημέρα"
            >
              &#8592;<!-- Arrow  -->
            </button>

            <div class="schedule__day-info" aria-live="polite">
              <span class="schedule__day-label"> ΠΡΟΓΡΑΜΜΑ ΗΜΕΡΑΣ </span>

              <h2 class="schedule__day-title">Δευτέρα</h2>
            </div>

            <button
              class="schedule__arrow schedule__arrow--next"
              type="button"
              aria-label="Επόμενη ημέρα"
            >
              &#8594;
            </button>
          </div>

          <div class="schedule__dots" aria-label="Επιλογή ημέρας"></div>

          <div class="schedule__cards"></div>
        </div>

        <!-- DESKTOP SCHEDULE -->
        <div class="schedule__table-wrapper">
          <table class="schedule__table">
            <thead class="schedule__head">
              <tr class="schedule__row">
                <th class="schedule__header" scope="col">Ώρα</th>
                <th class="schedule__header" scope="col">Δευτέρα</th>
                <th class="schedule__header" scope="col">Τρίτη</th>
                <th class="schedule__header" scope="col">Τετάρτη</th>
                <th class="schedule__header" scope="col">Πέμπτη</th>
                <th class="schedule__header" scope="col">Παρασκευή</th>
              </tr>
            </thead>

            <tbody class="schedule__body">
              <!-- 10:30 - 11:45 -->
              <tr class="schedule__row">
                <th class="schedule__time" scope="row">10:30 - 11:45</th>

                <!-- MONDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Kickboxing / Boxing
                  </div>
                  <div class="schedule__activity" data-zone="2">
                    MMA Amateurs
                  </div>
                </td>

                <!-- TUESDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Kickboxing / Boxing
                  </div>
                  <div class="schedule__activity" data-zone="2">
                    MMA Amateurs
                  </div>
                </td>

                <!-- WEDNESDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Kickboxing / Boxing
                  </div>
                  <div class="schedule__activity" data-zone="2">
                    MMA Amateurs
                  </div>
                </td>

                <!-- THURSDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Kickboxing / Boxing
                  </div>
                  <div class="schedule__activity" data-zone="2">
                    MMA Amateurs
                  </div>
                </td>

                <!-- FRIDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Kickboxing / Boxing
                  </div>
                  <div class="schedule__activity" data-zone="2">
                    MMA Amateurs
                  </div>
                </td>
              </tr>

              <!-- 17:00 - 18:00 -->
              <tr class="schedule__row">
                <th class="schedule__time" scope="row">17:00 - 18:00</th>

                <!-- MONDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Kickboxing Juniors (4-7)
                  </div>
                  <div class="schedule__activity" data-zone="2">
                    Kickboxing Kids (8-11)
                  </div>
                </td>

                <!-- TUESDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Personal Training
                  </div>
                  <div class="schedule__activity" data-zone="2">
                    Personal Training
                  </div>
                </td>

                <!-- WEDNESDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Kickboxing Juniors (4-7)
                  </div>
                  <div class="schedule__activity" data-zone="2">
                    Kickboxing Kids (8-11)
                  </div>
                </td>

                <!-- THURSDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Personal Training
                  </div>
                  <div class="schedule__activity" data-zone="2">
                    Personal Training
                  </div>
                </td>

                <!-- FRIDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Kickboxing Juniors (4-7)
                  </div>
                  <div class="schedule__activity" data-zone="2">
                    Kickboxing Kids (8-11)
                  </div>
                </td>
              </tr>

              <!-- 18:00 - 19:10 -->
              <tr class="schedule__row">
                <th class="schedule__time" scope="row">18:00 - 19:10</th>

                <!-- MONDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Kickboxing / Boxing
                  </div>
                  <div class="schedule__activity" data-zone="2">
                    MMA Amateurs
                  </div>
                </td>

                <!-- TUESDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Kickboxing / Boxing
                  </div>
                  <div class="schedule__activity" data-zone="2">
                    MMA Amateurs
                  </div>
                </td>

                <!-- WEDNESDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Kickboxing / Boxing
                  </div>
                  <div class="schedule__activity" data-zone="2">
                    MMA Amateurs
                  </div>
                </td>

                <!-- THURSDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Kickboxing / Boxing
                  </div>
                  <div class="schedule__activity" data-zone="2">
                    MMA Amateurs
                  </div>
                </td>

                <!-- FRIDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Kickboxing / Boxing
                  </div>
                  <div class="schedule__activity" data-zone="2">
                    MMA Amateurs
                  </div>
                </td>
              </tr>

              <!-- 19:10 - 20:20 -->
              <tr class="schedule__row">
                <th class="schedule__time" scope="row">19:10 - 20:20</th>

                <!-- MONDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Personal Training
                  </div>
                  <div class="schedule__activity" data-zone="2">MMA Pro</div>
                </td>

                <!-- TUESDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Personal Training
                  </div>
                  <div class="schedule__activity" data-zone="2">MMA Pro</div>
                </td>

                <!-- WEDNESDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Personal Training
                  </div>
                  <div class="schedule__activity" data-zone="2">MMA Pro</div>
                </td>

                <!-- THURSDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Personal Training
                  </div>
                  <div class="schedule__activity" data-zone="2">MMA Pro</div>
                </td>

                <!-- FRIDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Personal Training
                  </div>
                  <div class="schedule__activity" data-zone="2">MMA Pro</div>
                </td>
              </tr>

              <!-- 20:20 - 21:30 -->
              <tr class="schedule__row">
                <th class="schedule__time" scope="row">20:20 - 21:30</th>

                <!-- MONDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Kickboxing / Boxing
                  </div>
                  <div class="schedule__activity" data-zone="2">
                    MMA Amateurs
                  </div>
                </td>

                <!-- TUESDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Kickboxing / Boxing
                  </div>
                  <div class="schedule__activity" data-zone="2">
                    MMA Amateurs
                  </div>
                </td>

                <!-- WEDNESDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Kickboxing / Boxing
                  </div>
                  <div class="schedule__activity" data-zone="2">
                    MMA Amateurs
                  </div>
                </td>

                <!-- THURSDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Kickboxing / Boxing
                  </div>
                  <div class="schedule__activity" data-zone="2">
                    MMA Amateurs
                  </div>
                </td>

                <!-- FRIDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Kickboxing / Boxing
                  </div>
                  <div class="schedule__activity" data-zone="2">
                    MMA / Grappling
                  </div>
                </td>
              </tr>

              <!-- 21:30 - 22:30 -->
              <tr class="schedule__row">
                <th class="schedule__time" scope="row">21:30 - 22:30</th>

                <!-- MONDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Personal Training
                  </div>
                  <div class="schedule__activity" data-zone="2">Grappling</div>
                </td>

                <!-- TUESDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Personal Training
                  </div>
                  <div class="schedule__activity" data-zone="2">Grappling</div>
                </td>

                <!-- WEDNESDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Personal Training
                  </div>
                  <div class="schedule__activity" data-zone="2">Grappling</div>
                </td>

                <!-- THURSDAY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1">
                    Personal Training
                  </div>
                  <div class="schedule__activity" data-zone="2">Grappling</div>
                </td>

                <!-- FRIDAY - EMPTY -->
                <td class="schedule__cell">
                  <div class="schedule__activity" data-zone="1"></div>
                  <div class="schedule__activity" data-zone="2"></div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>