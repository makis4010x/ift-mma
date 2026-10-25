<?php loadPartial('head'); ?>
<?php loadPartial('navbar'); ?>

<main>      
    <section class="section contact container">
        <p class="contact__tagline">ΕΠΙΚΟΙΝΩΝΙΑ</p>

        <h1 class="contact__title">ΕΛΑ ΝΑ ΜΙΛΗΣΟΥΜΕ.</h1>

        <p class="contact__descr">
          Ρώτησέ μας για τα προγράμματα, τις συνδρομές ή το Personal Training.
        </p>

        <div class="contact__forms">
          <!-- LEFT SIDE -->
          <div class="contact__forms-left">
            <h2 class="contact__subtitle">ΣΤΟΙΧΕΙΑ</h2>

            <div class="contact__details">
              <div class="contact__detail">
                <h3 class="contact__label">ΔΙΕΥΘΥΝΣΗ</h3>
                <p class="contact__value">Συμπλήρωσε τη διεύθυνση της σχολής</p>
              </div>

              <div class="contact__detail">
                <h3 class="contact__label">ΤΗΛΕΦΩΝΟ</h3>
                <a class="contact__link" href="tel:+302109236791">
                  210 9236791
                </a>
              </div>

              <div class="contact__detail">
                <h3 class="contact__label">INSTAGRAM</h3>
                <a
                  class="contact__link"
                  href="https://www.instagram.com/ift_mma/"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  @ift_mma
                </a>
              </div>
            </div>
          </div>

          <!-- RIGHT SIDE -->
          <div class="contact__forms-right">
            <h2 class="contact__subtitle">ΣΤΕΙΛΕ ΜΗΝΥΜΑ</h2>

            <form class="contact__form" action="" method="post">
              <div class="contact__field">
                <label class="contact__field-label" for="contact-name">
                  Ονοματεπώνυμο
                </label>
                <input
                  class="contact__input"
                  type="text"
                  id="contact-name"
                  name="name"
                  placeholder="Το ονοματεπώνυμό σου"
                  autocomplete="name"
                  required
                />
              </div>

              <div class="contact__field">
                <label class="contact__field-label" for="contact-email">
                  Email
                </label>
                <input
                  class="contact__input"
                  type="email"
                  id="contact-email"
                  name="email"
                  placeholder="example@email.com"
                  autocomplete="email"
                  required
                />
              </div>

              <div class="contact__field">
                <label class="contact__field-label" for="contact-phone">
                  Τηλέφωνο
                </label>
                <input
                  class="contact__input"
                  type="tel"
                  id="contact-phone"
                  name="phone"
                  placeholder="69XXXXXXXX"
                  autocomplete="tel"
                />
              </div>

              <div class="contact__field">
                <label class="contact__field-label" for="contact-message">
                  Μήνυμα
                </label>
                <textarea
                  class="contact__textarea"
                  id="contact-message"
                  name="message"
                  rows="5"
                  placeholder="Πώς μπορούμε να σε βοηθήσουμε;"
                  required
                ></textarea>
              </div>

              <button class="contact__btn btn__red" type="submit">
                ΑΠΟΣΤΟΛΗ ΜΗΝΥΜΑΤΟΣ
              </button>
            </form>
          </div>
        </div>
      </section>
      <section class="contact__map container">
        <iframe
          src="https://www.google.com/maps?q=Υψηλάντου+23,+Καλλιθέα,+Αττική&output=embed"
          width="100%"
          height="400"
          style="border: 0"
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"
          title="IFT Academy - Υψηλάντου 23, Καλλιθέα"
          allowfullscreen
        ></iframe>
      </section>
</main>

<?php loadPartial('footer'); ?>