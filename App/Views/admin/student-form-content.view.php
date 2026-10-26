<?php

/**
 * Student creation / editing form.
 *
 * @var array<string, mixed> $student
 * @var string $studentFormMode
 * @var string $studentFormAction
 * @var string|null $studentFormError
 */

$isEdit = $studentFormMode === 'edit';

$fields = [
    ['first_name', 'Όνομα', 'text', 100, true],
    ['last_name', 'Επώνυμο', 'text', 100, true],
    ['father_name', 'Πατρώνυμο', 'text', 100, false],
    ['birth_date', 'Ημερομηνία γέννησης', 'date', 10, false],
    ['email', 'Email', 'email', 254, false],
    ['phone', 'Τηλέφωνο', 'tel', 30, false],
    ['contact_phone', 'Τηλέφωνο επικοινωνίας', 'tel', 30, false],
    ['identity_number', 'Αριθμός ταυτότητας', 'text', 30, false],
];
?>

<section class="space-y-6">

    <a
        href="<?= $isEdit
                    ? '/admin/students/' . (int) $student['id']
                    : '/admin/students'
                ?>"
        class="inline-flex items-center gap-2 text-sm text-zinc-400 hover:text-white">
        <i data-lucide="arrow-left" class="size-4"></i>
        Επιστροφή
    </a>

    <div>
        <h2 class="text-2xl font-extrabold text-white">
            <?= $isEdit
                ? 'Επεξεργασία μαθητή'
                : 'Νέος μαθητής'
            ?>
        </h2>

        <p class="mt-2 text-sm text-zinc-500">
            <?= $isEdit
                ? 'Το ID, το barcode και το ιστορικό δεν θα αλλάξουν.'
                : 'Με την αποθήκευση θα δημιουργηθεί μοναδικό barcode.'
            ?>
        </p>
    </div>

    <?php if (
        is_string($studentFormError) &&
        $studentFormError !== ''
    ): ?>
        <div
            role="alert"
            class="rounded-xl border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-300">
            <?= e($studentFormError) ?>
        </div>
    <?php endif; ?>

    <form
        action="<?= e($studentFormAction) ?>"
        method="POST"
        class="space-y-6 rounded-2xl border border-white/[.08] bg-[#18181c] p-6">
        <?= \Framework\Csrf::field() ?>

        <div class="grid gap-5 sm:grid-cols-2">

            <?php foreach (
                $fields as [$name, $label, $type, $maxlength, $required]
            ): ?>

                <div>
                    <label
                        for="student-<?= e($name) ?>"
                        class="mb-2 block text-sm text-zinc-400">
                        <?= e($label) ?>
                        <?= $required ? ' *' : '' ?>
                    </label>

                    <input
                        id="student-<?= e($name) ?>"
                        type="<?= e($type) ?>"
                        name="<?= e($name) ?>"
                        value="<?= e($student[$name] ?? '') ?>"
                        maxlength="<?= (int) $maxlength ?>"
                        <?= $required ? 'required' : '' ?>
                        class="w-full rounded-xl border border-white/10 bg-[#111114] px-4 py-3 text-sm text-white outline-none focus:border-red-500">
                </div>

            <?php endforeach; ?>

        </div>

        <div>
            <label
                for="student-address"
                class="mb-2 block text-sm text-zinc-400">
                Διεύθυνση
            </label>

            <textarea
                id="student-address"
                name="address"
                rows="2"
                maxlength="255"
                class="w-full rounded-xl border border-white/10 bg-[#111114] px-4 py-3 text-sm text-white outline-none focus:border-red-500"><?= e($student['address'] ?? '') ?></textarea>
        </div>

        <div class="flex flex-wrap items-center gap-3">

            <button
                type="submit"
                class="rounded-xl bg-red-600 px-6 py-3 text-sm font-bold text-white hover:bg-red-700">
                <?= $isEdit
                    ? 'Αποθήκευση αλλαγών'
                    : 'Δημιουργία μαθητή'
                ?>
            </button>

            <a
                href="<?= $isEdit
                            ? '/admin/students/' . (int) $student['id']
                            : '/admin/students'
                        ?>"
                class="rounded-xl border border-white/10 px-6 py-3 text-sm text-zinc-300 hover:bg-white/5">
                Ακύρωση
            </a>

        </div>
    </form>
</section>