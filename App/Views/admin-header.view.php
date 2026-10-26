<?php
$adminPageTitle = match ($adminPage ?? 'dashboard') {
  'students' => 'Μαθητές',
  'student-profile' => 'Προφίλ μαθητή',
  'student-form' => ($studentFormMode ?? '') === 'create'
    ? 'Νέος μαθητής'
    : 'Επεξεργασία μαθητή',
  'attendance' => 'Παρουσίες',
  'administrators' => 'Διαχειριστές',
  default => 'Dashboard',
  'scanner' => 'Scanner εισόδου',
  'membership-plans' => 'Συνδρομές',
};
?>
<!doctype html>
<html lang="el" class="dark">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>IFT MMA | Admin Panel</title>
  <!-- Tailwind Play CDN is intended for prototyping. Compile Tailwind for production. -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap"
    rel="stylesheet" />
  <!-- Custom stylesheet: sidebar, typography, responsive states -->
  <link rel="stylesheet" href="/styles/admin.css" />
</head>

<body class="min-h-screen bg-[#0e0e11] text-zinc-100 antialiased">
  <!-- ================= MOBILE OVERLAY ================= -->
  <!-- Backdrop displayed behind the sidebar on narrow screens -->
  <div
    id="sidebarOverlay"
    class="fixed inset-0 z-40 hidden bg-black/70 backdrop-blur-sm lg:hidden"
    aria-hidden="true"></div>

  <!-- ===================================================== -->
  <!-- SIDEBAR: logo, navigation links, shortcuts, user area -->
  <!-- ===================================================== -->
  <aside
    id="adminSidebar"
    data-sidebar
    class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-white/[.07] bg-[#151518] lg:translate-x-0"
    aria-label="Κύρια πλοήγηση">
    <div
      class="flex h-20 shrink-0 items-center justify-between border-b border-white/[.07] px-6">
      <a
        href="/admin"
        class="flex items-center gap-3"
        aria-label="IFT MMA αρχική διαχείρισης">
        <span
          data-logo
          class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-red-600 text-base font-extrabold tracking-tight text-white shadow-lg shadow-red-950/30">IFT</span>
        <span data-brand-text class="leading-tight"><span class="block text-sm font-extrabold tracking-[.14em]">IFT MMA</span><span
            class="block text-[.65rem] font-bold uppercase tracking-[.22em] text-zinc-500">Admin Panel</span></span>
      </a>
      <button
        id="closeMobileSidebar"
        class="rounded-lg p-2 text-zinc-400 hover:bg-white/5 hover:text-white lg:hidden"
        aria-label="Κλείσιμο μενού">
        <i data-lucide="x" class="size-5"></i>
      </button>
    </div>

    <!-- SIDEBAR NAVIGATION -->
    <div class="hide-scrollbar flex-1 space-y-7 overflow-y-auto px-4 py-6">
      <div data-sidebar-section>
        <div class="section-label">Επισκόπηση</div>
        <nav class="space-y-1">
          <a
            href="/admin"
            class="nav-link <?= (($adminPage ?? 'dashboard') === 'dashboard') ? 'active' : '' ?>"
            aria-current="page"
            title="Dashboard"><i data-lucide="layout-dashboard"></i><span data-sidebar-label>Dashboard</span></a>
          <a href="#reports" class="nav-link" title="Αναφορές"><i data-lucide="chart-no-axes-combined"></i><span data-sidebar-label>Αναφορές</span></a>
        </nav>
      </div>
      <div>
        <div data-sidebar-section class="section-label">
          Διαχείριση σχολής
        </div>
        <nav class="space-y-1">
          <a
            href="/admin/students"
            class="nav-link <?= in_array(
                              $adminPage ?? '',
                              ['students', 'student-profile', 'student-form'],
                              true
                            ) ? 'active' : '' ?>"
            title=" Μαθητές"><i data-lucide="users-round"></i><span data-sidebar-label>Μαθητές</span></a>
          <a
            href="/admin/membership-plans"
            class="nav-link <?= (($adminPage ?? '') === 'membership-plans') ? 'active' : '' ?>"
            title="Συνδρομές"><i data-lucide="badge-check"></i><span data-sidebar-label>Συνδρομές</span></a>
          <a href="#payments" class="nav-link" title="Πληρωμές"><i data-lucide="wallet"></i><span data-sidebar-label>Πληρωμές</span></a>

          <a
            href="/admin/attendance"
            class="nav-link <?= (($adminPage ?? '') === 'attendance') ? 'active' : '' ?>"
            title="Παρουσίες">
            <i data-lucide="calendar-check"></i>
            <span data-sidebar-label>Παρουσίες</span>
          </a>

          <a
            href="/admin/scanner"
            class="nav-link <?= (($adminPage ?? '') === 'scanner') ? 'active' : '' ?>"
            title="Scanner εισόδου">
            <i data-lucide="scan-barcode"></i>
            <span data-sidebar-label>Scanner εισόδου</span>
          </a>

          <a href="#schedule" class="nav-link" title="Πρόγραμμα"><i data-lucide="calendar-days"></i><span data-sidebar-label>Πρόγραμμα</span></a>
          <a href="#bookings" class="nav-link" title="Personal Bookings"><i data-lucide="calendar-check-2"></i><span data-sidebar-label>Personal Bookings</span></a>
          <a href="#services" class="nav-link" title="Υπηρεσίες"><i data-lucide="dumbbell"></i><span data-sidebar-label>Υπηρεσίες</span></a>
        </nav>
      </div>
      <div>
        <div data-sidebar-section class="section-label">
          Επικοινωνία & Site
        </div>
        <nav class="space-y-1">
          <a href="#email" class="nav-link" title="Emails"><i data-lucide="mail"></i><span data-sidebar-label>Emails</span></a>
          <a href="#content" class="nav-link" title="Περιεχόμενο Site"><i data-lucide="panels-top-left"></i><span data-sidebar-label>Περιεχόμενο Site</span></a>
        </nav>
      </div>
      <div>
        <div data-sidebar-section class="section-label">Σύστημα</div>
        <nav class="space-y-1">
          <a href="#settings" class="nav-link" title="Ρυθμίσεις"><i data-lucide="settings-2"></i><span data-sidebar-label>Ρυθμίσεις</span></a>
          <a
            href="/admin/administrators"
            class="nav-link"
            title="Διαχειριστές">
            <i data-lucide="shield-check"></i>
            <span data-sidebar-label>Διαχειριστές</span>
          </a>
        </nav>
      </div>
    </div>

    <!-- SIDEBAR FOOTER: website shortcut + current user -->
    <div data-sidebar-footer class="shrink-0 border-t border-white/[.07] p-4">
      <a
        href="#website"
        class="mb-3 flex items-center justify-between rounded-xl border border-white/10 bg-white/[.035] px-4 py-3 text-xs font-bold text-zinc-300 transition hover:bg-white/[.07]">Προβολή ιστοσελίδας
        <i data-lucide="arrow-up-right" class="size-4"></i></a>
      <div class="flex items-center gap-3 px-2">
        <div
          class="flex size-9 items-center justify-center rounded-full bg-zinc-800 text-xs font-extrabold text-zinc-200">
          AD
        </div>
        <div class="min-w-0">
          <p class="truncate text-xs font-bold">Administrator</p>
          <p class="text-[.7rem] text-zinc-500">IFT MMA</p>
        </div>
        <span class="ml-auto size-2 rounded-full bg-emerald-400"></span>
      </div>
      <!-- Sidebar Logout -->
      <form
        action="/logout"
        method="POST"
        class="mt-4 border-t border-white/[.07] pt-3">
        <?= \Framework\Csrf::field() ?>

        <button
          type="submit"
          class="nav-link w-full text-left !text-red-400 hover:!bg-red-500/10"
          title="Αποσύνδεση">
          <i data-lucide="log-out"></i>
          <span data-sidebar-label>Αποσύνδεση</span>
        </button>
      </form>
    </div>
  </aside>

  <!-- ===================================================== -->
  <!-- PAGE WRAPPER: everything right of the sidebar -->
  <!-- ===================================================== -->
  <div
    data-main
    class="min-h-screen transition-[margin] duration-200 lg:ml-72">
    <!-- =================================================== -->
    <!-- ADMIN HEADER: menu toggle, search, alerts, profile -->
    <!-- =================================================== -->
    <header
      class="sticky top-0 z-30 flex h-20 items-center justify-between gap-4 border-b border-white/[.07] bg-[#111114]/95 px-4 backdrop-blur-xl sm:px-6 lg:px-8">
      <div class="flex min-w-0 items-center gap-3 sm:gap-5">
        <button
          id="toggleSidebar"
          class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-white/10 text-zinc-400 transition hover:border-white/20 hover:bg-white/5 hover:text-white"
          aria-label="Εναλλαγή πλαϊνού μενού"
          aria-expanded="false"
          aria-controls="adminSidebar">
          <i data-lucide="panel-left" class="size-[1.15rem]"></i>
        </button>

        <div class="min-w-0">
          <div
            class="mb-1 hidden items-center gap-1.5 text-[.68rem] font-bold text-zinc-500 sm:flex">
            Διαχείριση
            <i data-lucide="chevron-right" class="size-3"></i>

            <span class="text-red-400">
              <?= e($adminPageTitle) ?>
            </span>
          </div>

          <h1
            class="truncate text-lg font-extrabold tracking-tight sm:text-xl">
            <?= e($adminPageTitle) ?>
          </h1>
        </div>
      </div>
      <div class="flex items-center gap-2 sm:gap-3">
        <div class="relative hidden md:block">
          <i
            data-lucide="search"
            class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-500"></i><input
            type="search"
            placeholder="Αναζήτηση..."
            aria-label="Αναζήτηση"
            class="w-56 rounded-xl border border-white/10 bg-white/[.035] py-2.5 pl-10 pr-4 text-sm text-white outline-none placeholder:text-zinc-600 focus:border-red-500/60 focus:ring-2 focus:ring-red-500/10 xl:w-72" />
        </div>
        <button
          class="flex size-10 items-center justify-center rounded-xl text-zinc-400 transition hover:bg-white/5 hover:text-white md:hidden"
          aria-label="Αναζήτηση"
          id="mobileSearchButton">
          <i data-lucide="search" class="size-5"></i>
        </button>
        <button
          class="relative flex size-10 items-center justify-center rounded-xl text-zinc-400 transition hover:bg-white/5 hover:text-white"
          aria-label="Ειδοποιήσεις"
          id="notificationsButton">
          <i data-lucide="bell" class="size-5"></i><span
            class="absolute right-2 top-2 size-2 rounded-full border-2 border-[#111114] bg-red-500"></span>
        </button>
        <div class="mx-1 hidden h-7 w-px bg-white/10 sm:block"></div>
        <button
          id="profileButton"
          class="flex items-center gap-2 rounded-xl p-1.5 transition hover:bg-white/5"
          aria-label="Μενού διαχειριστή"
          aria-expanded="false">
          <div
            class="flex size-9 items-center justify-center rounded-lg bg-red-600/20 text-xs font-extrabold text-red-400">
            AD
          </div>
          <span class="hidden text-left lg:block"><span class="block text-xs font-extrabold">Admin</span><span class="block text-[.65rem] text-zinc-500">Διαχειριστής</span></span><i
            data-lucide="chevron-down"
            class="hidden size-3.5 text-zinc-500 lg:block"></i>
        </button>
      </div>
      <!-- Profile menu (sample client-side only) -->
      <div
        id="profileMenu"
        class="absolute right-4 top-[4.7rem] hidden w-48 rounded-xl border border-white/10 bg-[#1d1d22] p-1.5 shadow-2xl sm:right-6 lg:right-8">
        <a
          href="#settings"
          class="block rounded-lg px-3 py-2.5 text-xs font-semibold text-zinc-300 hover:bg-white/5">Ρυθμίσεις λογαριασμού</a>
        <form action="/logout" method="POST">
          <?= \Framework\Csrf::field() ?>

          <button
            type="submit"
            class="block w-full rounded-lg px-3 py-2.5
                        text-left text-xs font-semibold
                        text-red-400 hover:bg-red-500/10">
            Αποσύνδεση
          </button>
        </form>
      </div>
    </header>
    <!-- =================================================== -->
    <!-- MAIN CONTENT: replace demo cards with PHP MVC views -->
    <!-- =================================================== -->
    <main id="dashboard" class="mx-auto max-w-7xl p-5 sm:p-8">

      <?php if (($adminPage ?? 'dashboard') === 'administrators'): ?>

        <?php require basePath(
          'App/Views/admin/administrators-content.view.php'
        ); ?>


      <?php elseif (($adminPage ?? 'dashboard') === 'students'): ?>

        <?php require basePath(
          'App/Views/admin/students-content.view.php'
        ); ?>

      <?php elseif (($adminPage ?? 'dashboard') === 'student-profile'): ?>

        <?php require basePath(
          'App/Views/admin/student-profile-content.view.php'
        ); ?>


      <?php elseif (($adminPage ?? 'dashboard') === 'student-form'): ?>

        <?php require basePath(
          'App/Views/admin/student-form-content.view.php'
        ); ?>

      <?php elseif (($adminPage ?? 'dashboard') === 'attendance'): ?>

        <?php require basePath(
          'App/Views/admin/attendance-content.view.php'
        ); ?>


      <?php elseif (($adminPage ?? 'dashboard') === 'scanner'): ?>

        <?php require basePath(
          'App/Views/admin/scanner-content.view.php'
        ); ?>

      <?php elseif (($adminPage ?? 'dashboard') === 'membership-plans'): ?>

        <?php require basePath(
          'App/Views/admin/membership-plans-content.view.php'
        ); ?>

      <?php else: ?>





        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
          <div>
            <div
              class="mb-2 inline-flex items-center gap-2 rounded-full border border-red-500/20 bg-red-500/10 px-3 py-1 text-[.67rem] font-extrabold uppercase tracking-wider text-red-400">
              <span class="size-1.5 rounded-full bg-red-500"></span> IFT Control
              Center
            </div>
            <h2 class="text-2xl font-extrabold tracking-tight sm:text-3xl">
              Καλώς ήρθες στο IFT MMA
            </h2>
            <p class="mt-2 text-sm text-zinc-500">
              Διαχειρίσου μαθητές, πληρωμές, παρουσίες και το πρόγραμμα της
              σχολής.
            </p>
          </div>
          <div
            class="rounded-xl border border-white/10 bg-white/[.03] px-4 py-2.5 text-xs font-semibold text-zinc-400">
            Admin workspace
          </div>
        </div>
        <!-- DASHBOARD KPI CARDS (sample data only) -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-2xl border border-white/[.08] bg-[#18181c] p-5">
            <div class="mb-6 flex items-center justify-between">
              <span class="text-xs font-bold text-zinc-400">Καταχωρισμένοι μαθητές</span><span class="rounded-xl bg-red-500/10 p-2.5 text-red-400"><i data-lucide="users-round" class="size-5"></i></span>
            </div>
            <div class="text-2xl font-extrabold">
              <?= isset($totalStudents)
                ? number_format((int) $totalStudents, 0, ',', '.')
                : '—'
              ?>
            </div>
            <p class="mt-2 text-xs text-zinc-500">
              Σύνολο εγγραφών μαθητών
            </p>
          </div>
          <div class="rounded-2xl border border-white/[.08] bg-[#18181c] p-5">
            <div class="mb-6 flex items-center justify-between">
              <span class="text-xs font-bold text-zinc-400">Σημερινές παρουσίες</span><span class="rounded-xl bg-violet-500/10 p-2.5 text-violet-400"><i data-lucide="scan-barcode" class="size-5"></i></span>
            </div>
            <div class="text-2xl font-extrabold">
              <?= isset($todayAttendances)
                ? number_format((int) $todayAttendances, 0, ',', '.')
                : '—'
              ?>
            </div>
            <p class="mt-2 text-xs text-zinc-500">
              Καταγραφές για σήμερα (ώρα Ελλάδας)
            </p>
          </div>
          <div class="rounded-2xl border border-white/[.08] bg-[#18181c] p-5">
            <div class="mb-6 flex items-center justify-between">
              <span class="text-xs font-bold text-zinc-400">Πληρωμές μήνα</span><span class="rounded-xl bg-emerald-500/10 p-2.5 text-emerald-400"><i data-lucide="wallet" class="size-5"></i></span>
            </div>
            <div class="text-2xl font-extrabold">—</div>
            <p class="mt-2 text-xs text-zinc-500">Σύνολο καταχωρίσεων</p>
          </div>
          <div class="rounded-2xl border border-white/[.08] bg-[#18181c] p-5">
            <div class="mb-6 flex items-center justify-between">
              <span class="text-xs font-bold text-zinc-400">Personal bookings</span><span class="rounded-xl bg-sky-500/10 p-2.5 text-sky-400"><i data-lucide="calendar-check-2" class="size-5"></i></span>
            </div>
            <div class="text-2xl font-extrabold">—</div>
            <p class="mt-2 text-xs text-zinc-500">Επερχόμενα ραντεβού</p>
          </div>
        </div>
        <div
          class="mt-5 rounded-2xl border border-dashed border-white/[.12] bg-white/[.015] px-6 py-14 text-center">
          <i
            data-lucide="panel-top"
            class="mx-auto mb-3 size-7 text-zinc-600"></i>
          <h3 class="text-sm font-bold">Περιεχόμενο της σελίδας</h3>
          <p class="mt-2 text-xs text-zinc-500">
            Το header και το sidebar είναι έτοιμα για σύνδεση με τις admin views
            του IFT.
          </p>
        </div>
      <?php endif; ?>
    </main>
  </div>

  <!-- Admin interactions: sidebar toggle / profile menu -->
  <script type="module" src="/src/admin.js"></script>
</body>

</html>