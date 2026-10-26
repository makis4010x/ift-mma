<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\AdminGuard;
use App\Support\MembershipCoverageService;
use DomainException;
use Framework\Config;
use Framework\Csrf;
use Framework\Database;
use Framework\Session;
use PDOException;

final class AdminStudentPaymentsController
{
    public function store(array $params): void
    {
        $admin = AdminGuard::requireAdmin();
        Csrf::protect();

        $studentId = $this->studentId($params);

        // Preserve form values after validation errors.
        $old = [];

        foreach (
            [
                'membership_id',
                'months',
                'amount',
                'paid_on',
                'method',
                'notes',
                'request_key',
                'special_amount'
            ] as $field
        ) {
            $value = $_POST[$field] ?? '';

            $old[$field] =
                is_string($value) && strlen($value) <= 1024
                ? $value
                : '';
        }

        try {
            $membershipId = $this->positiveId(
                $_POST['membership_id'] ?? null
            );

            $months = $this->positiveId(
                $_POST['months'] ?? null
            );

            if ($membershipId === null || $months === null) {
                throw new DomainException(
                    'Επίλεξε συμφωνία και μήνες κάλυψης.'
                );
            }

            foreach (
                [
                    'amount',
                    'paid_on',
                    'method',
                    'notes',
                    'request_key'
                ] as $field
            ) {
                $default = $field === 'notes' ? '' : null;

                if (!is_string($_POST[$field] ?? $default)) {
                    throw new DomainException(
                        'Μη έγκυρα στοιχεία φόρμας.'
                    );
                }
            }

            $special = $_POST['special_amount'] ?? '0';

            if (!in_array($special, ['0', '1'], true)) {
                throw new DomainException(
                    'Μη έγκυρη επιλογή ειδικού ποσού.'
                );
            }

            $db = new Database(Config::get('database'));

            // The service manages its own transaction.
            $result = MembershipCoverageService::register(
                $db,
                $studentId,
                $membershipId,
                (int) $admin['id'],
                $months,
                MembershipCoverageService::cents(
                    $_POST['amount']
                ),
                $_POST['paid_on'],
                $_POST['method'],
                $_POST['notes'] ?? '',
                $_POST['request_key'],
                $special === '1'
            );
        } catch (DomainException $exception) {
            Session::flash(
                'payment_old_' . $studentId,
                $old
            );

            $this->fail(
                $studentId,
                $exception->getMessage()
            );
        } catch (PDOException $exception) {
            $code = (int) ($exception->errorInfo[1] ?? 0);

            if (in_array($code, [1062, 1205, 1213], true)) {
                Session::flash(
                    'payment_old_' . $studentId,
                    $old
                );

                $this->fail(
                    $studentId,
                    'Η καταχώριση δεν ολοκληρώθηκε λόγω σύγκρουσης. '
                        . 'Έλεγξε το ιστορικό πριν επαναλάβεις.'
                );
            }

            throw $exception;
        }

        // Clear any remaining old form values.
        Session::getFlash('payment_old_' . $studentId);

        Session::flash(
            'student_payment_success',
            $result['repeated']
                ? 'Η ίδια πληρωμή είχε ήδη καταχωριστεί. '
                . 'Δεν δημιουργήθηκε δεύτερη.'
                : 'Η πληρωμή καταχωρίστηκε. Κάλυψη από '
                . $result['start']
                . ' έως πριν από '
                . $result['end']
                . '.'
        );

        $this->redirect($studentId);
    }

    public function voidPayment(array $params): void
    {
        $admin = AdminGuard::requireAdmin();
        Csrf::protect();

        $studentId = $this->studentId($params);

        $paymentId = $this->positiveId(
            $_POST['payment_id'] ?? null
        );

        $rawReason = $_POST['void_reason'] ?? null;

        if ($paymentId === null || !is_string($rawReason)) {
            $this->fail(
                $studentId,
                'Μη έγκυρα στοιχεία ακύρωσης.'
            );
        }

        $reason = trim($rawReason);

        if (preg_match('/\A.{5,255}\z/us', $reason) !== 1) {
            $this->fail(
                $studentId,
                'Η αιτιολογία πρέπει να έχει 5–255 χαρακτήρες.'
            );
        }

        $db = new Database(Config::get('database'));

        $result = $db->transaction(
            static function (Database $db) use (
                $studentId,
                $paymentId,
                $admin,
                $reason
            ): string {
                // Same locking order as payment creation.
                $student = $db->query(
                    'SELECT id
                     FROM students
                     WHERE id = :id
                     FOR UPDATE',
                    ['id' => $studentId]
                )->fetch();

                if (!is_array($student)) {
                    return 'missing';
                }

                $payment = $db->query(
                    'SELECT status
                     FROM student_payments
                     WHERE id = :payment
                       AND student_id = :student
                     FOR UPDATE',
                    [
                        'payment' => $paymentId,
                        'student' => $studentId
                    ]
                )->fetch();

                if (!is_array($payment)) {
                    return 'missing';
                }

                if ($payment['status'] === 'void') {
                    return 'already_void';
                }

                if ($payment['status'] !== 'posted') {
                    return 'invalid';
                }

                $db->query(
                    "UPDATE student_payments
                     SET status = 'void',
                         voided_at_utc = UTC_TIMESTAMP(6),
                         voided_by_admin_id = :admin,
                         void_reason = :reason
                     WHERE id = :payment
                       AND student_id = :student
                       AND status = 'posted'",
                    [
                        'admin' => (int) $admin['id'],
                        'reason' => $reason,
                        'payment' => $paymentId,
                        'student' => $studentId
                    ]
                );

                // Keep coverage and allocations as historical records.
                return 'voided';
            }
        );

        if (!in_array($result, ['voided', 'already_void'], true)) {
            $this->fail(
                $studentId,
                'Η πληρωμή δεν βρέθηκε ή δεν μπορεί να ακυρωθεί.'
            );
        }

        Session::flash(
            'student_payment_success',
            $result === 'already_void'
                ? 'Η πληρωμή έχει ήδη ακυρωθεί.'
                : 'Η πληρωμή ακυρώθηκε. Το ιστορικό παρέμεινε, '
                . 'χωρίς νέα οφειλή.'
        );

        $this->redirect($studentId);
    }

    private function positiveId(mixed $value): ?int
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $id = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        return $id === false ? null : $id;
    }

    private function studentId(array $params): int
    {
        $id = $this->positiveId($params['id'] ?? null);

        if ($id === null) {
            http_response_code(404);
            exit('Ο μαθητής δεν βρέθηκε.');
        }

        return $id;
    }

    private function fail(
        int $studentId,
        string $message
    ): never {
        Session::flash(
            'student_payment_error',
            $message
        );

        $this->redirect($studentId);
    }

    private function redirect(int $studentId): never
    {
        header(
            'Location: /admin/students/'
                . $studentId
                . '?tab=memberships#student-tabs',
            true,
            303
        );

        exit;
    }
}
