<?php

/**
 * Barcode scanner interface.
 *
 * @var string|null $scannerStatus
 */

$messages = [
    'saved' => 'Η παρουσία καταχωρίστηκε επιτυχώς!',
    'duplicate' => 'Ο μαθητής έχει ήδη πρόσφατη παρουσία. Δεν καταχωρίστηκε δεύτερη.',
    'unknown' => 'Δεν βρέθηκε μαθητής με αυτό το barcode.',
    'invalid' => 'Το barcode δεν είναι έγκυρο.'
];

$message = is_string($scannerStatus)
    ? ($messages[$scannerStatus] ?? null)
    : null;

$isSuccess = $scannerStatus === 'saved';
?>

<section class="space-y-6">

    <div>
        <h2 class="text-2xl font-extrabold text-white">
            Χειροκίνητη Καταχώριση Παρουσίας
        </h2>

        <p class="mt-2 text-sm text-zinc-500">
            Αναζήτηση μαθητή
        </p>
    </div>


    <!-- Open student-facing scanner -->
    <div class="flex flex-wrap items-center gap-3">

        <a
            href="/scanner"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-red-700">
            <i data-lucide="scan-barcode" class="size-5"></i>

            Άνοιγμα Scanner

            <i data-lucide="external-link" class="size-4"></i>
        </a>

    </div>


    <div class="max-w-2xl rounded-2xl border border-white/[.08] bg-[#18181c] p-6 sm:p-8">

        <div class="mb-6 flex items-center gap-4">

            <div class="flex size-14 items-center justify-center rounded-2xl bg-red-600/10 text-red-400">
                <i data-lucide="scan-barcode" class="size-7"></i>
            </div>

            <div>
                <h3 class="text-lg font-bold text-white">
                    Σάρωση μαθητή
                </h3>

                <p class="mt-1 text-sm text-zinc-500">
                    Συνδέστε USB scanner ή πληκτρολογήστε έναν κωδικό.
                </p>
            </div>
        </div>

        <?php if ($message !== null): ?>
            <div
                role="status"
                aria-live="polite"
                class="mb-6 rounded-xl border p-5 text-sm font-semibold
                <?= $isSuccess
                    ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-300'
                    : 'border-amber-500/20 bg-amber-500/10 text-amber-300'
                ?>">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <!-- Live student search -->
        <div class="mb-6 space-y-4 rounded-xl border border-white/10 bg-[#111114] p-5">

            <div>
                <label
                    for="scanner-student-search"
                    class="mb-2 block text-sm font-semibold text-white">
                    Αναζήτηση μαθητή
                </label>

                <input
                    id="scanner-student-search"
                    type="search"
                    maxlength="120"
                    autocomplete="off"
                    aria-controls="scanner-search-results"
                    aria-expanded="false"
                    autofocus
                    placeholder="Όνομα, επώνυμο, ID ή IFT000042"
                    class="w-full rounded-xl border border-white/10 bg-[#18181c] px-4 py-3 text-sm text-white outline-none focus:border-red-500">

                <p
                    id="scanner-search-status"
                    role="status"
                    aria-live="polite"
                    class="mt-2 text-xs text-zinc-500">
                    Γράψε τουλάχιστον δύο χαρακτήρες ή έναν αριθμητικό κωδικό.
                </p>
            </div>

            <!-- Search results -->
            <div
                id="scanner-search-results"
                class="hidden overflow-hidden rounded-xl border border-white/10"></div>

            <!-- Selected student -->
            <div
                id="scanner-selected-student"
                class="hidden rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-5">
                <p class="text-xs font-bold text-emerald-400">
                    Επιλεγμένος μαθητής
                </p>

                <p
                    id="scanner-selected-name"
                    class="mt-2 text-lg font-bold text-white"></p>

                <p
                    id="scanner-selected-code"
                    class="mt-1 text-sm text-zinc-400"></p>

                <form
                    action="/admin/scanner/scan"
                    method="POST"
                    class="mt-5 flex flex-wrap gap-3">
                    <?= \Framework\Csrf::field() ?>

                    <input
                        type="hidden"
                        id="scanner-student-id"
                        name="student_id"
                        value="">

                    <button
                        type="submit"
                        class="rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700">
                        Καταχώριση παρουσίας
                    </button>

                    <button
                        type="button"
                        id="scanner-clear-selection"
                        class="rounded-xl border border-white/10 px-4 py-3 text-sm text-zinc-300 hover:bg-white/5">
                        Αλλαγή μαθητή
                    </button>
                </form>
            </div>
        </div>

        <div class="mt-6 border-t border-white/10 pt-5">
            <p class="text-xs leading-6 text-zinc-500">
                Όριο επαναλαμβανόμενης σάρωσης:
                <strong class="text-zinc-300">
                    <?= \App\Support\AttendanceRecorder::DUPLICATE_MINUTES ?>
                    λεπτά
                </strong>.
                Η επόμενη έγκυρη είσοδος μετά από αυτό
                το διάστημα καταχωρίζεται κανονικά.
            </p>
        </div>
    </div>

    <a
        href="/admin/attendance"
        class="inline-flex items-center gap-2 text-sm font-semibold text-zinc-400 hover:text-white">
        <i data-lucide="arrow-left" class="size-4"></i>
        Προβολή ιστορικού παρουσιών
    </a>

</section>

<script type="module" src="/src/scanner-search.js"></script>