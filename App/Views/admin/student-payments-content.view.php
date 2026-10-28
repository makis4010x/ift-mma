<?php

/**
 * @var array<string, mixed> $student
 * @var array<string, mixed> $paymentData
 * @var array<string, mixed> $legacyPaymentData
 */

$paymentError = \Framework\Session::getFlash(
    'student_payment_error'
);

$paymentSuccess = \Framework\Session::getFlash(
    'student_payment_success'
);

$paymentOld = \Framework\Session::getFlash(
    'payment_old_' . (int) $student['id'],
    []
);

$paymentOld = is_array($paymentOld) ? $paymentOld : [];

$paymentToday = (
    new \DateTimeImmutable(
        'today',
        new \DateTimeZone('Europe/Athens')
    )
)->format('Y-m-d');

$paymentDate = static fn(?string $value): string =>
$value === null
    ? '—'
    : (new \DateTimeImmutable($value))->format('d/m/Y');

$paymentLastDay = static fn(string $end): string => (new \DateTimeImmutable($end))
    ->modify('-1 day')
    ->format('d/m/Y');

$paymentMoney = static fn(mixed $amount): string =>
number_format((float) $amount, 2, ',', '.');

$paymentMethods = [
    'cash' => 'Μετρητά',
    'card' => 'Κάρτα',
    'bank_transfer' => 'Τραπεζική μεταφορά'
];

$paymentInputClass =
    'w-full rounded-xl border border-white/10 '
    . 'bg-[#111114] px-4 py-3 text-sm text-white';

$selectedAgreement = (string) (
    $paymentOld['membership_id'] ?? ''
);

if ($selectedAgreement === '' && $paymentOld === []) {
    foreach ($paymentData['agreements'] as $agreement) {
        if (
            $agreement['valid_from'] <= $paymentToday &&
            (
                $agreement['valid_to'] === null ||
                $paymentToday < $agreement['valid_to']
            )
        ) {
            $selectedAgreement = (string) $agreement['id'];
            break;
        }
    }
}

$requestKey = (string) ($paymentOld['request_key'] ?? '');

if (preg_match('/\A[a-f0-9]{32}\z/D', $requestKey) !== 1) {
    $requestKey = bin2hex(random_bytes(16));
}
?>

