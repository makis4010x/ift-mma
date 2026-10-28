<?php

/** @var array<string, mixed> $dashboardStats */

$k = $dashboardStats;

$cards = [
    [
        'label' => 'Σύνολο μαθητών',
        'value' => number_format(
            $k['total_students'],
            0,
            ',',
            '.'
        ),
        'detail' => 'Εγγραφές μαθητών',
        'icon' => 'users-round',
        'iconClass' => 'bg-red-500/10 text-red-400',
    ],
    [
        'label' => 'Ενεργοί μαθητές',
        'value' => number_format(
            $k['active_students'],
            0,
            ',',
            '.'
        ),
        'detail' => 'Βάσει κανόνα δραστηριότητας',
        'icon' => 'user-check',
        'iconClass' => 'bg-emerald-500/10 text-emerald-400',
    ],
    [
        'label' => 'Ανενεργοί μαθητές',
        'value' => number_format(
            $k['inactive_students'],
            0,
            ',',
            '.'
        ),
        'detail' => 'Βάσει κανόνα δραστηριότητας',
        'icon' => 'user-x',
        'iconClass' => 'bg-amber-500/10 text-amber-400',
    ],
    [
        'label' => 'Προς έλεγχο',
        'value' => number_format(
            $k['review_students'],
            0,
            ',',
            '.'
        ),
        'detail' => 'Χρειάζονται έλεγχο ιστορικού',
        'icon' => 'shield-alert',
        'iconClass' => 'bg-orange-500/10 text-orange-400',
    ],
    [
        'label' => 'Χωρίς ιστορικό πληρωμής',
        'value' => number_format(
            $k['no_payment_students'],
            0,
            ',',
            '.'
        ),
        'detail' => 'Χωρίς κατάλληλη ημερομηνία πληρωμής',
        'icon' => 'circle-help',
        'iconClass' => 'bg-zinc-500/10 text-zinc-400',
    ],
    [
        'label' => 'Σημερινές παρουσίες',
        'value' => number_format(
            $k['today_attendances'],
            0,
            ',',
            '.'
        ),
        'detail' => 'Ημερομηνία Ελλάδας: ' . $k['as_of'],
        'icon' => 'scan-barcode',
        'iconClass' => 'bg-violet-500/10 text-violet-400',
    ],
    [
        'label' => 'Παρουσίες μήνα',
        'value' => number_format(
            $k['month_attendances'],
            0,
            ',',
            '.'
        ),
        'detail' => 'Μαζί με τις εισαγόμενες ιστορικές παρουσίες',
        'icon' => 'calendar-check',
        'iconClass' => 'bg-sky-500/10 text-sky-400',
    ],
    [
        'label' => 'Εισπράξεις μήνα',
        'value' => number_format(
            (float) $k['month_revenue_eur'],
            2,
            ',',
            '.'
        ) . ' €',
        'detail' => $k['month_posted_payments']
            . ' καταχωρισμένες πληρωμές · '
            . $k['month'],
        'icon' => 'wallet',
        'iconClass' => 'bg-teal-500/10 text-teal-400',
    ],
];

?>

<section
    aria-label="Στατιστικά διαχείρισης"
    class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php foreach ($cards as $card): ?>

        <div
            class="rounded-2xl border border-white/[.08]
                   bg-[#18181c] p-5">
            <div
                class="mb-5 flex items-center
                       justify-between gap-3">
                <span class="text-xs font-bold text-zinc-400">
                    <?= e($card['label']) ?>
                </span>

                <span
                    class="rounded-xl p-2.5
                           <?= e($card['iconClass']) ?>">
                    <i
                        data-lucide="<?= e($card['icon']) ?>"
                        class="size-5"></i>
                </span>
            </div>

            <div class="text-2xl font-extrabold">
                <?= e($card['value']) ?>
            </div>

            <p class="mt-2 text-xs text-zinc-500">
                <?= e($card['detail']) ?>
            </p>
        </div>

    <?php endforeach; ?>
</section>