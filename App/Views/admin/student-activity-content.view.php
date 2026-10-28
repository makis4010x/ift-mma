<?php

/**
 * @var array<string, mixed> $student
 * @var array<string, mixed> $studentActivity
 */

$activityError = \Framework\Session::getFlash(
    'activity_error_' . (int) $student['id']
);

$activitySuccess = \Framework\Session::getFlash(
    'activity_success_' . (int) $student['id']
);

$activityDate = static fn(?string $value): string =>
$value === null
    ? '—'
    : (new \DateTimeImmutable($value))->format('d/m/Y');

$activityManual =
    (int) $studentActivity['override_active'] === 1;

$activityInput =
    'w-full rounded-xl border border-white/10 '
    . 'bg-[#111114] px-4 py-3 text-sm text-white';
?>

<section
    id="student-activity"
    class="rounded-2xl border border-white/10 bg-[#18181c] p-5">

    <div class="flex flex-wrap items-center gap-3">

        <h3 class="font-bold text-white">
            Κατάσταση μαθητή
        </h3>

        <span
            class="rounded-lg px-3 py-1.5 text-xs font-bold
                   <?= e(
                        \App\Support\StudentActivityService::badgeClass(
                            $studentActivity['activity_status']
                        )
                    ) ?>">
            <?= e(
                \App\Support\StudentActivityService::label(
                    $studentActivity['activity_status']
                )
            ) ?>
        </span>

    </div>

    <p class="mt-3 text-sm text-zinc-300">
        <?= e(
            \App\Support\StudentActivityService::basisLabel(
                $studentActivity['activity_basis']
            )
        ) ?>
    </p>

    <?php if ($activityManual): ?>

        <p class="mt-2 text-sm text-zinc-400">
            <?= e($studentActivity['override_reason']) ?>

            <br>

            <?= $studentActivity['active_until'] === null
                ? 'Χωρίς ημερομηνία λήξης — μέχρι την ανάκληση.'
                : 'Ισχύει έως και '
                . e($activityDate(
                    $studentActivity['active_until']
                ))
            ?>
        </p>

    <?php else: ?>

        <p class="mt-2 text-xs text-zinc-400">

            Ημερομηνία αναφοράς:
            <?= e($activityDate(
                $studentActivity['reference_on']
            )) ?>

            · Όριο:
            <?= e($studentActivity['rule']) ?>

            <?php if ($studentActivity['inactive_on'] !== null): ?>

                · Συμπλήρωση ορίου:
                <?= e($activityDate(
                    $studentActivity['inactive_on']
                )) ?>

            <?php endif; ?>

        </p>

    <?php endif; ?>

    <?php if ((int) $studentActivity['history_problem'] === 1): ?>

        <p class="mt-2 text-xs text-amber-300">
            Υπάρχουν εκκρεμότητες στο παλιό ιστορικό.
            Δεν χρησιμοποιήθηκαν αμφίβολες εγγραφές.
        </p>

    <?php endif; ?>

    <?php if (
        is_string($activityError) &&
        $activityError !== ''
    ): ?>

        <p role="alert" class="mt-4 text-sm text-amber-300">
            <?= e($activityError) ?>
        </p>

    <?php endif; ?>

    <?php if (
        is_string($activitySuccess) &&
        $activitySuccess !== ''
    ): ?>

        <p role="status" class="mt-4 text-sm text-emerald-300">
            <?= e($activitySuccess) ?>
        </p>

    <?php endif; ?>

    <!-- Change activity mode -->
    <details class="mt-5">

        <summary
            class="cursor-pointer text-sm font-bold text-red-400">
            Αλλαγή τρόπου κατάστασης
        </summary>

        <form
            method="POST"
            action="/admin/students/<?= (int) $student['id'] ?>/activity"
            class="mt-4 grid gap-4 sm:grid-cols-2">

            <?= \Framework\Csrf::field() ?>

            <input
                type="hidden"
                name="request_key"
                value="<?= e(bin2hex(random_bytes(16))) ?>">

            <input
                type="hidden"
                name="expected_event_id"
                value="<?= (int) $studentActivity['override_event_id'] ?>">

            <div>
                <label
                    for="activity-mode"
                    class="mb-2 block text-sm text-zinc-300">
                    Τρόπος κατάστασης
                </label>

                <select
                    id="activity-mode"
                    name="mode"
                    class="<?= e($activityInput) ?>">

                    <option
                        value="auto"
                        <?= !$activityManual ? 'selected' : '' ?>>
                        Αυτόματα από το ιστορικό
                    </option>

                    <option
                        value="active"
                        <?= $activityManual ? 'selected' : '' ?>>
                        Ενεργός κατ’ εξαίρεση
                    </option>

                </select>
            </div>

            <div>
                <label
                    for="activity-until"
                    class="mb-2 block text-sm text-zinc-300">
                    Εξαίρεση έως και (προαιρετικά)
                </label>

                <input
                    id="activity-until"
                    type="date"
                    name="active_until"
                    min="<?= e($studentActivity['today']) ?>"
                    value="<?= e(
                                $activityManual
                                    ? $studentActivity['active_until']
                                    : ''
                            ) ?>"
                    class="<?= e($activityInput) ?>">

                <p class="mt-1 text-xs text-zinc-400">
                    Κενό = χωρίς λήξη.
                    Αγνοείται στην αυτόματη κατάσταση.
                </p>
            </div>

            <div class="sm:col-span-2">

                <label
                    for="activity-reason"
                    class="mb-2 block text-sm text-zinc-300">
                    Αιτιολογία αλλαγής
                </label>

                <textarea
                    id="activity-reason"
                    name="reason"
                    required
                    minlength="5"
                    maxlength="255"
                    rows="2"
                    placeholder="Π.χ. Δωρεάν συμμετοχή ή επιστροφή στον αυτόματο κανόνα"
                    class="<?= e($activityInput) ?>"></textarea>

            </div>

            <div class="sm:col-span-2">

                <button
                    type="submit"
                    class="rounded-xl bg-red-600 px-5 py-3
                           text-sm font-bold text-white hover:bg-red-700">
                    Αποθήκευση κατάστασης
                </button>

            </div>

        </form>

    </details>

    <!-- Audit history -->
    <?php if ($studentActivity['override_history'] !== []): ?>

        <details class="mt-5 text-sm">

            <summary class="cursor-pointer text-zinc-400">
                Ιστορικό αλλαγών — τελευταίες 10 ενέργειες
            </summary>

            <div class="mt-3 space-y-3">

                <?php foreach (
                    $studentActivity['override_history'] as $event
                ): ?>

                    <div
                        class="rounded-xl bg-[#111114] p-3 text-zinc-300">

                        <p>
                            <?= $event['mode'] === 'active'
                                ? 'Ενεργός κατ’ εξαίρεση'
                                : 'Επιστροφή στην αυτόματη κατάσταση'
                            ?>
                        </p>

                        <p class="mt-1">
                            <?= e($event['reason']) ?>
                        </p>

                        <p class="mt-1 text-xs text-zinc-400">

                            <?= e(
                                (new \DateTimeImmutable(
                                    $event['changed_at_utc'],
                                    new \DateTimeZone('UTC')
                                ))->setTimezone(
                                    new \DateTimeZone('Europe/Athens')
                                )->format('d/m/Y H:i')
                            ) ?>

                            · Admin

                            <?= $event['changed_by_admin_id'] !== null
                                ? '#' . (int) $event['changed_by_admin_id']
                                : '(διαγραμμένος λογαριασμός)'
                            ?>

                            · <?= e($event['admin_email_snapshot']) ?>

                            <?php if ($event['mode'] === 'active'): ?>

                                · Λήξη:

                                <?= $event['active_until'] === null
                                    ? 'Χωρίς λήξη'
                                    : e($activityDate(
                                        $event['active_until']
                                    ))
                                ?>

                            <?php endif; ?>

                        </p>

                    </div>

                <?php endforeach; ?>

            </div>

        </details>

    <?php endif; ?>

    <p class="mt-4 text-xs text-zinc-500">
        Η κατάσταση δεν αποτελεί εξοφλημένη κάλυψη,
        δεν δημιουργεί οφειλή και δεν αλλάζει
        δικαιώματα σύνδεσης ή scanner.
    </p>

</section>