<?php

/**
 * Student profile.
 *
 * @var array<string, mixed> $student
 * @var array<string, mixed> $studentActivity
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

    <!-- Student activity: independent of paid coverage -->
    <?php
    loadView('admin/student-activity-content', [
        'student' => $student,
        'studentActivity' => $studentActivity
    ]);
    ?>

    <!-- Student profile tabs -->
    <nav
        id="student-tabs"
        aria-label="Ενότητες μαθητή"
        class="flex flex-wrap gap-2">
        <?php foreach (
            [
                'details' => 'Στοιχεία',
                'attendance' => 'Παρουσίες',
                'memberships' => 'Συνδρομές',
                'barcode' => 'Barcode',
            ] as $tabKey => $tabLabel
        ): ?>

            <button
                type="button"
                data-student-tab="<?= e($tabKey) ?>"
                aria-controls="student-panel-<?= e($tabKey) ?>"
                aria-pressed="<?= $tabKey === 'details' ? 'true' : 'false' ?>"
                class="rounded-xl border border-white/10 bg-[#18181c]
                   px-4 py-2.5 text-sm font-semibold text-zinc-300
                   transition hover:bg-white/10">
                <?= e($tabLabel) ?>
            </button>

        <?php endforeach; ?>
    </nav>


    <!-- Student information -->
    <div
        id="student-panel-details"
        data-student-panel="details"
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
        id="student-panel-barcode"
        data-student-panel="barcode"
        hidden
        class="hidden rounded-2xl border border-white/[.08]
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
                id="student-barcode-card"
                data-student-name="<?= e($fullName) ?>"
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

            <div class="mt-5 flex flex-wrap gap-3">

                <button
                    type="button"
                    id="student-barcode-enlarge"
                    class="inline-flex items-center gap-2 rounded-xl
               bg-red-600 px-5 py-3 text-sm font-bold
               text-white transition hover:bg-red-700">
                    <i data-lucide="maximize-2" class="size-4"></i>
                    Μεγέθυνση
                </button>

                <button
                    type="button"
                    id="student-barcode-print"
                    class="inline-flex items-center gap-2 rounded-xl
               border border-white/10 px-5 py-3
               text-sm font-semibold text-zinc-200
               transition hover:bg-white/5">
                    <i data-lucide="printer" class="size-4"></i>
                    Εκτύπωση
                </button>

            </div>

        <?php else: ?>

            <p class="text-sm text-amber-400">
                Δεν υπάρχει έγκυρο barcode για αυτόν
                τον μαθητή.
            </p>

        <?php endif; ?>

    </div>


    <!-- Barcode enlargement modal -->
    <dialog
        id="student-barcode-dialog"
        aria-label="Μεγέθυνση barcode μαθητή"
        class="w-[calc(100%-2rem)] max-w-3xl
           rounded-2xl border border-white/10
           bg-[#18181c] p-0 text-white
           backdrop:bg-black/80">
        <div class="p-5 sm:p-8">

            <div class="mb-6 flex items-center justify-between gap-4">
                <h3 class="text-lg font-bold text-white">
                    Barcode μαθητή
                </h3>

                <button
                    type="button"
                    id="student-barcode-close"
                    aria-label="Κλείσιμο μεγέθυνσης"
                    class="rounded-lg border border-white/10
                       px-4 py-2 text-sm text-white
                       hover:bg-white/10">
                    Κλείσιμο
                </button>
            </div>

            <div
                id="student-barcode-dialog-content"
                class="overflow-x-auto rounded-xl bg-white p-4 sm:p-6"></div>

        </div>
    </dialog>

</section>
<?php if (is_string($studentNotice) && $studentNotice !== ''): ?>
    <div
        role="status"
        class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-sm text-emerald-300">
        <?= e($studentNotice) ?>
    </div>
<?php endif; ?>
<?php
require basePath(
    'App/Views/admin/student-history-content.view.php'
);
?>
<script type="module" src="/src/student-profile-tabs.js"></script>
<script type="module" src="/src/student-barcode-actions.js"></script>