<?php

/**
 * @var array<int, array<string, mixed>> $plans
 * @var array<string, mixed>|null $editPlan
 * @var array<string, mixed>|null $historyPlan
 * @var array<int, array<string, mixed>> $priceHistory
 * @var string|null $planError
 * @var string|null $planSuccess
 */

$formatFee = static fn(mixed $value): string =>
number_format((float) $value, 2, ',', '.') . ' €';

$formatUtc = static function (string $value): string {
    return (new DateTimeImmutable(
        $value,
        new DateTimeZone('UTC')
    ))->setTimezone(
        new DateTimeZone('Europe/Athens')
    )->format('d/m/Y H:i:s');
};
?>

<section class="space-y-6">

    <div>
        <h2 class="text-2xl font-extrabold text-white">
            Προγράμματα συνδρομών
        </h2>
        <p class="mt-2 text-sm text-zinc-500">
            Διαχείριση προγραμμάτων με ιστορικό τιμών.
            Οι συμφωνημένες τιμές των μαθητών
            δεν αλλάζουν αυτόματα.
        </p>
    </div>

    <?php if (is_string($planError) && $planError !== ''): ?>
        <div role="alert"
            class="rounded-xl border border-amber-500/20 bg-amber-500/10 p-4 text-sm text-amber-300">
            <?= e($planError) ?>
        </div>
    <?php endif; ?>

    <?php if (is_string($planSuccess) && $planSuccess !== ''): ?>
        <div role="status"
            class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-sm text-emerald-300">
            <?= e($planSuccess) ?>
        </div>
    <?php endif; ?>

    <!-- New membership plan -->
    <div class="rounded-2xl border border-white/[.08] bg-[#18181c] p-6">
        <h3 class="mb-5 text-lg font-bold text-white">
            Νέο πρόγραμμα
        </h3>

        <form action="/admin/membership-plans"
            method="POST"
            class="grid gap-4 sm:grid-cols-2">

            <?= \Framework\Csrf::field() ?>

            <div>
                <label for="plan-code"
                    class="mb-2 block text-sm text-zinc-300">
                    Κωδικός
                </label>
                <input id="plan-code"
                    name="code"
                    type="text"
                    maxlength="50"
                    required
                    placeholder="π.χ. MMA-BASIC"
                    class="w-full rounded-xl border border-white/10 bg-[#111114] px-4 py-3 text-sm text-white">
            </div>

            <div>
                <label for="plan-name"
                    class="mb-2 block text-sm text-zinc-300">
                    Όνομα προγράμματος
                </label>
                <input id="plan-name"
                    name="name"
                    type="text"
                    maxlength="150"
                    required
                    placeholder="Ονομασία συνδρομής"
                    class="w-full rounded-xl border border-white/10 bg-[#111114] px-4 py-3 text-sm text-white">
            </div>

            <div>
                <label for="plan-fee"
                    class="mb-2 block text-sm text-zinc-300">
                    Αρχική μηνιαία τιμή (€)
                </label>
                <input id="plan-fee"
                    name="default_monthly_fee"
                    type="text"
                    inputmode="decimal"
                    required
                    placeholder="π.χ. 45,00"
                    class="w-full rounded-xl border border-white/10 bg-[#111114] px-4 py-3 text-sm text-white">
            </div>

            <div class="flex items-end">
                <button type="submit"
                    class="w-full rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700">
                    Δημιουργία προγράμματος
                </button>
            </div>
        </form>
    </div>

    <!-- Edit selected plan -->
    <?php if (is_array($editPlan)): ?>

        <div id="plan-edit"
            class="space-y-5 rounded-2xl border border-red-500/30 bg-[#18181c] p-6">

            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-lg font-bold text-white">
                    Επεξεργασία: <?= e($editPlan['name']) ?>
                </h3>
                <a href="/admin/membership-plans"
                    class="text-sm text-zinc-400 hover:text-white">
                    Κλείσιμο
                </a>
            </div>

            <!-- Edit basic information -->
            <form action="/admin/membership-plans/update"
                method="POST"
                class="grid gap-4 sm:grid-cols-2">

                <?= \Framework\Csrf::field() ?>

                <input type="hidden"
                    name="plan_id"
                    value="<?= (int) $editPlan['id'] ?>">

                <div>
                    <label for="edit-code"
                        class="mb-2 block text-sm text-zinc-300">
                        Κωδικός
                    </label>
                    <input id="edit-code"
                        name="code"
                        maxlength="50"
                        required
                        value="<?= e($editPlan['code']) ?>"
                        class="w-full rounded-xl border border-white/10 bg-[#111114] px-4 py-3 text-sm text-white">
                </div>

                <div>
                    <label for="edit-name"
                        class="mb-2 block text-sm text-zinc-300">
                        Όνομα
                    </label>
                    <input id="edit-name"
                        name="name"
                        maxlength="150"
                        required
                        value="<?= e($editPlan['name']) ?>"
                        class="w-full rounded-xl border border-white/10 bg-[#111114] px-4 py-3 text-sm text-white">
                </div>

                <div>
                    <label for="edit-active"
                        class="mb-2 block text-sm text-zinc-300">
                        Κατάσταση
                    </label>

                    <select id="edit-active"
                        name="is_active"
                        class="w-full rounded-xl border border-white/10 bg-[#111114] px-4 py-3 text-sm text-white">
                        <option value="1"
                            <?= (int) $editPlan['is_active'] === 1 ? 'selected' : '' ?>>
                            Ενεργό
                        </option>

                        <option value="0"
                            <?= (int) $editPlan['is_active'] === 0 ? 'selected' : '' ?>>
                            Ανενεργό
                        </option>
                    </select>
                </div>

                <div class="flex items-end">
                    <button type="submit"
                        class="w-full rounded-xl border border-white/10 px-5 py-3 text-sm font-bold text-white hover:bg-white/5">
                        Αποθήκευση στοιχείων
                    </button>
                </div>
            </form>

            <!-- Record new price -->
            <div class="border-t border-white/10 pt-5">

                <h4 class="mb-2 font-bold text-white">
                    Αλλαγή μηνιαίας τιμής
                </h4>

                <p class="mb-4 text-sm text-zinc-400">
                    Τρέχουσα:
                    <?= e($formatFee($editPlan['default_monthly_fee'])) ?>.
                    Η νέα τιμή θα ισχύει αμέσως.
                    Η προηγούμενη παραμένει στο ιστορικό.
                </p>

                <form action="/admin/membership-plans/price"
                    method="POST"
                    class="flex flex-wrap items-end gap-3">

                    <?= \Framework\Csrf::field() ?>

                    <input type="hidden"
                        name="plan_id"
                        value="<?= (int) $editPlan['id'] ?>">

                    <div>
                        <label for="new-plan-price"
                            class="mb-2 block text-sm text-zinc-300">
                            Νέα τιμή (€ / μήνα)
                        </label>

                        <input id="new-plan-price"
                            name="new_monthly_fee"
                            type="text"
                            inputmode="decimal"
                            required
                            placeholder="π.χ. 50,00"
                            class="rounded-xl border border-white/10 bg-[#111114] px-4 py-3 text-sm text-white">
                    </div>

                    <button type="submit"
                        class="rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700">
                        Καταχώριση νέας τιμής
                    </button>
                </form>
            </div>

        </div>
    <?php endif; ?>

    <!-- Plans table -->
    <div class="overflow-hidden rounded-2xl border border-white/[.08] bg-[#18181c]">

        <div class="border-b border-white/10 p-5">
            <h3 class="text-lg font-bold text-white">
                Υπάρχοντα προγράμματα
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">

                <thead class="border-b border-white/10 text-xs text-zinc-500">
                    <tr>
                        <th class="px-5 py-4">Κωδικός</th>
                        <th class="px-5 py-4">Πρόγραμμα</th>
                        <th class="px-5 py-4">Τιμή / μήνα</th>
                        <th class="px-5 py-4">Κατάσταση</th>
                        <th class="px-5 py-4">Ενέργειες</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($plans as $plan): ?>
                        <?php
                        $isHistorical = (string) $plan['code'] === 'HISTORICAL';

                        $canDelete =
                            !$isHistorical
                            && (int) $plan['student_count'] === 0
                            && (int) $plan['program_count'] === 0
                            && (int) $plan['price_count'] <= 1;
                        ?>

                        <tr class="border-b border-white/5">

                            <td class="px-5 py-4 font-mono text-zinc-300">
                                <?= e($plan['code']) ?>
                            </td>

                            <td class="px-5 py-4 font-semibold text-white">
                                <?= e($plan['name']) ?>
                            </td>

                            <td class="px-5 py-4 text-zinc-300">
                                <?= e($formatFee($plan['default_monthly_fee'])) ?>
                            </td>

                            <td class="px-5 py-4 <?= (int) $plan['is_active'] === 1 ? 'text-emerald-400' : 'text-zinc-500' ?>">
                                <?= (int) $plan['is_active'] === 1
                                    ? 'Ενεργό'
                                    : 'Ανενεργό' ?>
                            </td>

                            <td class="px-5 py-4">

                                <div class="flex flex-wrap items-center gap-3">

                                    <?php if (!$isHistorical): ?>

                                        <a href="/admin/membership-plans?edit=<?= (int) $plan['id'] ?>#plan-edit"
                                            class="font-semibold text-white hover:text-red-400">
                                            Επεξεργασία
                                        </a>

                                    <?php else: ?>

                                        <span class="inline-flex items-center gap-2 text-zinc-400">
                                            <i data-lucide="lock" class="size-4"></i>
                                            Προστατευμένη
                                        </span>

                                    <?php endif; ?>

                                    <a href="/admin/membership-plans?history=<?= (int) $plan['id'] ?>#price-history"
                                        class="font-semibold text-red-400 hover:text-red-300">
                                        Ιστορικό
                                    </a>

                                    <?php if ($canDelete): ?>

                                        <form action="/admin/membership-plans/delete"
                                            method="POST"
                                            onsubmit="return confirm('Να διαγραφεί αυτό το αχρησιμοποίητο πρόγραμμα;');">

                                            <?= \Framework\Csrf::field() ?>

                                            <input type="hidden"
                                                name="plan_id"
                                                value="<?= (int) $plan['id'] ?>">

                                            <button type="submit"
                                                class="font-semibold text-zinc-400 hover:text-red-400">
                                                Διαγραφή
                                            </button>
                                        </form>

                                    <?php else: ?>

                                        <span title="Έχει συνδέσεις ή ιστορικό αλλαγών"
                                            class="text-zinc-600">
                                            <?= $isHistorical
                                                ? 'Δεν επιτρέπεται διαγραφή'
                                                : 'Διαγραφή μη διαθέσιμη' ?>
                                        </span>

                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    <?php if ($plans === []): ?>
                        <tr>
                            <td colspan="5"
                                class="px-5 py-10 text-center text-zinc-500">
                                Δεν έχουν δημιουργηθεί ακόμη προγράμματα.
                            </td>
                        </tr>
                    <?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>

    <!-- Price history -->
    <?php if (is_array($historyPlan)): ?>

        <div id="price-history"
            class="overflow-hidden rounded-2xl border border-white/[.08] bg-[#18181c]">

            <div class="border-b border-white/10 p-5">
                <h3 class="text-lg font-bold text-white">
                    Ιστορικό τιμών: <?= e($historyPlan['name']) ?>
                </h3>

                <p class="mt-2 text-xs text-zinc-500">
                    Οι ημερομηνίες εμφανίζονται σε ώρα Ελλάδας.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">

                    <thead class="border-b border-white/10 text-xs text-zinc-500">
                        <tr>
                            <th class="px-5 py-4">Ισχύει από</th>
                            <th class="px-5 py-4">Έως πριν από</th>
                            <th class="px-5 py-4">Ποσό</th>
                            <th class="px-5 py-4">Καταχώριση</th>
                            <th class="px-5 py-4">Διαχειριστής</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($priceHistory as $i => $price): ?>

                            <?php
                            $next = $i > 0
                                ? $priceHistory[$i - 1]
                                : null;

                            $kind = match ($price['change_type']) {
                                'migration' => 'Τιμή κατά τη μετάβαση',
                                'initial' => 'Αρχική τιμή',
                                default => 'Αλλαγή τιμής'
                            };
                            ?>

                            <tr class="border-b border-white/5">

                                <td class="px-5 py-4 text-zinc-300">
                                    <?= e($formatUtc($price['effective_at_utc'])) ?>
                                </td>

                                <td class="px-5 py-4 text-zinc-400">
                                    <?= $next !== null
                                        ? e($formatUtc($next['effective_at_utc']))
                                        : 'Σήμερα / σε ισχύ' ?>
                                </td>

                                <td class="px-5 py-4 font-semibold text-white">
                                    <?= e($formatFee($price['monthly_fee'])) ?>
                                </td>

                                <td class="px-5 py-4 text-zinc-400">
                                    <?= e($kind) ?>
                                </td>

                                <td class="px-5 py-4 text-zinc-400">

                                    <?php if ($price['changed_by_admin_id'] === null): ?>
                                        —
                                    <?php else: ?>

                                        #<?= (int) $price['changed_by_admin_id'] ?>

                                        <?php if ($price['admin_email'] !== null): ?>
                                            (<?= e($price['admin_email']) ?>)
                                        <?php endif; ?>

                                    <?php endif; ?>

                                </td>

                            </tr>
                        <?php endforeach; ?>

                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>

</section>