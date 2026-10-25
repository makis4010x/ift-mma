<?php

/**
 * Student profile.
 *
 * @var array<string, mixed> $student
 */

$fullName = trim(
    (string) $student['first_name'] . ' ' .
        (string) $student['last_name']
);

$fields = [
    'Email' => $student['email'],
    'Τηλέφωνο' => $student['phone'],
    'Τηλέφωνο επικοινωνίας' => $student['contact_phone'],
    'Πατρώνυμο' => $student['father_name'],
    'Ημερομηνία γέννησης' => $student['birth_date'],
    'Διεύθυνση' => $student['address'],
];
?>

<?php
$studentNotice = \Framework\Session::getFlash('student_notice');
?>
<section class="space-y-6">

    <a
        href="/admin/students"
        class="inline-flex items-center gap-2 text-sm
               text-zinc-400 hover:text-white">
        <i data-lucide="arrow-left" class="size-4"></i>
        Πίσω στους μαθητές
    </a>

    <a
        href="/admin/students/<?= (int) $student['id'] ?>/edit"
        class="inline-flex items-center gap-2 rounded-xl border border-white/10 px-5 py-3 text-sm font-semibold text-zinc-200 transition hover:bg-white/5">
        <i data-lucide="pencil" class="size-4"></i>
        Επεξεργασία μαθητή
    </a>



    <div>
        <h2 class="text-2xl font-extrabold text-white">
            <?= e($fullName) ?>
        </h2>

        <p class="mt-2 text-sm text-zinc-500">
            Καρτέλα μαθητή #<?= (int) $student['id'] ?>
        </p>
    </div>

    <!-- Student information -->
    <div
        class="rounded-2xl border border-white/[.08]
               bg-[#18181c] p-6">
        <h3 class="mb-5 text-lg font-bold">
            Προσωπικά στοιχεία
        </h3>

        <div class="grid gap-4 sm:grid-cols-2">

            <?php foreach ($fields as $label => $value): ?>
                <div
                    class="rounded-xl border border-white/5
                           bg-[#111114] p-4">
                    <p class="text-xs text-zinc-500">
                        <?= e($label) ?>
                    </p>

                    <p class="mt-2 break-words text-sm
                              font-semibold text-white">
                        <?= e(
                            trim((string) ($value ?? '')) !== ''
                                ? $value
                                : '—'
                        ) ?>
                    </p>
                </div>
            <?php endforeach; ?>

        </div>
    </div>

    <!-- Barcode -->
    <div
        class="rounded-2xl border border-white/[.08]
               bg-[#18181c] p-6">
        <h3 class="mb-2 text-lg font-bold">
            Barcode μαθητή
        </h3>

        <p class="mb-6 text-sm text-zinc-500">
            Μοναδικός κωδικός εισόδου της σχολής.
        </p>

        <?php if (
            is_string($student['barcode']) &&
            preg_match(
                '/^IFT[0-9]{6,}$/D',
                $student['barcode']
            ) === 1
        ): ?>

            <div
                class="inline-flex max-w-full flex-col
                       items-center gap-3 overflow-x-auto
                       rounded-xl bg-white p-6">
                <?= \App\Support\Barcode128::svg(
                    $student['barcode']
                ) ?>

                <span
                    class="font-mono text-lg font-bold
                           tracking-widest text-black">
                    <?= e($student['barcode']) ?>
                </span>

                <span
                    class="text-xs font-semibold tracking-widest
                           text-zinc-600">
                    IFT ACADEMY
                </span>
            </div>

        <?php else: ?>

            <p class="text-sm text-amber-400">
                Δεν υπάρχει έγκυρο barcode για αυτόν
                τον μαθητή.
            </p>

        <?php endif; ?>

    </div>

</section>
<?php
require basePath(
    'App/Views/admin/student-history-content.view.php'
);
?>

<?php if (is_string($studentNotice) && $studentNotice !== ''): ?>
    <div
        role="status"
        class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-sm text-emerald-300">
        <?= e($studentNotice) ?>
    </div>
<?php endif; ?>