<?php

/**
 * Student attendance and membership history.
 *
 * @var array<string, mixed> $student
 * @var array<int, array<string, mixed>> $attendances
 * @var int $attendanceTotal
 * @var int $attendancePage
 * @var int $attendanceTotalPages
 * @var array<int, array<string, mixed>> $memberships
 * @var array<string, mixed>|false $legacyBilling
 */

$athensTimezone = new \DateTimeZone('Europe/Athens');
$todayAthens = (new \DateTimeImmutable('now', $athensTimezone))
    ->format('Y-m-d');

$sourceLabels = [
    'import' => 'Εισαγωγή ιστορικού',
    'scanner' => 'Scanner',
    'manual' => 'Χειροκίνητη',
];
?>

<!-- Attendance history -->
<div class="overflow-hidden rounded-2xl border border-white/[.08] bg-[#18181c]">

    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 p-5">
        <div>
            <h3 class="text-lg font-bold text-white">
                Ιστορικό παρουσιών
            </h3>
            <p class="mt-1 text-xs text-zinc-500">
                Οι παρουσίες του συγκεκριμένου μαθητή.
            </p>
        </div>

        <span class="rounded-lg bg-white/5 px-3 py-2 text-sm font-semibold text-zinc-300">
            <?= number_format($attendanceTotal, 0, ',', '.') ?>
            παρουσίες
        </span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-white/10 text-xs text-zinc-500">
                <tr>
                    <th class="px-5 py-4">Ημερομηνία</th>
                    <th class="px-5 py-4">Ώρα</th>
                    <th class="px-5 py-4">Πηγή</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($attendances as $attendance): ?>
                    <?php
                    $localDate = (new \DateTimeImmutable(
                        (string) $attendance['attended_at_utc'],
                        new \DateTimeZone('UTC')
                    ))->setTimezone($athensTimezone);
                    ?>

                    <tr class="border-b border-white/5">
                        <td class="px-5 py-4 text-zinc-200">
                            <?= e($localDate->format('d/m/Y')) ?>
                        </td>

                        <td class="px-5 py-4 text-zinc-300">
                            <?= e($localDate->format('H:i')) ?>
                        </td>

                        <td class="px-5 py-4 text-zinc-400">
                            <?= e(
                                $sourceLabels[$attendance['source']]
                                    ?? $attendance['source']
                            ) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($attendances === []): ?>
                    <tr>
                        <td colspan="3" class="px-5 py-10 text-center text-zinc-500">
                            Δεν υπάρχουν καταχωρισμένες παρουσίες.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($attendanceTotalPages > 1): ?>
        <div class="flex items-center justify-between gap-3 border-t border-white/10 p-5">

            <span class="text-xs text-zinc-500">
                Σελίδα <?= $attendancePage ?>
                από <?= $attendanceTotalPages ?>
            </span>

            <div class="flex gap-2">
                <?php if ($attendancePage > 1): ?>
                    <a
                        href="?attendance_page=<?= $attendancePage - 1 ?>"
                        class="rounded-lg border border-white/10 px-3 py-2 text-xs text-zinc-300 hover:bg-white/5">
                        Προηγούμενη
                    </a>
                <?php endif; ?>

                <?php if ($attendancePage < $attendanceTotalPages): ?>
                    <a
                        href="?attendance_page=<?= $attendancePage + 1 ?>"
                        class="rounded-lg border border-white/10 px-3 py-2 text-xs text-zinc-300 hover:bg-white/5">
                        Επόμενη
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- Membership history -->
<div class="rounded-2xl border border-white/[.08] bg-[#18181c] p-6">

    <h3 class="text-lg font-bold text-white">
        Ιστορικό συνδρομών
    </h3>

    <p class="mt-2 mb-5 text-sm text-zinc-500">
        Καταχωρισμένες περίοδοι συνδρομής και συμφωνημένα ποσά.
    </p>

    <?php if ($memberships === []): ?>
        <p class="rounded-xl border border-white/5 bg-[#111114] p-5 text-sm text-zinc-400">
            Δεν υπάρχουν ακόμη καταχωρισμένες συνδρομές
            για τον μαθητή.
        </p>
    <?php endif; ?>

    <div class="space-y-3">
        <?php foreach ($memberships as $membership): ?>
            <?php
            $from = (string) $membership['valid_from'];
            $to = $membership['valid_to'] !== null
                ? (string) $membership['valid_to']
                : null;

            $isActive = $from <= $todayAthens &&
                ($to === null || $to > $todayAthens);

            $isFuture = $from > $todayAthens;

            $statusLabel = $isActive
                ? 'Ενεργή'
                : ($isFuture ? 'Μελλοντική' : 'Ολοκληρωμένη');

            $fee = $membership['agreed_monthly_fee'];
            ?>

            <div class="rounded-xl border border-white/5 bg-[#111114] p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h4 class="font-bold text-white">
                        <?= e($membership['plan_name'] ?? 'Πρόγραμμα συνδρομής') ?>
                    </h4>

                    <span class="text-xs font-semibold <?= $isActive
                                                            ? 'text-emerald-400'
                                                            : 'text-zinc-400' ?>">
                        <?= e($statusLabel) ?>
                    </span>
                </div>

                <div class="mt-4 grid gap-4 text-sm sm:grid-cols-3">

                    <div>
                        <p class="text-xs text-zinc-500">Έναρξη</p>
                        <p class="mt-1 text-zinc-200">
                            <?= e(date('d/m/Y', strtotime($from))) ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-zinc-500">Λήξη (μη συμπεριλαμβανόμενη)</p>
                        <p class="mt-1 text-zinc-200">
                            <?= $to !== null
                                ? e(date('d/m/Y', strtotime($to)))
                                : 'Χωρίς ορισμένη λήξη' ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-zinc-500">Μηνιαίο ποσό</p>
                        <p class="mt-1 text-zinc-200">
                            <?= $fee !== null
                                ? e(number_format(
                                    (float) $fee,
                                    2,
                                    ',',
                                    '.'
                                )) . ' ' . e($membership['currency'])
                                : '—' ?>
                        </p>
                    </div>

                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- Imported legacy billing reference -->
<?php if (is_array($legacyBilling)): ?>
    <div class="rounded-2xl border border-white/[.08] bg-[#18181c] p-6">

        <h3 class="text-lg font-bold text-white">
            Ιστορικά στοιχεία χρέωσης
        </h3>

        <p class="mt-2 text-sm text-zinc-500">
            Στοιχεία από την αρχική εισαγωγή δεδομένων.
            Δεν αποτελούν απόδειξη πληρωμής ή ενεργής συνδρομής.
        </p>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">

            <div class="rounded-xl bg-[#111114] p-4">
                <p class="text-xs text-zinc-500">Καταγεγραμμένο μηνιαίο ποσό</p>
                <p class="mt-2 font-semibold text-white">
                    <?= $legacyBilling['recorded_monthly_fee'] !== null
                        ? e(number_format(
                            (float) $legacyBilling['recorded_monthly_fee'],
                            2,
                            ',',
                            '.'
                        )) . ' ' . e($legacyBilling['currency'])
                        : '—' ?>
                </p>
            </div>

            <div class="rounded-xl bg-[#111114] p-4">
                <p class="text-xs text-zinc-500">Καταγεγραμμένη ημέρα χρέωσης</p>
                <p class="mt-2 font-semibold text-white">
                    <?= e($legacyBilling['recorded_billing_day'] ?? '—') ?>
                </p>
            </div>

        </div>
    </div>
<?php endif; ?>