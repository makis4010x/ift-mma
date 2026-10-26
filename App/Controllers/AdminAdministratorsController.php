<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\AdminGuard;
use Framework\Config;
use Framework\Csrf;
use Framework\Database;
use Framework\Session;
use PDOException;

class AdminAdministratorsController
{
    public function index(): void
    {
        $currentAdmin = AdminGuard::requireAdmin();

        $db = new Database(Config::get('database'));

        $admins = $db->query(
            "SELECT
                id,
                email,
                status,
                last_login_at,
                created_at
             FROM users
             WHERE role = 'admin'
             ORDER BY id ASC"
        )->fetchAll();

        loadView('admin-header', [
            'adminPage' => 'administrators',
            'adminEmail' => $currentAdmin['email'],
            'currentAdminId' => (int) $currentAdmin['id'],
            'admins' => $admins,
            'adminError' => Session::getFlash(
                'admin_manage_error'
            ),
            'adminSuccess' => Session::getFlash(
                'admin_manage_success'
            )
        ]);
    }

    public function store(): void
    {
        AdminGuard::requireAdmin();
        Csrf::protect();

        $emailInput = $_POST['email'] ?? null;
        $password = $_POST['password'] ?? null;
        $confirmation = $_POST['password_confirmation'] ?? null;

        if (
            !is_string($emailInput) ||
            !is_string($password) ||
            !is_string($confirmation)
        ) {
            $this->fail('Μη έγκυρα στοιχεία φόρμας.');
        }

        $email = strtolower(trim($emailInput));

        if (
            strlen($email) > 254 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            $this->fail('Το email δεν είναι έγκυρο.');
        }

        if (
            strlen($password) < 8 ||
            strlen($password) > 72
        ) {
            $this->fail(
                'Ο κωδικός πρέπει να έχει 8 έως 72 bytes.'
            );
        }

        if (!hash_equals($password, $confirmation)) {
            $this->fail(
                'Οι δύο κωδικοί δεν ταιριάζουν.'
            );
        }

        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $db = new Database(Config::get('database'));

        try {
            $db->query(
                "INSERT INTO users (
                    student_id,
                    email,
                    password_hash,
                    role,
                    status
                ) VALUES (
                    NULL,
                    :email,
                    :password_hash,
                    'admin',
                    'active'
                )",
                [
                    'email' => $email,
                    'password_hash' => $passwordHash
                ]
            );
        } catch (PDOException $exception) {
            // Duplicate email (unique index).
            if ((string) $exception->getCode() === '23000') {
                $this->fail(
                    'Το email χρησιμοποιείται ήδη από λογαριασμό.'
                );
            }

            throw $exception;
        }

        Session::flash(
            'admin_manage_success',
            'Ο νέος διαχειριστής δημιουργήθηκε επιτυχώς.'
        );

