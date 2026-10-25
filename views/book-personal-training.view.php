<?php loadPartial('head'); ?>
<?php loadPartial('navbar'); ?>

<main>
      <section class="section personal_training container">
        <p class="personal_training__tagline">PERSONAL TRAINING</p>
        <h1 class="personal_training__title">ΚΛΕΙΣΕ ΤΗΝ ΠΡΟΠΟΝΗΣΗ ΤΟΥ.</h1>
        <p class="personal_training__descr">
          Επίλεξε ημερομηνία και διαθέσιμη ώρα. Η διαθεσιμότητα ορίζεται από τη
          σχολή.
        </p>
        <div class="personal_training__forms">
          <div class="personal_training__left-form">
            <h2 class="personal_training__left-form__title">ΠΩΣ ΛΕΙΤΟΥΡΓΕΙ</h2>
            <p class="personal_training__left-form__descr">
              1. Επιλέγεις διαθέσιμη ημερομηνία και ώρα.
            </p>
            <p class="personal_training__left-form__descr">
              2. Συμπληρώνεις email ή κινητό.
            </p>
            <p class="personal_training__left-form__descr">
              3. Λαμβάνεις επιβεβαίωση της κράτησης.
            </p>
            <p class="personal_training__left-form__descr">
              Η πρώτη κράτηση μπορεί να γίνει και χωρίς λογαριασμό.
            </p>
          </div>
          <div class="personal_training__right-form">
            <h2 class="personal_training__right-form__title">ΝΕΑ ΚΡΑΤΗΣΗ</h2>
            <form class="personal__training-form" action="">
              <label for="personal__training-fullname">Ονοματεπώνυμο</label>
              <input
                type="text"
                class="personal__training-fullname"
                id="personal__training-fullname"
              />
              <label for="personal__training-email">Email</label>
              <input
                type="email"
                class="personal__training-email"
                id="personal__training-email"
              />
              <label for="personal__training-mobile">Κινητό</label>
              <input
                type="tel"
                class="personal__training-mobile"
                id="personal__training-mobile"
              />
              <label for="personal__training-date">Ημερομηνία</label>
              <input
                type="date"
                class="personal__training-date"
                id="personal__training-date"
              />
              <label for="personal__training-time">'Ωρα</label>
              <input
                type="time"
                class="personal__training-time"
                id="personal__training-time"
              />
              <button class="btn__red personal__training-btn">
                ΑΙΤΗΜΑ ΚΡΑΤΗΣΗΣ
              </button>
            </form>
          </div>
        </div>
      </section>
    </main>

<?php loadPartial('footer'); ?>