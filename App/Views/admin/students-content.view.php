<?php

/**
 * Student list view.
 *
 * @var array<int, array<string, mixed>> $students
 * @var string $search
 * @var int $page
 * @var int $totalPages
 * @var int $totalResults
 */
?>

<section class="space-y-6">

    <!-- Page heading -->
    <div>
        <h2 class="text-2xl font-extrabold text-white">
            Διαχείριση Μαθητών
        </h2>

        <p class="mt-2 text-sm text-zinc-500">
            Αναζήτηση και προβολή μαθητών της σχολής.
        </p>
    </div>

    <!-- Search -->
    <div
        class="rounded-2xl border border-white/[.08]
               bg-[#18181c] p-5">
        <form
            action="/admin/students"
            method="GET"
            class="flex flex-col gap-3 sm:flex-row">

            <input
                type="search"
                name="q"
                value="<?= e($search) ?>"
                placeholder="Αναζήτηση ονόματος ή email..."
                maxlength="150"
                class="min-w-0 flex-1 rounded-xl
                       border border-white/10
                       bg-[#111114] px-4 py-3
                       text-sm text-white
                       outline-none
                       focus:border-red-500">

            <button
                type="submit"
                class="rounded-xl bg-red-600
                       px-6 py-3 text-sm
                       font-bold text-white
                       hover:bg-red-700">
                Αναζήτηση
            </button>

            <?php if ($search !== ''): ?>
                <a
                    href="/admin/students"
                    class="rounded-xl border border-white/10
                           px-6 py-3 text-center text-sm
                           text-zinc-300 hover:bg-white/5">
                    Καθαρισμός
                </a>
            <?php endif; ?>

        </form>
    </div>

    <!-- Students table -->
    <div
        class="overflow-hidden rounded-2xl
               border border-white/[.08]
               bg-[#18181c]">
        <div
            class="flex items-center justify-between
                   border-b border-white/10 p-5">
            <h3 class="text-sm font-bold">
                Μαθητές
            </h3>

            <span class="text-xs text-zinc-400">
                <?= number_format(
                    $totalResults,
                    0,
                    ',',
                    '.'
                ) ?>
                αποτελέσματα
            </span>
        </div>

        <div class="overflow-x-auto">

            <table class="w-full text-left text-sm">

                <thead
                    class="border-b border-white/10
                           text-xs text-zinc-500">
                    <tr>
                        <th class="px-5 py-4">ID</th>
                        <th class="px-5 py-4">Ονοματεπώνυμο</th>
                        <th class="px-5 py-4">Email</th>
                        <th class="px-5 py-4">Τηλέφωνο</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($students as $student): ?>

                        <tr
                            class="border-b border-white/5
                                   transition hover:bg-white/[.025]">

                            <td class="px-5 py-4 text-zinc-500">
                                <?= (int) $student['id'] ?>
                            </td>


                            <td class="px-5 py-4 font-semibold">

                                <a
                                    href="/admin/students/<?= (int) $student['id'] ?>"
                                    class="text-white transition hover:text-red-400">
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

                        </tr>

                    <?php endforeach; ?>

                    <?php if ($students === []): ?>
                        <tr>
                            <td
                                colspan="4"
                                class="px-5 py-12 text-center
                                       text-sm text-zinc-500">
                                Δεν βρέθηκαν μαθητές.
                            </td>
                        </tr>
                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>

            <div
                class="flex items-center justify-between
                       gap-3 border-t border-white/10 p-5">

                <span class="text-xs text-zinc-500">
                    Σελίδα <?= $page ?>
                    από <?= $totalPages ?>
                </span>

                <div class="flex gap-2">

                    <?php if ($page > 1): ?>
                        <a
                            href="/admin/students?<?= e(
                                                        http_build_query([
                                                            'q' => $search,
                                                            'page' => $page - 1
                                                        ])
                                                    ) ?>"
                            class="rounded-lg border
                                   border-white/10 px-3 py-2
                                   text-xs text-zinc-300
                                   hover:bg-white/5">
                            Προηγούμενη
                        </a>
                    <?php endif; ?>

                    <?php if ($page < $totalPages): ?>
                        <a
                            href="/admin/students?<?= e(
                                                        http_build_query([
                                                            'q' => $search,
                                                            'page' => $page + 1
                                                        ])
                                                    ) ?>"
                            class="rounded-lg border
                                   border-white/10 px-3 py-2
                                   text-xs text-zinc-300
                                   hover:bg-white/5">
                            Επόμενη
                        </a>
                    <?php endif; ?>

                </div>

            </div>

        <?php endif; ?>

    </div>

</section>