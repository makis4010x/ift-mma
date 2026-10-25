<?php loadPartial('head'); ?>

<?php loadPartial('navbar'); ?>
<main>
      <!-- PAGE INTRO -->
      <section class="container section lessons-hero">
        <p class="lessons-hero__tagline">IFT MMA ACADEMY</p>

        <h1 class="lessons-hero__title">ΤΑ ΜΑΘΗΜΑΤΑ ΜΑΣ.</h1>

        <p class="lessons-hero__descr">
          Από τα πρώτα σου βήματα μέχρι την αγωνιστική προετοιμασία. Ανακάλυψε
          το μάθημα που σου ταιριάζει και ξεκίνα την προπόνησή σου.
        </p>
      </section>

      <!-- MMA -->
      <section class="container section lesson" id="mma">
        <div class="lesson__content">
          <p class="lesson__tagline">01 / MIXED MARTIAL ARTS</p>

          <h2 class="lesson__title">MMA</h2>

          <p class="lesson__descr">
            Το MMA συνδυάζει τεχνικές από διαφορετικές πολεμικές τέχνες,
            περιλαμβάνοντας όρθια μάχη, πάλη και τεχνικές εδάφους.
          </p>

          <p class="lesson__descr">
            Στην προπόνηση δουλεύεις δύναμη, αντοχή, τεχνική και στρατηγική,
            ανεξάρτητα από το επίπεδο εμπειρίας σου.
          </p>

          <a href="./schedule.html" class="btn__red lesson__btn">
            ΔΕΣ ΤΟ ΠΡΟΓΡΑΜΜΑ
          </a>
        </div>

        <div class="lesson__media">
          <img
            class="lesson__image"
            src="./public/classes/mma.webp"
            alt="Προπόνηση MMA"
            loading="lazy"
          />
        </div>
      </section>

      <!-- KICKBOXING -->
      <section class="container section lesson lesson--reverse" id="kickboxing">
        <div class="lesson__content">
          <p class="lesson__tagline">02 / STRIKING</p>

          <h2 class="lesson__title">KICKBOXING</h2>

          <p class="lesson__descr">
            Το Kickboxing συνδυάζει χτυπήματα με χέρια και πόδια, προσφέροντας
            μια δυναμική προπόνηση για όλο το σώμα.
          </p>

          <p class="lesson__descr">
            Βελτίωσε την τεχνική, τα αντανακλαστικά, τον συντονισμό και τη
            φυσική σου κατάσταση μέσα από οργανωμένη προπόνηση.
          </p>

          <a href="./schedule.html" class="btn__red lesson__btn">
            ΔΕΣ ΤΟ ΠΡΟΓΡΑΜΜΑ
          </a>
        </div>

        <div class="lesson__media">
          <img
            class="lesson__image"
            src="./public/classes/kickboxing.webp"
            alt="Προπόνηση Kickboxing"
            loading="lazy"
          />
        </div>
      </section>

      <!-- BOXING -->
      <section class="container section lesson" id="boxing">
        <div class="lesson__content">
          <p class="lesson__tagline">03 / BOXING</p>

          <h2 class="lesson__title">BOXING</h2>

          <p class="lesson__descr">
            Η πυγμαχία είναι ένας συνδυασμός τεχνικής, ταχύτητας, άμυνας και
            σωστής κίνησης.
          </p>

          <p class="lesson__descr">
            Μέσα από συνδυασμούς χτυπημάτων και ασκήσεις βελτιώνεις τη φυσική
            σου κατάσταση, τη συγκέντρωση και τον έλεγχο του σώματος.
          </p>

          <a href="./schedule.html" class="btn__red lesson__btn">
            ΔΕΣ ΤΟ ΠΡΟΓΡΑΜΜΑ
          </a>
        </div>

        <div class="lesson__media">
          <img
            class="lesson__image"
            src="./public/classes/boxing.webp"
            alt="Προπόνηση Boxing"
            loading="lazy"
          />
        </div>
      </section>


      <!-- PERSONAL TRAINING -->
      <section class="container section lesson lesson--reverse" id="personal-training-section">
        <div class="lesson__content">
          <p class="lesson__tagline">06 / ONE ON ONE</p>

          <h2 class="lesson__title">PERSONAL TRAINING</h2>

          <p class="lesson__descr">
            Εξατομικευμένη προπόνηση προσαρμοσμένη στις ανάγκες, το επίπεδο και
            τους προσωπικούς σου στόχους.
          </p>

          <p class="lesson__descr">
            Με προσωπική καθοδήγηση μπορείς να επικεντρωθείς στη σωστή τεχνική
            και στη βελτίωση της φυσικής σου κατάστασης.
          </p>

          <a href="./personal_training.html" class="btn__red lesson__btn">
            ΚΛΕΙΣΕ PERSONAL
          </a>
        </div>

        <div class="lesson__media">
          <img
            class="lesson__image"
            src="./public/classes/personal.webp"
            alt="Personal Training"
            loading="lazy"
          />
        </div>
      </section>

      <!-- CTA -->
      <section class="container section lessons-cta">
        <div class="lessons-cta__content">
          <p class="lessons-cta__tagline">JOIN THE TEAM</p>

          <h2 class="lessons-cta__title">ΕΤΟΙΜΟΣ ΝΑ ΞΕΚΙΝΗΣΕΙΣ;</h2>

          <p class="lessons-cta__descr">
            Δεν ξέρεις ποιο μάθημα σου ταιριάζει; Επικοινώνησε μαζί μας και θα
            σε βοηθήσουμε να κάνεις το πρώτο βήμα.
          </p>
        </div>

        <a href="./contact.html" class="btn__transparency lessons-cta__btn">
          ΕΠΙΚΟΙΝΩΝΗΣΕ ΜΑΖΙ ΜΑΣ
        </a>
      </section>
    </main>

<?php loadPartial('footer'); ?>