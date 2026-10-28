<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\AdminGuard;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Framework\Config;
use Framework\Csrf;
use Framework\Database;
use Framework\Session;
use PDOException;

final class AdminStudentActivityController
{
    public function update(array $params): void
    {
        $admin = AdminGuard::requireAdmin();
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

        try {
            foreach (
                [
                    'mode',
                    'active_until',
                    'reason',
                    'expected_event_id',
                    'request_key'
                ] as $field
            ) {
                if (!is_string($_POST[$field] ?? null)) {
                    throw new DomainException(
                        'Μη έγκυρα στοιχεία φόρμας.'
                    );
                }
            }

            $mode = $_POST['mode'];
            $reason = trim($_POST['reason']);
            $key = $_POST['request_key'];

            $expected = filter_var(
                $_POST['expected_event_id'],
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0]]
            );

            if (
                !in_array($mode, ['auto', 'active'], true) ||
                $expected === false ||
                preg_match('/\A.{5,255}\z/us', $reason) !== 1 ||
                preg_match('/\A[a-f0-9]{32}\z/D', $key) !== 1
            ) {
                throw new DomainException(
                    'Επίλεξε τρόπο κατάστασης και γράψε '
                        . 'αιτιολογία 5–255 χαρακτήρων.'
                );
            }

            $zone = new DateTimeZone('Europe/Athens');

            $today = (
                new DateTimeImmutable('today', $zone)
            )->format('Y-m-d');

            $until = $mode === 'active'
                ? trim($_POST['active_until'])
                : '';

            if ($until !== '') {
                if (
                    preg_match(
                        '/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}\z/D',
                        $until
                    ) !== 1
                ) {
                    throw new DomainException(
                        'Μη έγκυρη ημερομηνία λήξης.'
                    );
                }

                $date = DateTimeImmutable::createFromFormat(
                    '!Y-m-d',
                    $until,
                    $zone
                );

                if (
                    !$date ||
                    $date->format('Y-m-d') !== $until ||
                    $until < $today
                ) {
                    throw new DomainException(
                        'Η λήξη πρέπει να είναι έγκυρη ημερομηνία '
                            . 'από σήμερα και μετά.'
                    );
                }
            }

            $until = $until === '' ? null : $until;

            $db = new Database(
                Config::get('database')
            );

            $repeated = $db->transaction(
                static function (Database $db) use (
                    $studentId,
                    $admin,
                    $mode,
                    $reason,
                    $key,
                    $expected,
                    $today,
                    $until
                ): bool {
                    $student = $db->query(
                        'SELECT id
                         FROM students
                         WHERE id = :id
                         FOR UPDATE',

                        ['id' => $studentId]
                    )->fetch();

                    if (!is_array($student)) {
                        throw new DomainException(
                            'Ο μαθητής δεν υπάρχει.'
                        );
                    }

                    // A repeated submission must not create another event.
                    $previousRequest = $db->query(
                        'SELECT
                            student_id,
                            mode,
                            reason,
                            active_until

                         FROM student_activity_overrides

                         WHERE request_key = :key
                         FOR UPDATE',

                        ['key' => $key]
                    )->fetch();

                    if (is_array($previousRequest)) {
                        if (
                            (int) $previousRequest['student_id'] !== $studentId ||
                            $previousRequest['mode'] !== $mode ||
                            $previousRequest['reason'] !== $reason ||
                            $previousRequest['active_until'] !== $until
                        ) {
                            throw new DomainException(
                                'Η φόρμα έχει χρησιμοποιηθεί. '
                                    . 'Ανανέωσε τη σελίδα.'
                            );
                        }

                        return true;
                    }

                    $latest = $db->query(
                        'SELECT id
                         FROM student_activity_overrides
                         WHERE student_id = :student
                         ORDER BY id DESC
                         LIMIT 1
                         FOR UPDATE',

                        ['student' => $studentId]
                    )->fetch();

                    // Do not silently overwrite a change from another tab/admin.
                    if (
                        (int) ($latest['id'] ?? 0) !== $expected
                    ) {
                        throw new DomainException(
                            'Η επιλογή άλλαξε από άλλη καταχώριση. '
                                . 'Έλεγξε τη νέα κατάσταση και ξαναδοκίμασε.'
                        );
                    }

                    // Append an audit event; never erase previous decisions.
                    $db->query(
                        'INSERT INTO student_activity_overrides (
                            student_id,
                            mode,
                            starts_on,
                            active_until,
                            reason,
                            changed_by_admin_id,
                            admin_email_snapshot,
                            changed_at_utc,
                            request_key
                         ) VALUES (
                            :student,
                            :mode,
                            :today,
                            :until,
                            :reason,
                            :admin,
                            :admin_email,
                            UTC_TIMESTAMP(6),
                            :key
                         )',

                        [
                            'student' => $studentId,
                            'mode' => $mode,
                            'today' => $today,
                            'until' => $until,
                            'reason' => $reason,
                            'admin' => (int) $admin['id'],
                            'admin_email' => (string) $admin['email'],
                            'key' => $key
                        ]
                    );

                    return false;
                }
            );

            Session::flash(
                'activity_success_' . $studentId,

                $repeated
                    ? 'Αυτή η ενέργεια είχε ήδη καταχωριστεί. '
                    . 'Δεν επαναλήφθηκε.'
                    : 'Η επιλογή κατάστασης αποθηκεύτηκε.'
            );
        } catch (DomainException $exception) {
            Session::flash(
                'activity_error_' . $studentId,
                $exception->getMessage()
            );
        } catch (PDOException $exception) {
            if (
                !in_array(
                    (int) ($exception->errorInfo[1] ?? 0),
                    [1062, 1205, 1213],
                    true
                )
            ) {
                throw $exception;
            }

            Session::flash(
                'activity_error_' . $studentId,
                'Υπήρξε ταυτόχρονη αλλαγή. '
                    . 'Έλεγξε την κατάσταση πριν επαναλάβεις.'
            );
        }

        header(
            'Location: /admin/students/'
                . $studentId
                . '#student-activity',
            true,
            303
        );

        exit;
    }
}
