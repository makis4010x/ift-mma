<?php loadPartial('head'); ?>
<?php loadPartial('navbar'); ?>

    <main>
      <!-- Login -->
      <div class="section login container">
        <div class="login__content">
          <p class="login__tagline">MEMBERS AREA</p>
          <h1 class="login__title">ΣΥΝΔΕΣΗ</h1>

          <form class="login__form" action="#">
            <label for="email">Email</label>
            <input
              class="login__form-email"
              type="email"
              name="email"
              id="email"
              required
            />

            <label for="password">Password</label>
            <input
              class="login__form-password"
              type="password"
              name="password"
              id="password"
              required
            />
            <button class="btn__red login__button" type="submit">
              ΣΥΝΔΕΣΗ
            </button>
          </form>

          <p class="login__descr">
            Οι λογαριασμοί δημιουργούνται από τη σχολή.
          </p>
        </div>
      </div>
    </main>

<?php loadPartial('footer'); ?>