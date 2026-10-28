<?php

/**
 * @var array<string, mixed> $activitySettings
 * @var array<string, string> $settingsOld
 * @var string|null $settingsError
 * @var string|null $settingsSuccess
 */

$activityUnits = [
    'days' => 'Ημέρες',
    'months' => 'Μήνες',
    'years' => 'Χρόνια'
];

$activityValue = (string) (
    $settingsOld['inactivity_value']
    ?? $activitySettings['inactivity_value']
);

$activityUnit = (string) (
    $settingsOld['inactivity_unit']
    ?? $activitySettings['inactivity_unit']
);
?>

<section class="space-y-6">

    <div>
        <h2 class="text-2xl font-extrabold text-white">
            Ρυθμίσεις σχολής
        </h2>

        <p class="mt-2 text-sm text-zinc-400">
            Όριο αδράνειας μαθητών βάσει τελευταίας πληρωμής.
        </p>
    </div>

    <?php if (
        is_string($settingsError) &&
        $settingsError !== ''
    ): ?>

        <p
            role="alert"
            class="rounded-xl border border-amber-500/20
                   bg-amber-500/10 p-4 text-sm text-amber-300">
            <?= e($settingsError) ?>
        </p>

    <?php endif; ?>

    <?php if (
        is_string($settingsSuccess) &&
        $settingsSuccess !== ''
    ): ?>

        <p
            role="status"
            class="rounded-xl border border-emerald-500/20
                   bg-emerald-500/10 p-4 text-sm text-emerald-300">
            <?= e($settingsSuccess) ?>
        </p>

    <?php endif; ?>

    <div
        class="max-w-3xl rounded-2xl border border-white/10
               bg-[#18181c] p-6">

        <h3 class="text-lg font-bold text-white">
            Αδράνεια μαθητών
        </h3>

        <p class="mt-2 text-sm leading-6 text-zinc-400">
            Κριτήριο είναι η τελευταία πραγματική πληρωμή
            που δεν έχει ακυρωθεί. Δεν χρησιμοποιούνται οι παρουσίες
            ή τα εισαγόμενα στοιχεία χρέωσης των CSV.
        </p>

        <!-- Saved setting -->
        <div class="mt-5 rounded-xl bg-[#111114] p-4">

            <p class="text-xs text-zinc-400">
                Αποθηκευμένο όριο
            </p>

            <p class="mt-2 text-lg font-bold text-white">
                <?= (int) $activitySettings['inactivity_value'] ?>

                <?= e(
                    $activityUnits[$activitySettings['inactivity_unit']]
                ) ?>
            </p>

        </div>

        <!-- Settings form -->
        <form
            action="/admin/settings"
            method="POST"
            class="mt-6 grid gap-4 sm:grid-cols-2">

            <?= \Framework\Csrf::field() ?>

            <div>
                <label
                    for="inactivity-value"
                    class="mb-2 block text-sm text-zinc-300">
                    Χρονικό διάστημα
                </label>

                <input
                    id="inactivity-value"
                    name="inactivity_value"
                    type="number"
                    min="1"
                    max="999"
                    step="1"
                    required
                    value="<?= e($activityValue) ?>"
                    class="w-full rounded-xl border border-white/10
                           bg-[#111114] px-4 py-3 text-white
                           outline-none focus:border-red-500">
            </div>

            <div>
                <label
                    for="inactivity-unit"
                    class="mb-2 block text-sm text-zinc-300">
                    Μονάδα
                </label>

                <select
                    id="inactivity-unit"
                    name="inactivity_unit"
                    required
                    class="w-full rounded-xl border border-white/10
                           bg-[#111114] px-4 py-3 text-white
                           outline-none focus:border-red-500">

                    <?php foreach (
                        $activityUnits as $unit => $label
                    ): ?>

                        <option
                            value="<?= e($unit) ?>"
                            <?= $activityUnit === $unit
                                ? 'selected'
                                : '' ?>>
                            <?= e($label) ?>
                        </option>

                    <?php endforeach; ?>

                </select>
            </div>

            <div class="sm:col-span-2">

                <p class="mb-4 text-xs leading-6 text-zinc-400">
                    Παραδείγματα: 90 ημέρες, 3 μήνες ή 1 χρόνος.
                    Οι μήνες και τα χρόνια θα υπολογίζονται
                    ημερολογιακά, όχι ως σταθερός αριθμός ημερών.
                </p>

                <button
                    type="submit"
                    class="rounded-xl bg-red-600 px-5 py-3
                           text-sm font-bold text-white
                           hover:bg-red-700">
                    Αποθήκευση ρυθμίσεων
                </button>

            </div>

        </form>

    </div>

    <div
        class="max-w-3xl rounded-2xl border border-white/10
               bg-[#18181c] p-6">

        <h3 class="font-bold text-white">
            Τι αφορά αυτή η ρύθμιση
        </h3>

        <p class="mt-3 text-sm leading-7 text-zinc-400">
            Ο χαρακτηρισμός μαθητή παραμένει ξεχωριστός
            από την εξοφλημένη κάλυψη συνδρομής.
            Δεν δημιουργεί οφειλές, δεν ακυρώνει κάλυψη,
            δεν διαγράφει ιστορικό και δεν μπλοκάρει
            τον scanner ή τον λογαριασμό μαθητή.
        </p>

        <p class="mt-3 text-sm leading-7 text-zinc-400">
            Χωρίς έγκυρη πληρωμή θα εμφανίζεται
            «Χωρίς ιστορικό πληρωμής»,
            όχι αυτόματα «Ανενεργός».
        </p>

    </div>

</section>