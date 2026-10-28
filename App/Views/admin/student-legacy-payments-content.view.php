<?php

/** @var array<string, mixed> $legacyPaymentData */

$legacyDate = static fn(?string $value): string =>
$value === null
    ? 'Άγνωστη ημερομηνία'
    : (new \DateTimeImmutable($value))->format('d/m/Y');

$legacyAmount = static fn(mixed $value): string =>
$value === null
    ? '—'
    : number_format((float) $value, 2, ',', '.');
?>

<section class="mt-6 overflow-hidden rounded-2xl border border-white/10 bg-[#18181c]">

    <div class="border-b border-white/10 p-5">

        <h3 class="font-bold text-white">
            Ιστορικό από την παλιά εφαρμογή
        </h3>

        <p class="mt-2 text-sm leading-6 text-zinc-400">
            Η ημερομηνία διατηρείται όπως καταγράφηκε στην παλιά εφαρμογή.
            Δεν έχει επιβεβαιωθεί ότι είναι η ημέρα είσπραξης.
            Το παλιό υπόλοιπο δεν αποτελεί σημερινή οφειλή.
        </p>

    </div>

    <<?php if (
            $legacyPaymentData['state'] !== 'linked' &&
            $legacyPaymentData['rows'] === []
        ): ?>

        <p class="p-5 text-sm text-amber-300">
        Δεν υπάρχει μοναδική προτεινόμενη σύνδεση παλιού προφίλ
        για αυτή την προβολή. Χρειάζεται έλεγχος αντιστοίχισης.
        Αυτό δεν σημαίνει ότι ο μαθητής δεν είχε πληρώσει.
        </p>

    <?php else: ?>


        <p class="p-5 text-xs text-zinc-400">
            Ιστορικό από την παλιά εφαρμογή.

            Εγγραφές:
            <strong class="text-white">
                <?= count($legacyPaymentData['rows']) ?>
            </strong>.

            Περιλαμβάνει τις αρχικές και τις συμπληρωματικές
            ιστορικές εγγραφές, χωρίς διπλή καταχώριση.

            <?php if (!empty($legacyPaymentData['snapshot'])): ?>
                <span class="mt-1 block break-all">
                    Αρχική εξαγωγή:
                    <?= e($legacyPaymentData['snapshot']) ?>
                </span>
            <?php endif; ?>
        </p>


        <div class="overflow-x-auto">

            <table class="w-full text-left text-sm">

                <thead class="border-b border-white/10 text-xs text-zinc-400">
                    <tr>
                        <th class="p-4">
                            Ημερομηνία παλιάς εγγραφής
                        </th>

                        <th class="p-4">
                            Χρέωση πηγής
                        </th>

                        <th class="p-4">
                            Πληρωμή πηγής
                        </th>

                        <th class="p-4">
                            Υπόλοιπο πηγής
                        </th>

                        <th class="p-4">
                            Έλεγχος
                        </th>
                    </tr>
                </thead>

                <tbody class="text-zinc-300">

                    <?php foreach ($legacyPaymentData['rows'] as $legacyRow): ?>

                        <tr class="border-b border-white/5">

                            <td class="p-4">

                                <?= e($legacyDate(
                                    $legacyRow['source_record_date']
                                )) ?>

                                <?php if ($legacyRow['raw_date'] !== ''): ?>

                                    <p class="mt-1 text-xs text-zinc-400">
                                        Αρχικό κείμενο:

                                        <?= e($legacyRow['raw_date']) ?>
                                    </p>

                                <?php endif; ?>

                            </td>

                            <td class="p-4">
                                <?= e($legacyAmount(
                                    $legacyRow['source_charged_amount']
                                )) ?>
                            </td>

                            <td class="p-4">
                                <?= e($legacyAmount(
                                    $legacyRow['source_paid_amount']
                                )) ?>
                            </td>

                            <td class="p-4">
                                <?= e($legacyAmount(
                                    $legacyRow['source_balance_amount']
                                )) ?>
                            </td>

                            <td class="p-4">

                                <?php if ($legacyRow['needs_review']): ?>

                                    <span class="text-amber-300">
                                        Προς έλεγχο
                                    </span>

                                    <?php if (!empty($legacyRow['review_flags'])): ?>

                                        <details class="mt-2 text-xs">

                                            <summary class="cursor-pointer">
                                                Λεπτομέρειες
                                            </summary>

                                            <p class="mt-2 break-all">
                                                <?= e($legacyRow['review_flags']) ?>
                                            </p>

                                        </details>

                                    <?php endif; ?>

                                <?php else: ?>

                                    <span class="text-zinc-400">
                                        Ιστορική εγγραφή
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <?php if ($legacyPaymentData['rows'] === []): ?>

                        <tr>
                            <td colspan="5" class="p-5 text-zinc-400">
                                Το συνδεδεμένο παλιό προφίλ δεν είχε γραμμές ιστορικού
                                στη συγκεκριμένη εξαγωγή.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>