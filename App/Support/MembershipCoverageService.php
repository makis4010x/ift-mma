<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Framework\Database;

final class MembershipCoverageService
{
    public const MAX_MONTHS = 12;

    /**
     * Call after AdminGuard and CSRF validation.
     *
     * This method starts its own transaction.
     * Do not wrap it in another Database::transaction().
     *
     * @return array{
     *     payment_id: int,
     *     start: string,
     *     end: string,
     *     repeated: bool
     * }
     */
    public static function register(
        Database $db,
        int $studentId,
        int $membershipId,
        int $adminId,
        int $months,
        int $amountCents,
        string $paidOn,
        string $method,
        string $notes,
        string $requestKey,
        bool $specialAmount = false
    ): array {
        self::validDate($paidOn);

        $today = (
            new DateTimeImmutable(
                'today',
                new DateTimeZone('Europe/Athens')
            )
        )->format('Y-m-d');

        $notes = trim($notes);

        if (
            $studentId < 1 ||
            $membershipId < 1 ||
            $adminId < 1 ||
            $months < 1 ||
            $months > self::MAX_MONTHS ||
            $amountCents < 1 ||
            $amountCents > 9999999999 ||
            $paidOn > $today ||
            !in_array(
                $method,
                ['cash', 'card', 'bank_transfer'],
                true
            ) ||
            preg_match('/\A.{0,255}\z/us', $notes) !== 1 ||
            preg_match(
                '/\A[a-f0-9]{32}\z/D',
                $requestKey
            ) !== 1
        ) {
            throw new DomainException(
                'Μη έγκυρα στοιχεία πληρωμής.'
            );
        }

        return $db->transaction(
            static function (Database $db) use (
                $studentId,
                $membershipId,
                $adminId,
                $months,
                $amountCents,
                $paidOn,
                $method,
                $notes,
                $requestKey,
                $specialAmount
            ): array {

                // Same locking order as payment cancellation:
                // lock the student first.
                $student = $db->query(
                    'SELECT id
                     FROM students
                     WHERE id = :id
                     FOR UPDATE',
                    ['id' => $studentId]
                )->fetch();

                if (!is_array($student)) {
                    throw new DomainException(
                        'Ο μαθητής δεν βρέθηκε.'
                    );
                }

                // Re-submitting the same form must not
                // create another receipt.
                $old = $db->query(
                    'SELECT
                        p.id,
                        p.student_id,
                        p.amount,
                        p.paid_on,
                        p.method,
                        p.notes,
                        p.status,
                        c.membership_id,
                        c.months_count,
                        c.coverage_start,
                        c.coverage_end
                     FROM student_payments p
                     LEFT JOIN membership_payment_coverage c
                        ON c.payment_id = p.id
                     WHERE p.request_key = :request_key
                     FOR UPDATE',
                    ['request_key' => $requestKey]
                )->fetch();

                if (is_array($old)) {
                    $sameSubmission =
                        $old['status'] === 'posted'
                        && (int) $old['student_id'] === $studentId
                        && (int) $old['membership_id'] === $membershipId
                        && (int) $old['months_count'] === $months
                        && self::cents(
                            (string) $old['amount']
                        ) === $amountCents
                        && $old['paid_on'] === $paidOn
                        && $old['method'] === $method
                        && (string) ($old['notes'] ?? '') === $notes;

                    if (!$sameSubmission) {
                        throw new DomainException(
                            'Η φόρμα έχει ήδη χρησιμοποιηθεί. '
                                . 'Ανανέωσε τη σελίδα.'
                        );
                    }

                    return [
                        'payment_id' => (int) $old['id'],
                        'start' => (string) $old['coverage_start'],
                        'end' => (string) $old['coverage_end'],
                        'repeated' => true
                    ];
                }

                // Price comes from the student's agreement,
                // never from the general price of the plan.
                $membership = $db->query(
                    'SELECT
                        id,
                        valid_from,
                        valid_to,
                        agreed_monthly_fee,
                        currency
                     FROM student_memberships
                     WHERE id = :membership
                       AND student_id = :student
                     FOR UPDATE',
                    [
                        'membership' => $membershipId,
                        'student' => $studentId
                    ]
                )->fetch();

                if (
                    !is_array($membership) ||
                    $membership['currency'] !== 'EUR'
                ) {
                    throw new DomainException(
                        'Επίλεξε συμφωνία του μαθητή σε EUR.'
                    );
                }

                $fee = self::cents(
                    (string) $membership['agreed_monthly_fee']
                );

                if ($fee <= 0) {
                    throw new DomainException(
                        'Απαιτείται θετική συμφωνημένη τιμή μαθητή.'
                    );
                }

                if (
                    $amountCents !== $fee * $months &&
                    (
                        !$specialAmount ||
                        preg_match(
                            '/\A.{5,255}\z/us',
                            $notes
                        ) !== 1
                    )
                ) {
                    throw new DomainException(
                        'Το ποσό δεν αντιστοιχεί στους μήνες. '
                            . 'Επίλεξε «Ειδικό ποσό» και αιτιολογία.'
                    );
                }

                // Do not silently lose posted money from
                // the previous partial-payment workflow.
                $legacyIssues = (int) $db->query(
                    "SELECT COUNT(*)
                     FROM student_payments p
                     WHERE p.student_id = :student
                       AND p.status = 'posted'
                       AND NOT EXISTS (
                           SELECT 1
                           FROM membership_payment_coverage x
                           WHERE x.payment_id = p.id
                       )
                       AND (
                           p.currency <> 'EUR'
                           OR p.amount <> COALESCE((
                               SELECT SUM(a.applied_amount)
                               FROM payment_allocations a
                               WHERE a.payment_id = p.id
                           ), 0)
                       )",
                    ['student' => $studentId]
                )->fetchColumn();

                if ($legacyIssues > 0) {
                    throw new DomainException(
                        'Υπάρχουν παλιές μη πλήρως αντιστοιχισμένες '
                            . 'πληρωμές. Χρειάζεται έλεγχος, όχι διαγραφή.'
                    );
                }

                // Old periods count only when fully paid
                // with receipts that have not been voided.
                $legacy = $db->query(
                    "SELECT
                        c.membership_id,
                        c.period_start,
                        c.period_end,
                        c.amount,
                        c.currency,
                        sm.valid_from,
                        r.anchor_date,
                        r.cycle_index,
                        COALESCE((
                            SELECT SUM(a.applied_amount)
                            FROM payment_allocations a
                            JOIN student_payments p
                                ON p.id = a.payment_id
                            WHERE a.charge_id = c.id
                              AND p.status = 'posted'
                        ), 0) AS paid_amount
                     FROM membership_charges c
                     JOIN student_memberships sm
                        ON sm.id = c.membership_id
                     LEFT JOIN membership_renewal_events r
                        ON r.charge_id = c.id
                     WHERE c.student_id = :student",
                    ['student' => $studentId]
                )->fetchAll();

                $periods = [];

                foreach ($legacy as $row) {
                    $paid = self::cents(
                        (string) $row['paid_amount']
                    );

                    if ($paid === 0) {
                        // Unpaid or cancelled old periods do
                        // not absorb the new payment.
                        continue;
                    }

                    if (
                        $row['currency'] !== 'EUR' ||
                        $paid !== self::cents(
                            (string) $row['amount']
                        )
                    ) {
                        throw new DomainException(
                            'Υπάρχει παλιά μερική ή ασύμφωνη '
                                . 'εξόφληση. Χρειάζεται έλεγχος.'
                        );
                    }

                    $anchor = (string) (
                        $row['anchor_date']
                        ?? $row['valid_from']
                    );

                    $index = $row['anchor_date'] !== null
                        ? (int) $row['cycle_index'] + 1
                        : self::monthDistance(
                            $anchor,
                            (string) $row['period_end']
                        );

                    if (
                        $index < 0 ||
                        self::monthBoundary(
                            $anchor,
                            $index
                        ) !== $row['period_end']
                    ) {
                        $anchor = (string) $row['period_end'];
                        $index = 0;
                    }

                    $periods[] = [
                        'start' => (string) $row['period_start'],
                        'end' => (string) $row['period_end'],
                        'membership_id' => (int) $row['membership_id'],
                        'anchor' => $anchor,
                        'index' => $index
                    ];
                }

                // New coverage is valid only while its
                // associated payment remains posted.
                $newPeriods = $db->query(
                    "SELECT
                        c.coverage_start,
                        c.coverage_end,
                        c.membership_id,
                        c.anchor_date,
                        c.cycle_index_end
                     FROM membership_payment_coverage c
                     JOIN student_payments p
                        ON p.id = c.payment_id
                     WHERE c.student_id = :student
                       AND p.status = 'posted'",
                    ['student' => $studentId]
                )->fetchAll();

                foreach ($newPeriods as $row) {
                    $periods[] = [
                        'start' => (string) $row['coverage_start'],
                        'end' => (string) $row['coverage_end'],
                        'membership_id' => (int) $row['membership_id'],
                        'anchor' => (string) $row['anchor_date'],
                        'index' => (int) $row['cycle_index_end']
                    ];
                }

                $cursor = self::renewalCursor(
                    $periods,
                    (string) $membership['valid_from'],
                    $paidOn
                );

                $expected = $cursor['expected'];
                $previous = $cursor['previous'];

                $anchor = $expected;
                $index = 0;

                if (
                    $previous !== null &&
                    $previous['end'] === $expected &&
                    $previous['membership_id'] === $membershipId &&
                    self::monthBoundary(
                        $previous['anchor'],
                        $previous['index']
                    ) === $expected
                ) {
                    $anchor = $previous['anchor'];
                    $index = $previous['index'];
                }

                // Attendance on the payment day itself does
                // not backdate a student's return.
                $dates = $paidOn > $expected
                    ? self::attendanceDates(
                        $db,
                        $studentId,
                        $expected,
                        $paidOn
                    )
                    : [];

                $decision = self::decideCoverage(
                    $expected,
                    $paidOn,
                    $dates,
                    self::monthBoundary($anchor, $index + 1)
                );

                $start = $decision['start'];

                if ($start !== $expected) {
                    $anchor = $start;
                    $index = 0;
                }

                $endIndex = $index + $months;
                $end = self::monthBoundary($anchor, $endIndex);

                if (
                    $start < $membership['valid_from'] ||
                    (
                        $membership['valid_to'] !== null &&
                        $end > $membership['valid_to']
                    )
                ) {
                    throw new DomainException(
                        'Η συμφωνία δεν καλύπτει όλη τη νέα περίοδο.'
                    );
                }

                foreach ($periods as $period) {
                    if (
                        $period['start'] < $end &&
                        $period['end'] > $start
                    ) {
                        throw new DomainException(
                            'Η νέα περίοδος επικαλύπτει υπάρχουσα '
                                . 'κάλυψη. Χρειάζεται έλεγχος.'
                        );
                    }
                }

                $note = match ($decision['decision']) {
                    'advance' =>
                    'Πληρωμή επόμενων μηνών. '
                        . 'Συνέχεια της υπάρχουσας κάλυψης.',

                    'on_time' =>
                    'Ανανέωση στην προβλεπόμενη ημερομηνία.',

                    'attended' =>
                    'Υπήρχαν παρουσίες πριν την ημέρα πληρωμής: '
                        . implode(', ', $dates)
                        . '. Διατηρήθηκε η αρχική ανανέωση.',

                    default =>
                    'Χωρίς παρουσίες στις προηγούμενες '
                        . 'ακάλυπτες ημέρες. Νέα έναρξη από την πληρωμή.'
                };

                $note .= ' Πληρωμή: ' . $paidOn
                    . '. Κάλυψη: [' . $start . ', ' . $end . ').';

                if ($amountCents !== $fee * $months) {
                    $note .= ' Ειδικό ποσό: ' . $notes;
                }

                // Record the receipt.
                $db->query(
                    "INSERT INTO student_payments (
                        student_id,
                        amount,
                        currency,
                        paid_on,
                        recorded_at_utc,
                        method,
                        notes,
                        recorded_by_admin_id,
                        request_key
                     ) VALUES (
                        :student,
                        :amount,
                        'EUR',
                        :paid_on,
                        UTC_TIMESTAMP(6),
                        :method,
                        :notes,
                        :admin,
                        :request_key
                     )",
                    [
                        'student' => $studentId,
                        'amount' => self::money($amountCents),
                        'paid_on' => $paidOn,
                        'method' => $method,
                        'notes' => $notes === '' ? null : $notes,
                        'admin' => $adminId,
                        'request_key' => $requestKey
                    ]
                );

                $paymentId = (int) $db->query(
                    'SELECT LAST_INSERT_ID()'
                )->fetchColumn();

                // Record the period purchased by this receipt.
                // No new membership_charges or allocations.
                $db->query(
                    'INSERT INTO membership_payment_coverage (
                        student_id,
                        membership_id,
                        payment_id,
                        coverage_start,
                        coverage_end,
                        expected_start,
                        anchor_date,
                        cycle_index_end,
                        months_count,
                        agreed_monthly_fee,
                        paid_amount,
                        decision,
                        attendance_dates,
                        automatic_note,
                        created_at_utc
                     ) VALUES (
                        :student,
                        :membership,
                        :payment,
                        :start,
                        :end,
                        :expected,
                        :anchor,
                        :cycle,
                        :months,
                        :fee,
                        :amount,
                        :decision,
                        :attendance,
                        :note,
                        UTC_TIMESTAMP(6)
                     )',
                    [
                        'student' => $studentId,
                        'membership' => $membershipId,
                        'payment' => $paymentId,
                        'start' => $start,
                        'end' => $end,
                        'expected' => $expected,
                        'anchor' => $anchor,
                        'cycle' => $endIndex,
                        'months' => $months,
                        'fee' => self::money($fee),
                        'amount' => self::money($amountCents),
                        'decision' => $decision['decision'],
                        'attendance' => $dates === []
                            ? null
                            : implode(', ', $dates),
                        'note' => $note
                    ]
                );

                return [
                    'payment_id' => $paymentId,
                    'start' => $start,
                    'end' => $end,
                    'repeated' => false
                ];
            }
        );
    }

    /**
     * Determine the next renewal boundary.
     * Supply posted, fully paid periods only.
     */
    public static function renewalCursor(
        array $periods,
        string $from,
        string $paidOn
    ): array {
        self::validDate($from);
        self::validDate($paidOn);

        usort(
            $periods,
            static fn(array $a, array $b): int =>
            [$a['start'], $a['end']]
                <=> [$b['start'], $b['end']]
        );

        $reference = max($from, $paidOn);
        $previous = null;
        $lastEnd = null;

        foreach ($periods as $period) {
            self::validDate($period['start']);
            self::validDate($period['end']);

            if (
                $period['end'] <= $period['start'] ||
                (
                    $lastEnd !== null &&
                    $period['start'] < $lastEnd
                )
            ) {
                throw new DomainException(
                    'Ασυμφωνία ή επικάλυψη υπάρχουσας κάλυψης.'
                );
            }

            $lastEnd = $period['end'];

            if (
                $period['start'] <= $reference ||
                (
                    $previous !== null &&
                    $previous['end'] > $reference &&
                    $period['start'] === $previous['end']
                )
            ) {
                $previous = $period;
            }
        }

        return [
            'expected' => max(
                $from,
                $previous['end'] ?? $from
            ),
            'previous' => $previous
        ];
    }

    public static function decideCoverage(
        string $expected,
        string $paidOn,
        array $attendanceDates,
        ?string $nextBoundary = null
    ): array {
        self::validDate($expected);
        self::validDate($paidOn);

        if ($paidOn < $expected) {
            return [
                'start' => $expected,
                'decision' => 'advance'
            ];
        }

        if ($paidOn === $expected) {
            return [
                'start' => $expected,
                'decision' => 'on_time'
            ];
        }

        $dates = [];

        foreach ($attendanceDates as $date) {
            self::validDate($date);

            if ($expected <= $date && $date < $paidOn) {
                $dates[$date] = true;
            }
        }

        $limit = $nextBoundary
            ?? self::monthBoundary($expected, 1);

        if ($dates !== []) {
            if ($paidOn >= $limit) {
                throw new DomainException(
                    'Υπάρχουν παρουσίες σε μεγάλο κενό. '
                        . 'Χρειάζεται χειροκίνητος έλεγχος.'
                );
            }

            return [
                'start' => $expected,
                'decision' => 'attended'
            ];
        }

        return [
            'start' => $paidOn,
            'decision' => $paidOn >= $limit
                ? 'restart'
                : 'shifted'
        ];
    }

    public static function monthBoundary(
        string $anchor,
        int $months
    ): string {
        self::validDate($anchor);

        if ($months < 0 || $months > 120000) {
            throw new DomainException(
                'Μη έγκυρος αριθμός μηνών.'
            );
        }

        $first = new DateTimeImmutable(
            substr($anchor, 0, 7) . '-01',
            new DateTimeZone('UTC')
        );

        $target = $first->modify(
            '+' . $months . ' months'
        );

        if ((int) $target->format('Y') > 9999) {
            throw new DomainException(
                'Η ημερομηνία υπερβαίνει το επιτρεπτό όριο.'
            );
        }

        $day = min(
            (int) substr($anchor, 8, 2),
            (int) $target->format('t')
        );

        return $target->format('Y-m-')
            . sprintf('%02d', $day);
    }

    /**
     * Convert an amount to integer cents without floating-point maths.
     */
    public static function cents(string $raw): int
    {
        $value = str_replace(',', '.', trim($raw));

        if (
            preg_match(
                '/\A[0-9]{1,8}(?:\.[0-9]{1,2})?\z/D',
                $value
            ) !== 1
        ) {
            throw new DomainException(
                'Μη έγκυρο χρηματικό ποσό.'
            );
        }

        $parts = explode('.', $value, 2);

        return (int) $parts[0] * 100
            + (int) str_pad(
                $parts[1] ?? '',
                2,
                '0'
            );
    }

    private static function money(int $cents): string
    {
        return intdiv($cents, 100)
            . '.'
            . sprintf('%02d', $cents % 100);
    }

    private static function validDate(string $value): void
    {
        if (
            preg_match(
                '/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}\z/D',
                $value
            ) !== 1
        ) {
            throw new DomainException(
                'Μη έγκυρη ημερομηνία.'
            );
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value,
            new DateTimeZone('UTC')
        );

        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new DomainException(
                'Μη έγκυρη ημερομηνία.'
            );
        }
    }

    private static function monthDistance(
        string $from,
        string $to
    ): int {
        return (
            (int) substr($to, 0, 4)
            - (int) substr($from, 0, 4)
        ) * 12
            + (int) substr($to, 5, 2)
            - (int) substr($from, 5, 2);
    }

    private static function attendanceDates(
        Database $db,
        int $studentId,
        string $from,
        string $paidOn
    ): array {
        $athens = new DateTimeZone('Europe/Athens');
        $utc = new DateTimeZone('UTC');

        $begin = (
            new DateTimeImmutable(
                $from . ' 00:00:00',
                $athens
            )
        )->setTimezone($utc);

        $end = (
            new DateTimeImmutable(
                $paidOn . ' 00:00:00',
                $athens
            )
        )->setTimezone($utc);

        $rows = $db->query(
            'SELECT attended_at_utc
             FROM attendances
             WHERE student_id = :student
               AND attended_at_utc >= :begin
               AND attended_at_utc < :end
             ORDER BY attended_at_utc
             LIMIT 501',
            [
                'student' => $studentId,
                'begin' => $begin->format('Y-m-d H:i:s'),
                'end' => $end->format('Y-m-d H:i:s')
            ]
        )->fetchAll();

        if (count($rows) > 500) {
            throw new DomainException(
                'Πάρα πολλές παρουσίες για αυτόματο έλεγχο.'
            );
        }

        $dates = [];

        foreach ($rows as $row) {
            $date = (
                new DateTimeImmutable(
                    (string) $row['attended_at_utc'],
                    $utc
                )
            )->setTimezone($athens)->format('Y-m-d');

            $dates[$date] = true;
        }

        return array_keys($dates);
    }
}
