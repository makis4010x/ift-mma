<?php

/** @var string|null $manualStatus */

$messages = [
    'saved' => 'Η παρουσία καταχωρίστηκε επιτυχώς.',
    'duplicate' => 'Υπάρχει ήδη παρουσία στα τελευταία 90 λεπτά. Δεν έγινε νέα καταχώριση.',
    'unknown' => 'Ο μαθητής δεν βρέθηκε.',
    'invalid' => 'Μη έγκυρος μαθητής.'
];

$message = is_string($manualStatus)
    ? ($messages[$manualStatus] ?? null)
    : null;
?>

<section class="space-y-6">

    <div>
        <h2 class="text-2xl font-extrabold text-white">
            Χειροκίνητη παρουσία
        </h2>

        <p class="mt-2 text-sm text-zinc-500">
            Αναζήτησε με όνομα, επώνυμο, ονοματεπώνυμο,
            ID ή barcode.
        </p>
    </div>

    <?php if ($message !== null): ?>
        <div
            role="status"
            class="rounded-xl border p-4 text-sm font-semibold
            <?= $manualStatus === 'saved'
                ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-300'
                : 'border-amber-500/20 bg-amber-500/10 text-amber-300'
            ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <div
        class="max-w-3xl space-y-4 rounded-2xl border
               border-white/[.08] bg-[#18181c] p-6">

        <label
            for="manualSearch"
            class="block text-sm font-semibold text-zinc-300">
            Αναζήτηση μαθητή
        </label>

        <input
            id="manualSearch"
            type="search"
            maxlength="120"
            autocomplete="off"
            placeholder="π.χ. Παπαδόπουλος Γιώργος ή IFT000042"
            aria-controls="manualResults"
            aria-expanded="false"
            class="w-full rounded-xl border border-white/10
                   bg-[#111114] px-4 py-3 text-white
                   outline-none focus:border-red-500">

        <p
            id="manualSearchStatus"
            role="status"
            aria-live="polite"
            class="text-sm text-zinc-400">
            Πληκτρολόγησε τουλάχιστον δύο χαρακτήρες
            ή ένα αριθμητικό ID.
        </p>

        <!-- Live search results -->
        <div
            id="manualResults"
            class="hidden overflow-hidden rounded-xl border
                   border-white/10 bg-[#111114]"></div>

        <!-- Selected student -->
        <div
            id="manualSelected"
            class="hidden rounded-xl border border-emerald-500/20
                   bg-emerald-500/5 p-5">

            <p class="text-xs font-bold uppercase tracking-wide text-emerald-400">
                Επιλεγμένος μαθητής
            </p>

            <p
                id="manualSelectedName"
                class="mt-2 text-lg font-bold text-white"></p>

            <p
                id="manualSelectedBarcode"
                class="mt-1 text-sm text-zinc-400"></p>

            <form
                action="/admin/attendance/manual"
                method="POST"
                class="mt-5 flex flex-wrap gap-3">

                <?= \Framework\Csrf::field() ?>

                <input
                    type="hidden"
                    name="student_id"
                    id="manualStudentId"
                    value="">

                <button
                    type="submit"
                    class="rounded-xl bg-red-600 px-5 py-3
                           text-sm font-bold text-white
                           hover:bg-red-700">
                    Καταχώριση παρουσίας
                </button>

                <button
                    type="button"
                    id="manualChangeStudent"
                    class="rounded-xl border border-white/10
                           px-5 py-3 text-sm text-zinc-300
                           hover:bg-white/5">
                    Αλλαγή μαθητή
                </button>

            </form>
        </div>
    </div>

    <a
        href="/admin/attendance"
        class="inline-flex text-sm font-semibold
               text-zinc-400 hover:text-white">
        Πίσω στο ιστορικό παρουσιών
    </a>

</section>

<script
    type="module"
    src="/src/admin/manual-attendance.js"></script>