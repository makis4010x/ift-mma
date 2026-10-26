<?php

/**
 * Attendance management list.
 *
 * @var array<int, array<string, mixed>> $attendances
 * @var int $totalAttendances
 * @var int $totalResults
 * @var string $search
 * @var string $dateFilter
 * @var int $page
 * @var int $totalPages
 * @var int $todayAttendances
 */

$athens = new \DateTimeZone('Europe/Athens');
$utc = new \DateTimeZone('UTC');

$sourceLabels = [
    'import' => 'Ιστορικό',
    'scanner' => 'Scanner',
    'manual' => 'Χειροκίνητη'
];
?>

<section class="space-y-6">

    <!-- Heading -->
    <div>
        <h2 class="text-2xl font-extrabold text-white">
            Διαχείριση Παρουσιών
        </h2>

        <p class="mt-2 text-sm text-zinc-500">
            Ιστορικό παρουσιών και αναζήτηση μαθητών.
        </p>
    </div>
    <a
        href="/admin/scanner"
        class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-red-700">
        <i data-lucide="scan-barcode" class="size-4"></i>
        Άνοιγμα Scanner
    </a>
    <!-- Summary -->
    <div class="grid gap-4 sm:grid-cols-3">

        <div class="rounded-2xl border border-white/[.08] bg-[#18181c] p-5">
            <p class="text-xs font-semibold text-zinc-500">
                Συνολικές παρουσίες
            </p>

            <p class="mt-2 text-3xl font-extrabold text-white">
                <?= number_format($totalAttendances, 0, ',', '.') ?>
            </p>
        </div>


        <div class="rounded-2xl border border-white/[.08] bg-[#18181c] p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-zinc-500">
                    Σημερινές παρουσίες
                </p>

                <i
                    data-lucide="calendar-check-2"
                    class="size-5 text-emerald-400"></i>
            </div>

            <p class="mt-2 text-3xl font-extrabold text-white">
                <?= number_format(
                    (int) $todayAttendances,
                    0,
                    ',',
                    '.'
                ) ?>
            </p>

            <p class="mt-2 text-xs text-zinc-500">
                Σήμερα · Ώρα Ελλάδας
            </p>
        </div>


        <div class="rounded-2xl border border-white/[.08] bg-[#18181c] p-5">
            <p class="text-xs font-semibold text-zinc-500">
                Αποτελέσματα αναζήτησης
            </p>

            <p class="mt-2 text-3xl font-extrabold text-white">
                <?= number_format($totalResults, 0, ',', '.') ?>
            </p>
        </div>

    </div>

    <!-- Filters -->
    <div class="rounded-2xl border border-white/[.08] bg-[#18181c] p-5">

        <form
            action="/admin/attendance"
            method="GET"
            class="grid items-end gap-4 md:grid-cols-[minmax(0,1fr)_auto_auto_auto]">
            <div>
                <label
                    for="attendance-search"
                    class="mb-2 block text-xs text-zinc-400">
                    Αναζήτηση μαθητή
                </label>

                <input
                    id="attendance-search"
                    type="search"
                    name="q"
                    maxlength="150"
                    value="<?= e($search) ?>"
                    placeholder="Όνομα, επώνυμο ή email..."
                    class="w-full rounded-xl border border-white/10 bg-[#111114] px-4 py-3 text-sm text-white outline-none focus:border-red-500">
            </div>

            <div>
                <label
                    for="attendance-date"
                    class="mb-2 block text-xs text-zinc-400">
                    Ημερομηνία
                </label>

                <input
                    id="attendance-date"
                    type="date"
                    name="date"
                    value="<?= e($dateFilter) ?>"
                    class="w-full rounded-xl border border-white/10 bg-[#111114] px-4 py-3 text-sm text-white outline-none focus:border-red-500">
            </div>

            <button
                type="submit"
                class="rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700">
                Αναζήτηση
            </button>

            <a
                href="/admin/attendance"
                class="rounded-xl border border-white/10 px-5 py-3 text-center text-sm font-semibold text-zinc-300 hover:bg-white/5">
                Καθαρισμός
            </a>
        </form>
    </div>

    <!-- Attendance table -->
    <div class="overflow-hidden rounded-2xl border border-white/[.08] bg-[#18181c]">

        <div class="border-b border-white/10 p-5">
            <h3 class="text-sm font-bold text-white">
                Καταχωρισμένες παρουσίες
            </h3>
        </div>

        <div class="overflow-x-auto">

            <table class="w-full text-left text-sm">

                <thead class="border-b border-white/10 text-xs text-zinc-500">
                    <tr>
                        <th class="px-5 py-4">Μαθητής</th>
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
                            $utc
                        ))->setTimezone($athens);

                        $source = (string) (
                            $attendance['source'] ?? ''
                        );
                        ?>

                        <tr class="border-b border-white/5 transition hover:bg-white/[.025]">

                            <td class="px-5 py-4">
                                <a
                                    href="/admin/students/<?= (int) $attendance['student_id'] ?>"
                                    class="font-semibold text-white hover:text-red-400">
                                    <?= e($attendance['first_name']) ?>
                                    <?= e($attendance['last_name']) ?>
                                </a>
                            </td>

                            <td class="px-5 py-4 text-zinc-300">
                                <?= e($localDate->format('d/m/Y')) ?>
                            </td>

                            <td class="px-5 py-4 text-zinc-300">
                                <?= e($localDate->format('H:i')) ?>
                            </td>

                            <td class="px-5 py-4 text-zinc-400">
                                <?= e(
                                    $sourceLabels[$source]
                                        ?? ($source !== '' ? $source : '—')
                                ) ?>
                            </td>

                        </tr>
                    <?php endforeach; ?>

                    <?php if ($attendances === []): ?>
                        <tr>
                            <td
                                colspan="4"
                                class="px-5 py-12 text-center text-sm text-zinc-500">
                                Δεν βρέθηκαν παρουσίες.
                            </td>
                        </tr>
                    <?php endif; ?>

                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="flex items-center justify-between gap-3 border-t border-white/10 p-5">

                <span class="text-xs text-zinc-500">
                    Σελίδα <?= $page ?> από <?= $totalPages ?>
                </span>

                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a
                            href="/admin/attendance?<?= e(
                                                        http_build_query([
                                                            'q' => $search,
                                                            'date' => $dateFilter,
                                                            'page' => $page - 1
                                                        ])
                                                    ) ?>"
                            class="rounded-lg border border-white/10 px-3 py-2 text-xs text-zinc-300 hover:bg-white/5">
                            Προηγούμενη
                        </a>
                    <?php endif; ?>

                    <?php if ($page < $totalPages): ?>
                        <a
                            href="/admin/attendance?<?= e(
                                                        http_build_query([
                                                            'q' => $search,
                                                            'date' => $dateFilter,
                                                            'page' => $page + 1
                                                        ])
                                                    ) ?>"
                            class="rounded-lg border border-white/10 px-3 py-2 text-xs text-zinc-300 hover:bg-white/5">
                            Επόμενη
                        </a>
                    <?php endif; ?>
                </div>

            </div>
        <?php endif; ?>

    </div>

</section>