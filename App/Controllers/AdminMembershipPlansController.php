<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\AdminGuard;
use Framework\Config;
use Framework\Csrf;
use Framework\Database;
use Framework\Session;
use PDOException;

final class AdminMembershipPlansController
{
    public function index(): void
    {
        $admin = AdminGuard::requireAdmin();
        $db = new Database(Config::get('database'));

        $plans = $db->query(
            'SELECT p.id, p.code, p.name, p.default_monthly_fee,
                    p.currency, p.is_active,
                    (SELECT COUNT(*) FROM student_memberships sm
                     WHERE sm.plan_id = p.id) AS student_count,
                    (SELECT COUNT(*) FROM membership_plan_programs mpp
                     WHERE mpp.plan_id = p.id) AS program_count,
                    (SELECT COUNT(*) FROM membership_plan_prices pp
                     WHERE pp.plan_id = p.id) AS price_count
             FROM membership_plans p
             ORDER BY p.is_active DESC, p.name, p.id'
        )->fetchAll();

        $editPlan = null;
        $historyPlan = null;
        $priceHistory = [];

        $editId = $this->positiveId($_GET['edit'] ?? null);

        if ($editId !== null) {
            $editPlan = $db->query(
                'SELECT id, code, name, default_monthly_fee,
                        currency, is_active
                 FROM membership_plans
                 WHERE id = :id',
                ['id' => $editId]
            )->fetch() ?: null;
        }

        if (
            is_array($editPlan)
            && (string) $editPlan['code'] === 'HISTORICAL'
        ) {
            $editPlan = null;
        }

        $historyId = $this->positiveId(
            $_GET['history'] ?? null
        );

        if ($historyId !== null) {
            $historyPlan = $db->query(
                'SELECT id, code, name
                 FROM membership_plans
                 WHERE id = :id',
                ['id' => $historyId]
            )->fetch() ?: null;

            if ($historyPlan !== null) {
                $priceHistory = $db->query(
                    'SELECT pp.id, pp.monthly_fee,
                            pp.currency, pp.effective_at_utc,
                            pp.recorded_at_utc, pp.change_type,
                            pp.changed_by_admin_id,
                            u.email AS admin_email
                     FROM membership_plan_prices pp
                     LEFT JOIN users u
                         ON u.id = pp.changed_by_admin_id
                     WHERE pp.plan_id = :id
                     ORDER BY pp.effective_at_utc DESC,
                              pp.id DESC',
                    ['id' => $historyId]
                )->fetchAll();
            }
        }

        loadView('admin-header', [
            'adminPage' => 'membership-plans',
            'adminEmail' => $admin['email'],
            'plans' => $plans,
            'editPlan' => $editPlan,
            'historyPlan' => $historyPlan,
            'priceHistory' => $priceHistory,
            'planError' => Session::getFlash(
                'membership_plan_error'
            ),
            'planSuccess' => Session::getFlash(
                'membership_plan_success'
            )
        ]);
    }

    public function store(): void
    {
        $admin = AdminGuard::requireAdmin();
        Csrf::protect();

        [$code, $name] = $this->validatedIdentity();

        if ($code === 'HISTORICAL') {
            $this->fail(
                'Η Ιστορική Συνδρομή είναι κατηγορία συστήματος και δεν μπορεί να δημιουργηθεί χειροκίνητα.'
            );
        }

        $fee = $this->validatedFee(
            $_POST['default_monthly_fee'] ?? null
        );

        $db = new Database(Config::get('database'));

        try {
            $db->transaction(
                static function (Database $db) use (
                    $code,
                    $name,
                    $fee,
                    $admin
                ): void {

                    $db->query(
                        "INSERT INTO membership_plans
                            (code, name, default_monthly_fee,
                             currency, is_active)
                         VALUES
                            (:code, :name, :fee, 'EUR', 1)",
                        [
                            'code' => $code,
                            'name' => $name,
                            'fee' => $fee
                        ]
                    );

                    $id = (int) $db->query(
                        'SELECT LAST_INSERT_ID()'
                    )->fetchColumn();

                    $db->query(
                        "INSERT INTO membership_plan_prices
                            (plan_id, monthly_fee, currency,
                             effective_at_utc, recorded_at_utc,
                             change_type, changed_by_admin_id)
                         VALUES
                            (:id, :fee, 'EUR',
                             UTC_TIMESTAMP(6),
                             UTC_TIMESTAMP(6),
                             'initial', :admin_id)",
                        [
                            'id' => $id,
                            'fee' => $fee,
                            'admin_id' => (int) $admin['id']
                        ]
                    );
                }
            );
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                $this->fail(
                    'Υπάρχει ήδη πρόγραμμα με αυτόν τον κωδικό.'
                );
            }

            throw $e;
        }

