<?php

/**
 * @var array<string, mixed> $student
 * @var array<int, array<string, mixed>> $membershipPlans
 */

$membershipError = \Framework\Session::getFlash(
    'student_membership_error'
);

$membershipSuccess = \Framework\Session::getFlash(
    'student_membership_success'
);

$membershipOld = \Framework\Session::getFlash(
    'student_membership_old',
    []
);

if (!is_array($membershipOld)) {
    $membershipOld = [];
}
?>

<div class="rounded-2xl border border-white/[.08] bg-[#18181c] p-6">

    <h3 class="text-lg font-bold text-white">
        Ανάθεση νέας συνδρομής
    </h3>

    <p class="mt-2 mb-5 text-sm text-zinc-400">
        Η τιμή του προγράμματος είναι ενδεικτική.
        Το ποσό που θα αποθηκευτεί είναι το συμφωνημένο
        ποσό του συγκεκριμένου μαθητή.
    </p>

    <?php if (
        is_string($membershipError) &&
        $membershipError !== ''
    ): ?>
        <p
            role="alert"
            class="mb-5 rounded-xl border border-amber-500/20
                   bg-amber-500/10 p-4 text-sm text-amber-300">
            <?= e($membershipError) ?>
        </p>
    <?php endif; ?>

    <?php if (
        is_string($membershipSuccess) &&
        $membershipSuccess !== ''
    ): ?>
        <p
            role="status"
            class="mb-5 rounded-xl border border-emerald-500/20
                   bg-emerald-500/10 p-4 text-sm text-emerald-300">
            <?= e($membershipSuccess) ?>
        </p>
    <?php endif; ?>

    <?php if ($membershipPlans === []): ?>

        <p class="rounded-xl bg-[#111114] p-4 text-sm text-amber-300">
            Δεν υπάρχουν ενεργά προγράμματα.
            Δημιούργησε πρώτα ένα από τις Συνδρομές.
        </p>

    <?php else: ?>

        <form
            id="student-membership-form"
            action="/admin/students/<?= (int) $student['id'] ?>/memberships"
            method="POST"
            class="grid gap-4 md:grid-cols-2">

            <?= \Framework\Csrf::field() ?>

            <!-- Program -->
            <div class="md:col-span-2">

                <label
                    for="membership-plan"
                    class="mb-2 block text-sm text-zinc-300">
                    Πρόγραμμα
                </label>

                <select
                    id="membership-plan"
                    name="plan_id"
                    required
                    class="w-full rounded-xl border border-white/10
                           bg-[#111114] px-4 py-3 text-sm text-white">
                    <option value="">
                        Επίλεξε πρόγραμμα
                    </option>

                    <?php foreach ($membershipPlans as $plan): ?>

                        <option
                            value="<?= (int) $plan['id'] ?>"
                            data-fee="<?= e($plan['default_monthly_fee']) ?>"
                            data-currency="<?= e($plan['currency']) ?>"
                            <?= (string) ($membershipOld['plan_id'] ?? '') === (string) $plan['id']
                                ? 'selected'
                                : '' ?>>
                            <?= e($plan['name']) ?>
                            (<?= e($plan['code']) ?>)
                        </option>

                    <?php endforeach; ?>

                </select>

                <p
                    id="membership-price-hint"
                    class="mt-2 text-xs text-zinc-500">
                    Η τρέχουσα τιμή θα εμφανιστεί
                    όταν επιλέξεις πρόγραμμα.
                </p>

            </div>

            <!-- Start date -->
            <div>

                <label
                    for="membership-from"
                    class="mb-2 block text-sm text-zinc-300">
                    Ημερομηνία έναρξης
                </label>

                <input
                    id="membership-from"
                    name="valid_from"
                    type="date"
                    required
                    value="<?= e($membershipOld['valid_from'] ?? '') ?>"
                    class="w-full rounded-xl border border-white/10
                           bg-[#111114] px-4 py-3 text-sm text-white">

            </div>

            <!-- End date -->
            <div>

                <label
                    for="membership-to"
                    class="mb-2 block text-sm text-zinc-300">
                    Λήξη (προαιρετική)
                </label>

                <input
                    id="membership-to"
                    name="valid_to"
                    type="date"
                    value="<?= e($membershipOld['valid_to'] ?? '') ?>"
                    class="w-full rounded-xl border border-white/10
                           bg-[#111114] px-4 py-3 text-sm text-white">

                <p class="mt-2 text-xs text-zinc-500">
                    Η λήξη είναι η πρώτη ημέρα που δεν ισχύει
                    η συνδρομή. Κενό = χωρίς ορισμένη λήξη.
                </p>

            </div>

            <!-- Agreed price -->
            <div>

                <label
                    for="membership-fee"
                    class="mb-2 block text-sm text-zinc-300">
                    Συμφωνημένο ποσό / μήνα (€)
                </label>

                <input
                    id="membership-fee"
                    name="agreed_monthly_fee"
                    type="text"
                    inputmode="decimal"
                    required
                    placeholder="π.χ. 45,00"
                    value="<?= e($membershipOld['agreed_monthly_fee'] ?? '') ?>"
                    class="w-full rounded-xl border border-white/10
                           bg-[#111114] px-4 py-3 text-sm text-white">

                <p class="mt-2 text-xs text-zinc-500">
                    Μπορεί να διαφέρει από τον τιμοκατάλογο.
                </p>

            </div>

            <div class="flex items-end">

                <button
                    type="submit"
                    class="w-full rounded-xl bg-red-600 px-5 py-3
                           text-sm font-bold text-white
                           hover:bg-red-700">
                    Καταχώριση συνδρομής
                </button>

            </div>

        </form>

    <?php endif; ?>

</div>

<script
    type="module"
    src="/src/student-memberships.js"></script>