        $this->redirect();
    }


    public function changeStatus(array $params): void
    {
        $currentAdmin = AdminGuard::requireAdmin();
        Csrf::protect();

        $idInput = $params['id'] ?? null;
        $requestedStatus = $_POST['status'] ?? null;

        $targetId = is_string($idInput)
            ? filter_var(
                $idInput,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            )
            : false;

        if (
            $targetId === false ||
            !is_string($requestedStatus) ||
            !in_array(
                $requestedStatus,
                ['active', 'disabled'],
                true
            )
        ) {
            $this->fail('Μη έγκυρη ενέργεια.');
        }

        $currentAdminId = (int) $currentAdmin['id'];

        $db = new Database(Config::get('database'));

        $result = $db->transaction(
            static function (Database $db) use (
                $targetId,
                $currentAdminId,
                $requestedStatus
            ): string {

                // Lock administrator rows to prevent
                // concurrent status changes.
                $rows = $db->query(
                    "SELECT id, status
                 FROM users
                 WHERE role = 'admin'
                 ORDER BY id
                 FOR UPDATE"
                )->fetchAll();

                $statuses = [];

                foreach ($rows as $row) {
                    $statuses[(int) $row['id']] =
                        $row['status'];
                }

                // The acting admin must still be active.
                if (
                    ($statuses[$currentAdminId] ?? null)
                    !== 'active'
                ) {
                    return 'unauthorized';
                }

                if (!isset($statuses[$targetId])) {
                    return 'not_found';
                }

                if ($targetId === $currentAdminId) {
                    return 'self';
                }

                if ($statuses[$targetId] === 'pending') {
                    return 'pending';
                }

                if ($statuses[$targetId] === $requestedStatus) {
                    return 'unchanged';
                }

                // Protect the last active administrator.
                if ($requestedStatus === 'disabled') {
                    $activeCount = count(
                        array_filter(
                            $statuses,
                            static fn($status) =>
                            $status === 'active'
                        )
                    );

                    if ($activeCount <= 1) {
                        return 'last_admin';
                    }
                }

                $db->query(
                    "UPDATE users
                 SET status = :status
                 WHERE id = :id
                   AND role = 'admin'",
                    [
                        'status' => $requestedStatus,
                        'id' => $targetId
                    ]
                );

                return 'updated';
            }
        );

        if ($result !== 'updated') {
            $messages = [
                'unauthorized' => 'Δεν έχεις πλέον δικαίωμα πρόσβασης.',
                'not_found' => 'Ο διαχειριστής δεν βρέθηκε.',
                'self' => 'Δεν μπορείς να απενεργοποιήσεις τον εαυτό σου.',
                'pending' => 'Ο λογαριασμός εκκρεμεί ενεργοποίηση.',
                'unchanged' => 'Ο λογαριασμός έχει ήδη αυτή την κατάσταση.',
                'last_admin' => 'Δεν μπορεί να απενεργοποιηθεί ο τελευταίος ενεργός Admin.'
            ];

            $this->fail(
                $messages[$result] ?? 'Η ενέργεια απέτυχε.'
            );
        }

        Session::flash(
            'admin_manage_success',
            $requestedStatus === 'active'
                ? 'Ο διαχειριστής ενεργοποιήθηκε.'
                : 'Ο διαχειριστής απενεργοποιήθηκε.'
        );

        $this->redirect();
    }



    public function destroy(array $params): void
    {
        $currentAdmin = AdminGuard::requireAdmin();
        Csrf::protect();

        $rawId = $params['id'] ?? null;

        $targetId = is_string($rawId)
            ? filter_var(
                $rawId,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            )
            : false;

        if ($targetId === false) {
            $this->fail('Μη έγκυρος διαχειριστής.');
        }

        $currentAdminId = (int) $currentAdmin['id'];

        if ($targetId === $currentAdminId) {
            $this->fail(
                'Δεν μπορείς να διαγράψεις τον λογαριασμό σου.'
            );
        }

        $db = new Database(Config::get('database'));

        try {
            $result = $db->transaction(
                static function (Database $db) use (
                    $targetId,
                    $currentAdminId
                ): string {

                    // Lock all admin accounts during deletion.
                    $rows = $db->query(
                        "SELECT id, status
                     FROM users
                     WHERE role = 'admin'
                     ORDER BY id
                     FOR UPDATE"
                    )->fetchAll();

                    $admins = [];

                    foreach ($rows as $row) {
                        $admins[(int) $row['id']] =
                            $row['status'];
                    }

                    if (
                        ($admins[$currentAdminId] ?? null)
                        !== 'active'
                    ) {
                        return 'unauthorized';
                    }

                    if (!array_key_exists($targetId, $admins)) {
                        return 'not_found';
                    }

                    if ($targetId === $currentAdminId) {
                        return 'self';
                    }

                    $activeCount = count(
                        array_filter(
                            $admins,
                            static fn($status) =>
                            $status === 'active'
                        )
                    );

                    // Never remove the last active admin.
                    if (
                        $admins[$targetId] === 'active' &&
                        $activeCount <= 1
                    ) {
                        return 'last_admin';
                    }

                    // Preserve import-resolution history.
                    $references = (int) $db->query(
                        "SELECT COUNT(*)
                     FROM import_issues
                     WHERE resolved_by = :id",
                        ['id' => $targetId]
                    )->fetchColumn();

                    if ($references > 0) {
                        return 'has_history';
                    }

                    $statement = $db->query(
                        "DELETE FROM users
                     WHERE id = :id
                       AND role = 'admin'
                       AND student_id IS NULL",
                        ['id' => $targetId]
                    );

                    return $statement->rowCount() === 1
                        ? 'deleted'
                        : 'not_found';
                }
            );
        } catch (\PDOException $exception) {

            if ((string) $exception->getCode() === '23000') {
                $this->fail(
                    'Ο λογαριασμός συνδέεται με άλλα '
                        . 'δεδομένα και δεν μπορεί να διαγραφεί. '
                        . 'Χρησιμοποίησε την απενεργοποίηση.'
                );
            }

            throw $exception;
        }

        if ($result !== 'deleted') {
            $messages = [
                'unauthorized' => 'Δεν έχεις δικαίωμα διαγραφής.',
                'not_found' => 'Ο διαχειριστής δεν βρέθηκε.',
                'self' => 'Δεν μπορείς να διαγράψεις τον λογαριασμό σου.',
                'last_admin' => 'Δεν μπορεί να διαγραφεί ο τελευταίος ενεργός Admin.',
                'has_history' => 'Ο διαχειριστής έχει ιστορικές καταγραφές. Χρησιμοποίησε την απενεργοποίηση.'
            ];

            $this->fail(
                $messages[$result] ?? 'Η διαγραφή απέτυχε.'
            );
        }

        Session::flash(
            'admin_manage_success',
            'Ο διαχειριστής διαγράφηκε οριστικά.'
        );

        $this->redirect();
    }


    private function fail(string $message): never
    {
        Session::flash('admin_manage_error', $message);
        $this->redirect();
    }

    private function redirect(): never
    {
        header(
            'Location: /admin/administrators',
            true,
            303
        );
        exit;
    }
}
