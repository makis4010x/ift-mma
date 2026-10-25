<?php

/**
 * Variables provided by AdminAdministratorsController.
 *
 * @var array<int, array<string, mixed>> $admins
 * @var int $currentAdminId
 * @var string|null $adminError
 * @var string|null $adminSuccess
 */
?>
<section class="space-y-6">

    <div>
        <h2 class="text-2xl font-extrabold text-white">
            Διαχείριση Διαχειριστών
        </h2>

        <p class="mt-2 text-sm text-zinc-500">
            Δημιουργία και διαχείριση λογαριασμών Admin.
        </p>
    </div>

    <?php if (!empty($adminError)): ?>
        <div
            role="alert"
            class="rounded-xl border border-red-500/20
                   bg-red-500/10 p-4 text-sm text-red-300">
            <?= e($adminError) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($adminSuccess)): ?>
        <div
            role="status"
            class="rounded-xl border border-emerald-500/20
                   bg-emerald-500/10 p-4 text-sm text-emerald-300">
            <?= e($adminSuccess) ?>
        </div>
    <?php endif; ?>

    <!-- Existing administrators -->
    <div
        class="overflow-hidden rounded-2xl border
               border-white/[.08] bg-[#18181c]">
        <div class="border-b border-white/10 p-5">
            <h3 class="text-sm font-bold">
                Υπάρχοντες διαχειριστές
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">

                <thead
                    class="border-b border-white/10
                           text-xs text-zinc-500">
                    <tr>
                        <th class="px-5 py-4">Email</th>
                        <th class="px-5 py-4">Κατάσταση</th>
                        <th class="px-5 py-4">Τελευταία σύνδεση</th>
                        <th class="px-5 py-4">Ενέργειες</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    /** @var array<int, array<string, mixed>> $admins */
                    /** @var string|null $adminError */
                    /** @var string|null $adminSuccess */
                    foreach ($admins as $admin): ?>
                        <tr class="border-b border-white/5">

                            <td class="px-5 py-4 font-medium">
                                <?= e($admin['email']) ?>
                            </td>

                            <td class="px-5 py-4">
                                <?php if ($admin['status'] === 'active'): ?>
                                    <span class="text-emerald-400">
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="text-zinc-500">
                                        <?= e($admin['status']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="px-5 py-4 text-zinc-400">
                                <?= $admin['last_login_at'] !== null
                                    ? e($admin['last_login_at'])
                                    : 'Ποτέ'
                                ?>
                            </td>


                            <td class="px-5 py-4">

                                <?php
                                $isCurrentAdmin =
                                    (int) $admin['id'] === (int) $currentAdminId;

                                $isActive = $admin['status'] === 'active';
                                ?>

                                <?php if ($isCurrentAdmin): ?>

                                    <span class="text-xs text-zinc-500">
                                        Ο λογαριασμός σου
                                    </span>

                                <?php elseif (in_array(
                                    $admin['status'],
                                    ['active', 'disabled'],
                                    true
                                )): ?>

                                    <form
                                        action="/admin/administrators/<?= (int) $admin['id'] ?>/status"
                                        method="POST">

                                        <?= \Framework\Csrf::field() ?>

                                        <input
                                            type="hidden"
                                            name="status"
                                            value="<?= $isActive ? 'disabled' : 'active' ?>">

                                        <button
                                            type="submit"
                                            class="rounded-lg border px-3 py-2
                       text-xs font-semibold transition
                       <?= $isActive
                                        ? 'border-red-500/20 text-red-400 hover:bg-red-500/10'
                                        : 'border-emerald-500/20 text-emerald-400 hover:bg-emerald-500/10'
                        ?>">
                                            <?= $isActive
                                                ? 'Απενεργοποίηση'
                                                : 'Ενεργοποίηση'
                                            ?>
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <span class="text-xs text-zinc-500">
                                        Εκκρεμεί ενεργοποίηση
                                    </span>

                                <?php endif; ?>


                                <?php if (
                                    (int) $admin['id'] !== (int) $currentAdminId
                                ): ?>

                                    <form
                                        action="/admin/administrators/<?= (int) $admin['id'] ?>/delete"
                                        method="POST"
                                        class="mt-2"
                                        onsubmit="return confirm('Προσοχή! Ο λογαριασμός θα διαγραφεί οριστικά. Θέλεις να συνεχίσεις;');">

                                        <?= \Framework\Csrf::field() ?>

                                        <button
                                            type="submit"
                                            class="rounded-lg border border-red-500/30
                   px-3 py-2 text-xs font-semibold
                   text-red-400 transition
                   hover:bg-red-500/10">
                                            <i data-lucide="trash-2"
                                                class="mr-1 inline-block size-3.5"></i>

                                            Διαγραφή
                                        </button>

                                    </form>

                                <?php endif; ?>

                            </td>

                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>
    </div>

    <!-- Create administrator -->
    <div
        class="rounded-2xl border border-white/[.08]
               bg-[#18181c] p-6">
        <h3 class="mb-6 text-lg font-bold">
            Προσθήκη νέου Admin
        </h3>

        <form
            action="/admin/administrators"
            method="POST"
            class="max-w-lg space-y-5">

            <?= \Framework\Csrf::field() ?>

            <div>
                <label
                    for="new-admin-email"
                    class="mb-2 block text-sm text-zinc-400">
                    Email
                </label>

                <input
                    id="new-admin-email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    maxlength="254"
                    required
                    class="w-full rounded-xl border border-white/10
                           bg-[#111114] px-4 py-3 text-white
                           outline-none focus:border-red-500">
            </div>

            <div>
                <label
                    for="new-admin-password"
                    class="mb-2 block text-sm text-zinc-400">
                    Κωδικός
                </label>

                <input
                    id="new-admin-password"
                    type="password"
                    name="password"
                    autocomplete="new-password"
                    minlength="8"
                    required
                    class="w-full rounded-xl border border-white/10
                           bg-[#111114] px-4 py-3 text-white
                           outline-none focus:border-red-500">
            </div>

            <div>
                <label
                    for="new-admin-confirm"
                    class="mb-2 block text-sm text-zinc-400">
                    Επιβεβαίωση κωδικού
                </label>

                <input
                    id="new-admin-confirm"
                    type="password"
                    name="password_confirmation"
                    autocomplete="new-password"
                    minlength="8"
                    required
                    class="w-full rounded-xl border border-white/10
                           bg-[#111114] px-4 py-3 text-white
                           outline-none focus:border-red-500">
            </div>

            <button
                type="submit"
                class="rounded-xl bg-red-600 px-6 py-3
                       text-sm font-bold text-white
                       transition hover:bg-red-700">
                Δημιουργία Admin
            </button>

        </form>
    </div>

</section>