        $this->success(
            'Το πρόγραμμα δημιουργήθηκε με την αρχική του τιμή.'
        );
    }

    public function update(): void
    {
        AdminGuard::requireAdmin();
        Csrf::protect();

        $id = $this->positiveId(
            $_POST['plan_id'] ?? null
        );

        if ($id === null) {
            $this->fail('Μη έγκυρο πρόγραμμα.');
        }

        [$code, $name] = $this->validatedIdentity();

        $activeInput = $_POST['is_active'] ?? null;

        if (!in_array($activeInput, ['0', '1'], true)) {
            $this->fail('Μη έγκυρη κατάσταση.');
        }

        $db = new Database(Config::get('database'));

        $existingCode = $db->query(
            'SELECT code
     FROM membership_plans
     WHERE id = :id',
            ['id' => $id]
        )->fetchColumn();

        if ($existingCode === false) {
            $this->fail('Το πρόγραμμα δεν βρέθηκε.');
        }

        if (
            (string) $existingCode === 'HISTORICAL'
            || $code === 'HISTORICAL'
        ) {
            $this->fail(
                'Η Ιστορική Συνδρομή είναι προστατευμένη και δεν μπορεί να τροποποιηθεί.'
            );
        }

        try {
            $statement = $db->query(
                "UPDATE membership_plans
                SET code = :code,
                name = :name,
                is_active = :active
                WHERE id = :id
                AND code <> 'HISTORICAL'",
                [
                    'code' => $code,
                    'name' => $name,
                    'active' => (int) $activeInput,
                    'id' => $id
                ]
            );

            if ($statement->rowCount() === 0) {
                $exists = $db->query(
                    'SELECT id
                     FROM membership_plans
                     WHERE id = :id',
                    ['id' => $id]
                )->fetchColumn();

                if ($exists === false) {
                    $this->fail(
                        'Το πρόγραμμα δεν βρέθηκε.'
                    );
                }
            }
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                $this->fail(
                    'Ο κωδικός χρησιμοποιείται από άλλο πρόγραμμα.'
                );
            }

            throw $e;
        }

        $this->success(
            'Τα στοιχεία ενημερώθηκαν. Η τιμή δεν άλλαξε.'
        );
    }

    public function changePrice(): void
    {
        $admin = AdminGuard::requireAdmin();
        Csrf::protect();

        $id = $this->positiveId(
            $_POST['plan_id'] ?? null
        );

        if ($id === null) {
            $this->fail('Μη έγκυρο πρόγραμμα.');
        }

        $fee = $this->validatedFee(
            $_POST['new_monthly_fee'] ?? null
        );

        $db = new Database(Config::get('database'));

        $result = $db->transaction(
            static function (Database $db) use (
                $id,
                $fee,
                $admin
            ): string {

                // One price update at a time per plan.
                $plan = $db->query(
                    'SELECT id, code, default_monthly_fee, currency
                    FROM membership_plans
                    WHERE id = :id
                    FOR UPDATE',
                    ['id' => $id]
                )->fetch();

                if (!is_array($plan)) {
                    return 'missing';
                }

                if ((string) $plan['code'] === 'HISTORICAL') {
                    return 'protected';
                }

                $current = number_format(
                    (float) $plan['default_monthly_fee'],
                    2,
                    '.',
                    ''
                );

                if ($current === $fee) {
                    return 'same';
                }

                // Append immutable price history.
                $db->query(
                    "INSERT INTO membership_plan_prices
                        (plan_id, monthly_fee, currency,
                         effective_at_utc, recorded_at_utc,
                         change_type, changed_by_admin_id)
                     VALUES
                        (:id, :fee, :currency,
                         UTC_TIMESTAMP(6),
                         UTC_TIMESTAMP(6),
                         'change', :admin_id)",
                    [
                        'id' => $id,
                        'fee' => $fee,
                        'currency' => $plan['currency'],
                        'admin_id' => (int) $admin['id']
                    ]
                );

                // Keep existing code compatible.
                $db->query(
                    'UPDATE membership_plans
                     SET default_monthly_fee = :fee
                     WHERE id = :id',
                    [
                        'id' => $id,
                        'fee' => $fee
                    ]
                );

                return 'saved';
            }
        );

        if ($result === 'missing') {
            $this->fail('Το πρόγραμμα δεν βρέθηκε.');
        }

        if ($result === 'same') {
            $this->fail(
                'Η νέα τιμή είναι ίδια με την τρέχουσα.'
            );
        }

        if ($result === 'protected') {
            $this->fail(
                'Δεν επιτρέπεται αλλαγή της γενικής τιμής της Ιστορικής Συνδρομής.'
            );
        }

        $this->success(
            'Η νέα τιμή ισχύει από τώρα. Το ιστορικό διατηρήθηκε.'
        );
    }

    public function delete(): void
    {
        AdminGuard::requireAdmin();
        Csrf::protect();

        $id = $this->positiveId(
            $_POST['plan_id'] ?? null
        );

        if ($id === null) {
            $this->fail('Μη έγκυρο πρόγραμμα.');
        }

        $db = new Database(Config::get('database'));

        try {
            $result = $db->transaction(
                static function (Database $db) use ($id): string {
                    $plan = $db->query(
                        'SELECT id, code
                        FROM membership_plans
                        WHERE id = :id
                        FOR UPDATE',
                        ['id' => $id]
                    )->fetch();

                    if (!is_array($plan)) {
                        return 'missing';
                    }

                    if ((string) $plan['code'] === 'HISTORICAL') {
                        return 'protected';
                    }

                    $students = (int) $db->query(
                        'SELECT COUNT(*)
                         FROM student_memberships
                         WHERE plan_id = :id',
                        ['id' => $id]
                    )->fetchColumn();

                    $programs = (int) $db->query(
                        'SELECT COUNT(*)
                         FROM membership_plan_programs
                         WHERE plan_id = :id',
                        ['id' => $id]
                    )->fetchColumn();

                    $prices = (int) $db->query(
                        'SELECT COUNT(*)
                         FROM membership_plan_prices
                         WHERE plan_id = :id',
                        ['id' => $id]
                    )->fetchColumn();

                    if (
                        $students > 0 ||
                        $programs > 0 ||
                        $prices > 1
                    ) {
                        return 'in_use';
                    }

                    // Only an unused plan with its initial
                    // price may be permanently removed.
                    $db->query(
                        'DELETE FROM membership_plan_prices
                         WHERE plan_id = :id',
                        ['id' => $id]
                    );

                    $db->query(
                        'DELETE FROM membership_plans
                         WHERE id = :id',
                        ['id' => $id]
                    );

                    return 'deleted';
                }
            );
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1451) {
                $this->fail(
                    'Το πρόγραμμα χρησιμοποιείται. Απενεργοποίησέ το αντί να το διαγράψεις.'
                );
            }

            throw $e;
        }

        if ($result === 'missing') {
            $this->fail('Το πρόγραμμα δεν βρέθηκε.');
        }

        if ($result === 'in_use') {
            $this->fail(
                'Δεν διαγράφουμε προγράμματα με συνδέσεις ή ιστορικό αλλαγών. Κάνε τα ανενεργά.'
            );
        }

        if ($result === 'protected') {
            $this->fail(
                'Η Ιστορική Συνδρομή είναι μόνιμη και δεν μπορεί να διαγραφεί.'
            );
        }

        $this->success(
            'Το αχρησιμοποίητο πρόγραμμα διαγράφηκε.'
        );
    }

    /** @return array{string, string} */
    private function validatedIdentity(): array
    {
        $rawCode = $_POST['code'] ?? null;
        $rawName = $_POST['name'] ?? null;

        if (
            !is_string($rawCode) ||
            !is_string($rawName)
        ) {
            $this->fail(
                'Συμπλήρωσε κωδικό και όνομα.'
            );
        }

        $code = strtoupper(trim($rawCode));
        $name = trim($rawName);

        if (
            preg_match(
                '/\A[A-Z0-9][A-Z0-9_-]{0,49}\z/D',
                $code
            ) !== 1
        ) {
            $this->fail(
                'Κωδικός: 1–50 λατινικά γράμματα, αριθμοί, - ή _.'
            );
        }

        if (
            preg_match('/\A.{2,150}\z/us', $name) !== 1
        ) {
            $this->fail(
                'Το όνομα πρέπει να έχει 2–150 χαρακτήρες.'
            );
        }

        return [$code, $name];
    }

    private function validatedFee(mixed $raw): string
    {
        if (!is_string($raw)) {
            $this->fail('Συμπλήρωσε μηνιαίο ποσό.');
        }

        $fee = str_replace(
            ',',
            '.',
            trim($raw)
        );

        if (
            preg_match(
                '/\A[0-9]{1,8}(?:\.[0-9]{1,2})?\z/D',
                $fee
            ) !== 1
        ) {
            $this->fail(
                'Μη έγκυρο ποσό. Παράδειγμα: 45,00.'
            );
        }

        return number_format(
            (float) $fee,
            2,
            '.',
            ''
        );
    }

    private function positiveId(mixed $raw): ?int
    {
        if (!is_int($raw) && !is_string($raw)) {
            return null;
        }

        $value = filter_var(
            $raw,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        return $value === false ? null : $value;
    }

    private function fail(string $message): never
    {
        Session::flash(
            'membership_plan_error',
            $message
        );

        $this->redirect();
    }

    private function success(string $message): never
    {
        Session::flash(
            'membership_plan_success',
            $message
        );

        $this->redirect();
    }

    private function redirect(): never
    {
        header(
            'Location: /admin/membership-plans',
            true,
            303
        );

        exit;
    }
}
