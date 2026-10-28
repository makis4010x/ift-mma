<?php

/**
 * @var array<int, array<string, mixed>> $students
 * @var string $search
 * @var string $activityFilter
 * @var string $activityRule
 * @var int $page
 * @var int $totalPages
 * @var int $totalResults
 */

$activityOptions = [
    'all' => 'Όλες οι καταστάσεις',
    'active' => 'Ενεργοί',
    'inactive' => 'Ανενεργοί',
    'no_payment' => 'Χωρίς ιστορικό πληρωμής',
    'review' => 'Προς έλεγχο'
];

$studentListDate = static fn(?string $value): string =>
$value === null
    ? '—'
    : (new \DateTimeImmutable($value))->format('d/m/Y');

$studentListUrl = static fn(int $target): string =>
'/admin/students?' . http_build_query([
    'q' => $search,
    'activity' => $activityFilter,
    'page' => $target
]);
?>

<section class="space-y-6">

    <div>
        <h2 class="text-2xl font-extrabold text-white">
            Διαχείριση Μαθητών
        </h2>

        <p class="mt-2 text-sm text-zinc-400">
            Όριο αδράνειας: <?= e($activityRule) ?>
            από την ημερομηνία αναφοράς, εκτός ενεργής χειροκίνητης εξαίρεσης.

            <a
                href="/admin/settings"
                class="text-red-400 hover:text-red-300">
                Ρυθμίσεις
            </a>
        </p>
    </div>

    <a
        href="/admin/students/new"
        class="inline-flex items-center gap-2 rounded-xl
               bg-red-600 px-5 py-3 text-sm font-bold
               text-white hover:bg-red-700">
        <i data-lucide="user-round-plus" class="size-4"></i>
        Νέος μαθητής
    </a>

    <!-- Search and activity filter -->
    <div
        class="rounded-2xl border border-white/10
               bg-[#18181c] p-5">
        <form
            action="/admin/students"
            method="GET"
            class="flex flex-col gap-3 lg:flex-row lg:items-end">

            <div class="min-w-0 flex-1">
                <label
                    for="student-search"
                    class="mb-2 block text-xs text-zinc-400">
                    Όνομα, επώνυμο ή email
                </label>

                <input
                    id="student-search"
                    type="search"
                    name="q"
                    value="<?= e($search) ?>"
                    maxlength="150"
                    placeholder="Αναζήτηση μαθητή..."
                    class="w-full rounded-xl border border-white/10
                           bg-[#111114] px-4 py-3 text-sm
                           text-white outline-none
                           focus:border-red-500">
            </div>

            <div>
                <label
                    for="student-activity"
                    class="mb-2 block text-xs text-zinc-400">
                    Κατάσταση μαθητή
                </label>

                <select
                    id="student-activity"
                    name="activity"
                    class="w-full rounded-xl border border-white/10
                           bg-[#111114] px-4 py-3 text-sm text-white">
                    <?php foreach ($activityOptions as $key => $label): ?>
                        <option
                            value="<?= e($key) ?>"
                            <?= $activityFilter === $key
                                ? 'selected'
                                : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button
                type="submit"
                class="rounded-xl bg-red-600 px-5 py-3
                       text-sm font-bold text-white hover:bg-red-700">
                Αναζήτηση
            </button>

            <?php if ($search !== '' || $activityFilter !== 'all'): ?>
                <a
                    href="/admin/students"
                    class="rounded-xl border border-white/10
                           px-5 py-3 text-center text-sm
                           text-zinc-300 hover:bg-white/5">
                    Καθαρισμός
                </a>
            <?php endif; ?>

        </form>
    </div>

    <!-- Students -->
    <div
        class="overflow-hidden rounded-2xl border
               border-white/10 bg-[#18181c]">

        <div
            class="flex items-center justify-between
                   border-b border-white/10 p-5">
            <h3 class="text-sm font-bold text-white">
                Μαθητές
            </h3>

            <span class="text-xs text-zinc-400">
                <?= number_format($totalResults, 0, ',', '.') ?>
                αποτελέσματα
            </span>
        </div>

        <div class="overflow-x-auto">

            <table class="w-full text-left text-sm">

                <thead
                    class="border-b border-white/10
                           text-xs text-zinc-400">
                    <tr>
                        <th class="px-5 py-4">ID</th>
                        <th class="px-5 py-4">Ονοματεπώνυμο</th>
                        <th class="px-5 py-4">Email</th>
                        <th class="px-5 py-4">Τηλέφωνο</th>
                        <th class="px-5 py-4">
                            Κατάσταση βάσει πληρωμής
                        </th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($students as $student): ?>

                        <tr
                            class="border-b border-white/5
                                   hover:bg-white/[.025]">

                            <td class="px-5 py-4 text-zinc-400">
                                <?= (int) $student['id'] ?>
                            </td>

                            <td class="px-5 py-4 font-semibold">
                                <a
                                    href="/admin/students/<?= (int) $student['id'] ?>"
                                    class="text-white hover:text-red-400">
                                    <?= e($student['first_name']) ?>
                                    <?= e($student['last_name']) ?>
                                </a>
                            </td>

                            <td class="px-5 py-4 text-zinc-400">
                                <?= e($student['email'] ?: '—') ?>
                            </td>

                            <td class="px-5 py-4 text-zinc-400">
                                <?= e(
                                    $student['contact_phone']
                                        ?: ($student['phone'] ?: '—')
                                ) ?>
                            </td>

                            <td class="px-5 py-4">

                                <span
                                    class="inline-flex rounded-lg
                                           px-3 py-1.5 text-xs font-semibold
                                           <?= e(
                                                \App\Support\StudentActivityService::badgeClass(
                                                    $student['activity_status']
                                                )
                                            ) ?>">
                                    <?= e(
                                        \App\Support\StudentActivityService::label(
                                            $student['activity_status']
                                        )
                                    ) ?>
                                </span>

                                <p class="mt-2 text-xs text-zinc-400">
                                    Ημερομηνία αναφοράς:

                                    <?= e($studentListDate(
                                        $student['reference_on']
                                    )) ?>
                                </p>

                                <p class="mt-1 max-w-xs text-xs text-zinc-400">
                                    <?= e(
                                        \App\Support\StudentActivityService::basisLabel(
                                            $student['activity_basis']
                                        )
                                    ) ?>
                                </p>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <?php if ($students === []): ?>
                        <tr>
                            <td
                                colspan="5"
                                class="px-5 py-12 text-center text-zinc-400">
                                Δεν βρέθηκαν μαθητές με αυτά τα φίλτρα.
                            </td>
                        </tr>
                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <!-- Pagination preserves search and activity filter -->
        <?php if ($totalPages > 1): ?>

            <div
                class="flex items-center justify-between gap-3
                       border-t border-white/10 p-5">

                <span class="text-xs text-zinc-400">
                    Σελίδα <?= $page ?> από <?= $totalPages ?>
                </span>

                <div class="flex gap-2">

                    <?php if ($page > 1): ?>
                        <a
                            href="<?= e($studentListUrl($page - 1)) ?>"
                            class="rounded-lg border border-white/10
                                   px-3 py-2 text-xs text-zinc-300
                                   hover:bg-white/5">
                            Προηγούμενη
                        </a>
                    <?php endif; ?>

                    <?php if ($page < $totalPages): ?>
                        <a
                            href="<?= e($studentListUrl($page + 1)) ?>"
                            class="rounded-lg border border-white/10
                                   px-3 py-2 text-xs text-zinc-300
                                   hover:bg-white/5">
                            Επόμενη
                        </a>
                    <?php endif; ?>

                </div>

            </div>

        <?php endif; ?>

    </div>

</section>