<div class="space-y-6">

    <section class="rounded-2xl border border-white/10 bg-[#18181c] p-6">

        <h3 class="text-lg font-bold text-white">
            Πληρωμές και κάλυψη
        </h3>

        <p class="mt-2 text-sm text-zinc-400">
            Επιλέγεις συμφωνία, μήνες και ποσό.
            Δεν δημιουργούνται αυτόματες οφειλές.
        </p>

        <!-- Coverage summary -->
        <div class="mt-5 grid gap-4 sm:grid-cols-2">

            <div class="rounded-xl bg-[#111114] p-4">
                <p class="text-xs text-zinc-400">
                    Κάλυψη έως και
                </p>

                <p class="mt-2 text-lg font-bold text-white">
                    <?= $paymentData['covered_until'] !== null
                        ? e($paymentLastDay(
                            $paymentData['covered_until']
                        ))
                        : 'Χωρίς επιβεβαιωμένη κάλυψη σήμερα'
                    ?>
                </p>

                <p class="mt-2 text-xs text-zinc-400">
                    <?php if ($paymentData['covered_until'] !== null): ?>

                        Επόμενη ανανέωση:
                        <?= e($paymentDate(
                            $paymentData['covered_until']
                        )) ?>

                    <?php elseif ($paymentData['future_from'] !== null): ?>

                        Μελλοντική περίοδος από
                        <?= e($paymentDate(
                            $paymentData['future_from']
                        )) ?>.

                    <?php elseif ($paymentData['last_end'] !== null): ?>

                        Η τελευταία κάλυψη έληξε πριν από
                        <?= e($paymentDate(
                            $paymentData['last_end']
                        )) ?>.

                    <?php else: ?>

                        Δεν υπάρχει εξοφλημένη περίοδος κάλυψης.

                    <?php endif; ?>
                </p>
            </div>

            <div class="rounded-xl bg-[#111114] p-4">
                <p class="text-xs text-zinc-400">
                    Τελευταία έγκυρη πληρωμή
                </p>

                <p class="mt-2 text-lg font-bold text-white">
                    <?= e($paymentDate(
                        $paymentData['last_paid_on']
                    )) ?>
                </p>

                <p class="mt-2 text-xs text-zinc-400">
                    Δεν περιλαμβάνει ακυρωμένες πληρωμές.
                </p>
            </div>

        </div>

        <!-- Messages -->
        <?php foreach ([$paymentError, $paymentSuccess] as $paymentMessage): ?>
            <?php if (
                is_string($paymentMessage) &&
                $paymentMessage !== ''
            ): ?>
                <p
                    role="status"
                    class="mt-4 rounded-xl border border-white/10
                           bg-[#111114] p-4 text-sm text-white">
                    <?= e($paymentMessage) ?>
                </p>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($paymentData['review']): ?>

            <p
                role="alert"
                class="mt-5 rounded-xl bg-amber-500/10
                       p-4 text-sm text-amber-300">
                Υπάρχει παλιά μερική πληρωμή ή ασυμφωνία κάλυψης.
                Χρειάζεται έλεγχος πριν από νέα είσπραξη.
                Δεν διαγράφουμε χρήματα ή ιστορικό για να
                παρακάμψουμε τον έλεγχο.
            </p>

        <?php elseif ($paymentData['agreements'] === []): ?>

            <p class="mt-5 text-sm text-amber-300">
                Ανάθεσε πρώτα συνδρομή στον μαθητή
                με θετικό μηνιαίο ποσό σε EUR.
            </p>

        <?php else: ?>

            <!-- Simple payment form -->
            <form
                id="coverage-payment-form"
                method="POST"
                action="/admin/students/<?= (int) $student['id'] ?>/payments"
                class="mt-6 grid gap-4 sm:grid-cols-2">

                <?= \Framework\Csrf::field() ?>

                <input
                    type="hidden"
                    name="request_key"
                    value="<?= e($requestKey) ?>">

                <div class="sm:col-span-2">

                    <label
                        for="coverage-agreement"
                        class="mb-2 block text-sm text-zinc-300">
                        Συμφωνημένη συνδρομή μαθητή
                    </label>

                    <select
                        id="coverage-agreement"
                        name="membership_id"
                        required
                        class="<?= e($paymentInputClass) ?>">
                        <option value="">
                            Επίλεξε συμφωνία
                        </option>

                        <?php foreach (
                            $paymentData['agreements'] as $agreement
                        ): ?>
                            <option
                                value="<?= (int) $agreement['id'] ?>"
                                data-fee="<?= e(
                                                $agreement['agreed_monthly_fee']
                                            ) ?>"
                                <?= $selectedAgreement ===
                                    (string) $agreement['id']
                                    ? 'selected'
                                    : '' ?>>
                                <?= e($agreement['plan_name']) ?>
                                —
                                <?= e($paymentMoney(
                                    $agreement['agreed_monthly_fee']
                                )) ?> €/μήνα

                                · από
                                <?= e($paymentDate(
                                    $agreement['valid_from']
                                )) ?>

                                <?= $agreement['valid_to'] !== null
                                    ? ' έως πριν από '
                                    . e($paymentDate(
                                        $agreement['valid_to']
                                    ))
                                    : ''
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                </div>

                <div>
                    <label
                        for="coverage-months"
                        class="mb-2 block text-sm text-zinc-300">
                        Μήνες κάλυψης
                    </label>

                    <select
                        id="coverage-months"
                        name="months"
                        required
                        class="<?= e($paymentInputClass) ?>">
                        <?php for ($month = 1; $month <= 12; $month++): ?>
                            <option
                                value="<?= $month ?>"
                                <?= (int) (
                                    $paymentOld['months'] ?? 1
                                ) === $month
                                    ? 'selected'
                                    : '' ?>>
                                <?= $month ?>
                                <?= $month === 1 ? 'μήνας' : 'μήνες' ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div>
                    <label
                        for="coverage-amount"
                        class="mb-2 block text-sm text-zinc-300">
                        Ποσό πληρωμής (€)
                    </label>

                    <input
                        id="coverage-amount"
                        name="amount"
                        type="text"
                        inputmode="decimal"
                        maxlength="11"
                        required
                        value="<?= e($paymentOld['amount'] ?? '') ?>"
                        class="<?= e($paymentInputClass) ?>">
                </div>

                <div>
                    <label
                        for="coverage-paid-on"
                        class="mb-2 block text-sm text-zinc-300">
                        Ημερομηνία πληρωμής
                    </label>

                    <input
                        id="coverage-paid-on"
                        name="paid_on"
                        type="date"
                        required
                        max="<?= e($paymentToday) ?>"
                        value="<?= e(
                                    $paymentOld['paid_on'] ?? $paymentToday
                                ) ?>"
                        class="<?= e($paymentInputClass) ?>">
                </div>

                <div>
                    <label
                        for="coverage-method"
                        class="mb-2 block text-sm text-zinc-300">
                        Τρόπος πληρωμής
                    </label>

                    <select
                        id="coverage-method"
                        name="method"
                        class="<?= e($paymentInputClass) ?>">
                        <?php foreach (
                            $paymentMethods as $key => $label
                        ): ?>
                            <option
                                value="<?= e($key) ?>"
                                <?= (
                                    $paymentOld['method'] ?? 'cash'
                                ) === $key
                                    ? 'selected'
                                    : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <label
                    class="flex items-center gap-3
                           text-sm text-zinc-300 sm:col-span-2">
                    <input
                        id="coverage-special"
                        name="special_amount"
                        type="checkbox"
                        value="1"
                        <?= (
                            $paymentOld['special_amount'] ?? ''
                        ) === '1'
                            ? 'checked'
                            : '' ?>>

                    Ειδικό ποσό για τους επιλεγμένους μήνες
                    — όχι μερική πληρωμή
                </label>

                <div class="sm:col-span-2">
                    <label
                        for="coverage-notes"
                        class="mb-2 block text-sm text-zinc-300">
                        Σημείωση / αιτιολογία ειδικού ποσού
                    </label>

                    <textarea
                        id="coverage-notes"
                        name="notes"
                        rows="2"
                        maxlength="255"
                        class="<?= e($paymentInputClass) ?>"><?= e($paymentOld['notes'] ?? '') ?></textarea>
                </div>

                <div class="sm:col-span-2">

                    <p
                        id="coverage-form-hint"
                        role="status"
                        aria-live="polite"
                        class="mb-4 text-sm text-zinc-300">
                        Το ποσό πρέπει να αντιστοιχεί στους μήνες
                        ή να δηλωθεί ως ειδικό ποσό.
                    </p>

                    <button
                        type="submit"
                        class="rounded-xl bg-red-600 px-5 py-3
                               text-sm font-bold text-white
                               hover:bg-red-700">
                        Καταχώριση πληρωμής
                    </button>

                    <p class="mt-3 text-xs text-zinc-400">
                        Η ακριβής περίοδος υπολογίζεται στον server
                        από την κάλυψη και τις παρουσίες,
                        και εμφανίζεται στο ιστορικό
                        μετά την αποθήκευση.
                    </p>

                </div>

            </form>

        <?php endif; ?>

    </section>

    <!-- Payment history -->
    <section
        class="overflow-hidden rounded-2xl border
               border-white/10 bg-[#18181c]">

        <h3
            class="border-b border-white/10 p-5
                   font-bold text-white">
            Ιστορικό πληρωμών και κάλυψης
        </h3>

        <div class="overflow-x-auto">

            <table class="w-full text-left text-sm">

                <thead class="text-zinc-400">
                    <tr>
                        <th class="p-4">Πληρωμή</th>
                        <th class="p-4">Ποσό</th>
                        <th class="p-4">Συμφωνία / κάλυψη</th>
                        <th class="p-4">Κατάσταση</th>
                        <th class="p-4">Ενέργεια</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach (
                        $paymentData['history'] as $payment
                    ): ?>

                        <tr class="border-t border-white/5 text-zinc-300">

                            <td class="p-4">
                                <?= e($paymentDate(
                                    $payment['paid_on']
                                )) ?>

                                <p class="mt-1 text-xs text-zinc-400">
                                    #<?= (int) $payment['id'] ?>
                                    ·
                                    <?= e(
                                        $paymentMethods[$payment['method']]
                                            ?? $payment['method']
                                    ) ?>
                                </p>
                            </td>

                            <td class="p-4">
                                <?= e($paymentMoney(
                                    $payment['amount']
                                )) ?>
                                <?= e($payment['currency']) ?>
                            </td>

                            <td class="p-4">

                                <?php if ($payment['coverage_id'] !== null): ?>

                                    <p>
                                        <?= e($payment['plan_name']) ?>
                                        ·
                                        <?= (int) $payment['months_count'] ?>
                                        μήνες
                                    </p>

                                    <p class="mt-1">
                                        <?= e($paymentDate(
                                            $payment['coverage_start']
                                        )) ?>
                                        –
                                        <?= e($paymentLastDay(
                                            $payment['coverage_end']
                                        )) ?>
                                    </p>

                                    <?php if ($payment['status'] === 'void'): ?>
                                        <p class="mt-1 text-xs text-amber-300">
                                            Η κάλυψη ακυρώθηκε
                                            μαζί με την πληρωμή.
                                        </p>
                                    <?php endif; ?>

                                <?php else: ?>

                                    <p>
                                        Παλαιά καταχώριση —
                                        λεπτομέρειες στο αρχείο παρακάτω.
                                    </p>

                                <?php endif; ?>

                                <?php if (
                                    !empty($payment['notes']) ||
                                    !empty($payment['automatic_note'])
                                ): ?>
                                    <details class="mt-2 max-w-md text-xs">
                                        <summary class="cursor-pointer text-zinc-400">
                                            Σχόλια
                                        </summary>

                                        <p class="mt-2 whitespace-pre-wrap">
                                            <?= e($payment['notes']) ?>
                                        </p>

                                        <p class="mt-2">
                                            <?= e($payment['automatic_note']) ?>
                                        </p>
                                    </details>
                                <?php endif; ?>

                            </td>

                            <td class="p-4">

                                <?php if ($payment['status'] === 'posted'): ?>

                                    <span class="text-emerald-400">
                                        Καταχωρισμένη
                                    </span>

                                <?php else: ?>

                                    <span class="text-amber-300">
                                        Ακυρωμένη
                                    </span>

                                    <p class="mt-2 max-w-xs text-xs">
                                        <?= e($payment['void_reason']) ?>
                                    </p>

                                    <p class="mt-1 text-xs">
                                        Admin
                                        #<?= (int) $payment['voided_by_admin_id'] ?>
                                    </p>

                                    <?php if ($payment['voided_at_utc'] !== null): ?>
                                        <p class="mt-1 text-xs">
                                            <?= e(
                                                (new \DateTimeImmutable(
                                                    $payment['voided_at_utc'],
                                                    new \DateTimeZone('UTC')
                                                ))->setTimezone(
                                                    new \DateTimeZone('Europe/Athens')
                                                )->format('d/m/Y H:i')
                                            ) ?>
                                        </p>
                                    <?php endif; ?>

                                <?php endif; ?>

                            </td>

                            <td class="p-4">

                                <?php if ($payment['status'] === 'posted'): ?>

                                    <details>

                                        <summary class="cursor-pointer text-red-400">
                                            Ακύρωση
                                        </summary>

                                        <form
                                            method="POST"
                                            action="/admin/students/<?= (int) $student['id'] ?>/payments/void"
                                            class="mt-3 min-w-52 space-y-3"
                                            onsubmit="return confirm('Ακύρωση πληρωμής και της αντίστοιχης κάλυψης;');">

                                            <?= \Framework\Csrf::field() ?>

                                            <input
                                                type="hidden"
                                                name="payment_id"
                                                value="<?= (int) $payment['id'] ?>">

                                            <label
                                                for="payment-void-<?= (int) $payment['id'] ?>"
                                                class="block text-xs">
                                                Αιτιολογία ακύρωσης
                                            </label>

                                            <textarea
                                                id="payment-void-<?= (int) $payment['id'] ?>"
                                                name="void_reason"
                                                required
                                                minlength="5"
                                                maxlength="255"
                                                rows="2"
                                                class="<?= e($paymentInputClass) ?>"></textarea>

                                            <button
                                                type="submit"
                                                class="rounded-lg border
                                                       border-red-500/30
                                                       px-3 py-2 text-red-300">
                                                Επιβεβαίωση
                                            </button>

                                        </form>

                                    </details>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <?php if ($paymentData['history'] === []): ?>
                        <tr>
                            <td colspan="5" class="p-5 text-zinc-400">
                                Δεν υπάρχουν πληρωμές.
                            </td>
                        </tr>
                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <!-- Old records stay available for review, not as new debt. -->
    <?php if ($paymentData['legacy'] !== []): ?>

        <details
            class="rounded-2xl border border-white/10
                   bg-[#18181c] p-5">

            <summary class="cursor-pointer text-sm text-zinc-400">
                Περίοδοι παλαιού μηχανισμού — αρχείο ελέγχου
            </summary>

            <p class="mt-3 text-xs text-zinc-400">
                Διατηρούνται χωρίς αυτόματη μετατροπή σε χρέος.
                Μόνο πλήρως εξοφλημένες περίοδοι με έγκυρες
                πληρωμές συνυπολογίζονται στην κάλυψη.
            </p>

            <div class="mt-4 overflow-x-auto">

                <table class="w-full text-left text-sm text-zinc-300">

                    <thead>
                        <tr>
                            <th class="p-3">Περίοδος</th>
                            <th class="p-3">Αρχικό ποσό</th>
                            <th class="p-3">Έγκυρες πληρωμές</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($paymentData['legacy'] as $row): ?>
                            <tr class="border-t border-white/5">

                                <td class="p-3">
                                    <?= e($paymentDate(
                                        $row['period_start']
                                    )) ?>
                                    –
                                    <?= e($paymentLastDay(
                                        $row['period_end']
                                    )) ?>
                                </td>

                                <td class="p-3">
                                    <?= e($paymentMoney($row['amount'])) ?>
                                    <?= e($row['currency']) ?>
                                </td>

                                <td class="p-3">
                                    <?= e($paymentMoney($row['paid_amount'])) ?>
                                    <?= e($row['currency']) ?>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>

                </table>

            </div>

        </details>

    <?php endif; ?>

</div>

<script
    type="module"
    src="/src/student-payment-form.js?v=1"></script>

<?php
loadView('admin/student-legacy-payments-content', [
    'legacyPaymentData' => $legacyPaymentData
]);
?>