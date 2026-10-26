<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\AdminGuard;
use DateTimeImmutable;
use Framework\Config;
use Framework\Csrf;
use Framework\Database;
use Framework\Session;
use PDOException;

final class AdminStudentMembershipsController
{
    public function store(array $params): void
    {
        AdminGuard::requireAdmin();
        Csrf::protect();

        $studentId = filter_var(
            $params['id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($studentId === false) {
            http_response_code(404);
            exit('Ο μαθητής δεν βρέθηκε.');
        }

        $rawPlanId = $_POST['plan_id'] ?? null;

        $planId = filter_var(
            $rawPlanId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $from = $_POST['valid_from'] ?? null;
        $rawTo = $_POST['valid_to'] ?? '';
        $rawFee = $_POST['agreed_monthly_fee'] ?? null;

        $old = [
            'plan_id' => is_scalar($rawPlanId)
                ? (string) $rawPlanId
                : '',
            'valid_from' => is_string($from) ? $from : '',
            'valid_to' => is_string($rawTo) ? $rawTo : '',
            'agreed_monthly_fee' => is_string($rawFee)
                ? $rawFee
                : '',
        ];

        if ($planId === false) {
            $this->fail(
                $studentId,
                $old,
                'Επίλεξε πρόγραμμα συνδρομής.'
            );
        }

        if (!$this->validDate($from)) {
            $this->fail(
                $studentId,
                $old,
                'Η ημερομηνία έναρξης δεν είναι έγκυρη.'
            );
        }

        if (!is_string($rawTo)) {
            $this->fail(
                $studentId,
                $old,
                'Η ημερομηνία λήξης δεν είναι έγκυρη.'
            );
        }

        $to = trim($rawTo) === ''
            ? null
            : trim($rawTo);

        if (
            $to !== null &&
            (!$this->validDate($to) || $to <= $from)
        ) {
            $this->fail(
                $studentId,
                $old,
                'Η λήξη πρέπει να είναι μετά την έναρξη. Η ημέρα λήξης δεν περιλαμβάνεται.'
            );
        }

        if (!is_string($rawFee)) {
            $this->fail(
                $studentId,
                $old,
                'Συμπλήρωσε συμφωνημένο μηνιαίο ποσό.'
            );
        }

        $fee = str_replace(
            ',',
            '.',
            trim($rawFee)
        );

        if (
            preg_match(
                '/\A(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?\z/D',
                $fee
            ) !== 1
        ) {
            $this->fail(
                $studentId,
                $old,
                'Το ποσό πρέπει να είναι έγκυρο, π.χ. 45,00.'
            );
        }

        $fee = number_format(
            (float) $fee,
            2,
            '.',
            ''
        );

        $db = new Database(Config::get('database'));

        try {
            $result = $db->transaction(
                static function (Database $db) use (
                    $studentId,
                    $planId,
                    $fee,
                    $from,
                    $to
                ): string {

                    // Lock the student to prevent concurrent
                    // membership assignments.
                    $student = $db->query(
                        'SELECT id
                         FROM students
                         WHERE id = :id
                         FOR UPDATE',
                        ['id' => $studentId]
                    )->fetch();

                    if (!is_array($student)) {
                        return 'student_missing';
                    }

                    // Only active plans are assignable.
                    $plan = $db->query(
                        'SELECT id, currency
                         FROM membership_plans
                         WHERE id = :id
                           AND is_active = 1
                         FOR UPDATE',
                        ['id' => $planId]
                    )->fetch();

                    if (!is_array($plan)) {
                        return 'plan_unavailable';
                    }

                    // Intervals: [valid_from, valid_to)
                    // A null end date means no defined end.
                    $overlapSql = '
                        SELECT id
                        FROM student_memberships
                        WHERE student_id = :student_id
                          AND (
                              valid_to IS NULL
                              OR valid_to > :new_from
                          )
                    ';

                    $overlapParams = [
                        'student_id' => $studentId,
                        'new_from' => $from
                    ];

                    if ($to !== null) {
                        $overlapSql .= '
                            AND valid_from < :new_to
                        ';

                        $overlapParams['new_to'] = $to;
                    }

                    $overlapSql .= ' LIMIT 1';

                    $existing = $db->query(
                        $overlapSql,
                        $overlapParams
                    )->fetch();

                    if ($existing) {
                        return 'overlap';
                    }

                    // Store a fixed agreed price for this
                    // student and this membership period.
                    $db->query(
                        'INSERT INTO student_memberships
                            (
                                student_id,
                                plan_id,
                                agreed_monthly_fee,
                                currency,
                                valid_from,
                                valid_to
                            )
                         VALUES
                            (
                                :student_id,
                                :plan_id,
                                :fee,
                                :currency,
                                :valid_from,
                                :valid_to
                            )',
                        [
                            'student_id' => $studentId,
                            'plan_id' => $planId,
                            'fee' => $fee,
                            'currency' => $plan['currency'],
                            'valid_from' => $from,
                            'valid_to' => $to
                        ]
                    );

                    return 'saved';
                }
            );
        } catch (PDOException $exception) {
            if (
                (int) ($exception->errorInfo[1] ?? 0)
                === 1062
            ) {
                $this->fail(
                    $studentId,
                    $old,
                    'Υπάρχει ήδη συνδρομή με την ίδια ημερομηνία έναρξης.'
                );
            }

            throw $exception;
        }

        if ($result === 'student_missing') {
            http_response_code(404);
            exit('Ο μαθητής δεν βρέθηκε.');
        }

        if ($result === 'plan_unavailable') {
            $this->fail(
                $studentId,
                $old,
                'Το πρόγραμμα δεν είναι πλέον ενεργό ή δεν υπάρχει.'
            );
        }

        if ($result === 'overlap') {
            $this->fail(
                $studentId,
                $old,
                'Υπάρχει ήδη συνδρομή που καλύπτει μέρος αυτής της περιόδου. Δεν έγινε καταχώριση.'
            );
        }

        Session::flash(
            'student_membership_success',
            'Η συνδρομή καταχωρίστηκε. Η συμφωνημένη τιμή αποθηκεύτηκε ανεξάρτητα από τον τιμοκατάλογο.'
        );

        $this->redirect($studentId);
    }

    private function validDate(mixed $value): bool
    {
        if (
            !is_string($value) ||
            preg_match(
                '/\A\d{4}-\d{2}-\d{2}\z/D',
                $value
            ) !== 1
        ) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value
        );

        return $date !== false
            && $date->format('Y-m-d') === $value;
    }

    private function fail(
        int $studentId,
        array $old,
        string $message
    ): never {
        Session::flash(
            'student_membership_error',
            $message
        );

        Session::flash(
            'student_membership_old',
            $old
        );

        $this->redirect($studentId);
    }

    private function redirect(int $studentId): never
    {
        header(
            'Location: /admin/students/' .
                $studentId .
                '?tab=memberships#student-tabs',
            true,
            303
        );

        exit;
    }
}
