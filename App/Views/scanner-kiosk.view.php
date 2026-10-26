<?php

/** @var bool $authorized */
/** @var bool $configured */
/** @var string|null $notice */
?>
<!doctype html>
<html lang="el">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>IFT Academy | Scanner εισόδου</title>
  <style>
    :root {
      color-scheme: dark;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      background: #0d0d10;
      color: #fafafa;
      font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
    }

    .page {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      padding: 24px;
    }

    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }

    .brand {
      font-size: 17px;
      font-weight: 900;
      letter-spacing: .12em;
    }

    .brand span {
      color: #f43f5e;
    }

    .content {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .panel {
      width: min(100%, 720px);
      text-align: center;
      padding: 34px 20px;
    }

    .symbol {
      font-size: clamp(58px, 12vw, 100px);
      line-height: 1.1;
      margin: 10px;
    }

    h1 {
      margin: 12px 0;
      font-size: clamp(28px, 5vw, 56px);
      letter-spacing: -.04em;
    }

    p {
      color: #a1a1aa;
      font-size: clamp(16px, 2vw, 21px);
      line-height: 1.6;
    }

    .name {
      font-weight: 800;
      color: #fff;
      font-size: clamp(20px, 3vw, 30px);
      min-height: 1.4em;
    }

    .saved {
      color: #4ade80;
    }

    .duplicate {
      color: #fbbf24;
    }

    .error {
      color: #f87171;
    }

    .action {
      font: inherit;
      font-weight: 700;
      border: 1px solid #3f3f46;
      background: #27272a;
      color: #fff;
      border-radius: 12px;
      padding: 11px 16px;
      cursor: pointer;
    }

    .action:hover {
      background: #3f3f46;
    }

    .footer {
      display: flex;
      gap: 10px;
      align-items: center;
      justify-content: center;
      flex-wrap: wrap;
    }

    .pin-input {
      width: 100%;
      max-width: 370px;
      padding: 14px;
      border-radius: 12px;
      border: 1px solid #52525b;
      background: #18181b;
      color: #fff;
      font: inherit;
      text-align: center;
      letter-spacing: .08em;
      text-transform: uppercase;
    }

    .activate-form {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      flex-wrap: wrap;
      margin-top: 25px;
    }

    .hidden-scanner-input {
      position: absolute;
      left: -9999px;
      opacity: 0;
      width: 1px;
      height: 1px;
    }

    .small {
      font-size: 13px;
    }
  </style>
</head>

<body>
  <div class="page">
    <header class="header">
      <div class="brand">IFT <span>ACADEMY</span></div>
      <?php if ($authorized): ?>
        <button type="button" class="action" id="fullscreen">Πλήρης οθόνη</button>
      <?php endif; ?>
    </header>

    <main class="content">
      <?php if (!$authorized): ?>
        <section class="panel">
          <div class="symbol" aria-hidden="true">🔒</div>
          <h1>Ενεργοποίηση scanner</h1>
          <?php if (!$configured): ?>
            <p class="error">Ο scanner δεν έχει ρυθμιστεί. Εκτέλεσε πρώτα το setup στον υπολογιστή διαχείρισης.</p>
          <?php else: ?>
            <p>Πληκτρολόγησε τον κωδικό ενεργοποίησης της συσκευής.</p>
            <?php if (is_string($notice) && $notice !== ''): ?>
              <p class="error" role="alert"><?= e($notice) ?></p>
            <?php endif; ?>
            <form action="/scanner/activate" method="POST" class="activate-form">
              <?= \Framework\Csrf::field() ?>
              <input class="pin-input" type="password" name="pin" maxlength="128"
                autocomplete="off" aria-label="Κωδικός ενεργοποίησης scanner" required autofocus>
              <button class="action" type="submit">Ενεργοποίηση</button>
            </form>
          <?php endif; ?>
        </section>
      <?php else: ?>
        <section class="panel" aria-live="polite" aria-atomic="true">
          <div id="scanSymbol" class="symbol" aria-hidden="true">▥</div>
          <h1 id="scanTitle">Περάστε το barcode σας</h1>
          <div class="name" id="scanName"></div>
          <p id="scanMessage">Ο scanner είναι έτοιμος για την επόμενη παρουσία.</p>
          <form id="scanForm" autocomplete="off">
            <input type="hidden" name="_csrf" value="<?= e(\Framework\Csrf::token()) ?>">
            <input class="hidden-scanner-input" id="scanInput" name="barcode"
              type="text" maxlength="32" autocomplete="off" autofocus
              aria-label="Barcode μαθητή">
          </form>
        </section>
      <?php endif; ?>
    </main>

    <footer class="footer">
      <?php if ($authorized): ?>
        <span class="small" style="color:#a1a1aa">Η παρουσία καταχωρίζεται αυτόματα μετά τη σάρωση.</span>
      <?php else: ?>
        <span class="small" style="color:#a1a1aa">Πρόσβαση μόνο σε εξουσιοδοτημένη συσκευή.</span>
      <?php endif; ?>
    </footer>
  </div>
  <?php if ($authorized): ?>
    <script src="/src/scanner-kiosk.js" defer></script>
  <?php endif; ?>
</body>

</html>