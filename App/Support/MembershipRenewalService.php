<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Framework\Database;

final class MembershipRenewalService
{
    public static function register(
        Database $db,
        int $studentId,
        int $adminId,
        int $newCents,
        string $paidOn,
        string $method,
        string $notes,
        string $requestKey
    ): int {
        $student = $db->query(
            'SELECT id FROM students WHERE id = :id FOR UPDATE',
            ['id' => $studentId]
        )->fetch();

        if (!is_array($student)) {
            throw new DomainException('Ο μαθητής δεν βρέθηκε.');
        }

        $agreements = $db->query(
            'SELECT id, valid_from, valid_to,
                    agreed_monthly_fee, currency
             FROM student_memberships
             WHERE student_id = :id
             ORDER BY valid_from, id',
            ['id' => $studentId]
        )->fetchAll();

        if ($agreements === []) {
            throw new DomainException(
                'Πρώτα ανάθεσε συνδρομή στον μαθητή.'
            );
        }

        $charges = $db->query(
            "SELECT c.id, c.membership_id,
                    c.period_start, c.period_end,
                    c.amount, c.currency,
                    COALESCE((
                        SELECT SUM(a.applied_amount)
                        FROM payment_allocations a
                        JOIN student_payments p
                            ON p.id = a.payment_id
                        WHERE a.charge_id = c.id
                          AND p.status = 'posted'
                    ), 0) AS paid_amount
             FROM membership_charges c
             WHERE c.student_id = :id
             ORDER BY c.period_start, c.id
             FOR UPDATE",
            ['id' => $studentId]
        )->fetchAll();

        // Real receipt, protected against duplicate submission.
        $db->query(
            "INSERT INTO student_payments
                (student_id, amount, currency, paid_on,
                 recorded_at_utc, method, notes,
                 recorded_by_admin_id, request_key)
             VALUES
                (:student, :amount, 'EUR', :paid_on,
                 UTC_TIMESTAMP(6), :method, :notes,
                 :admin, :request_key)",
            [
                'student' => $studentId,
                'amount' => self::money($newCents),
                'paid_on' => $paidOn,
                'method' => $method,
                'notes' => $notes === '' ? null : $notes,
                'admin' => $adminId,
                'request_key' => $requestKey,
            ]
        );

        $paymentId = (int) $db->query(
            'SELECT LAST_INSERT_ID()'
        )->fetchColumn();

        // Find all posted, currently unallocated payments.
        $payments = $db->query(
            "SELECT p.id, p.amount,
                    COALESCE((
                        SELECT SUM(a.applied_amount)
                        FROM payment_allocations a
                        WHERE a.payment_id = p.id
                    ), 0) AS allocated
             FROM student_payments p
             WHERE p.student_id = :id
               AND p.status = 'posted'
             ORDER BY p.paid_on, p.id",
            ['id' => $studentId]
        )->fetchAll();

        $credits = [];

        foreach ($payments as $payment) {
            $available =
                self::cents((string) $payment['amount'])
                - self::cents((string) $payment['allocated']);

            if ($available < 0) {
                throw new DomainException(
                    'Ασυμφωνία στις προηγούμενες πληρωμές.'
                );
            }

            if ($available > 0) {
                $credits[] = [
                    'id' => (int) $payment['id'],
                    'available' => $available
                ];
            }
        }

        // Preserve and settle previously recorded partial charges.
        foreach ($charges as $charge) {
            $outstanding =
                self::cents((string) $charge['amount'])
                - self::cents((string) $charge['paid_amount']);

            if ($outstanding < 0) {
                throw new DomainException(
                    'Ασυμφωνία σε προηγούμενη χρέωση.'
                );
            }

            if ($charge['currency'] !== 'EUR') {
                throw new DomainException(
                    'Εντοπίστηκε διαφορετικό νόμισμα.'
                );
            }

            if ($outstanding > 0) {
                self::allocateCredits(
                    $db,
                    $credits,
                    (int) $charge['id'],
                    $outstanding
                );

                if ($outstanding > 0) {
                    return $paymentId;
                }
            }
        }

        $previous = $charges === []
            ? null
            : $charges[array_key_last($charges)];

        $expected = $previous === null
            ? null
            : (string) $previous['period_end'];

        $anchor = null;
        $nextIndex = 0;

        // Preserve original day of renewal through short months.
        if ($previous !== null) {
            $event = $db->query(
                'SELECT anchor_date, cycle_index
                 FROM membership_renewal_events
                 WHERE charge_id = :id
                 LIMIT 1',
                ['id' => (int) $previous['id']]
            )->fetch();

            if (is_array($event)) {
                $anchor = (string) $event['anchor_date'];
                $nextIndex = (int) $event['cycle_index'] + 1;
            }
        }

        if ($expected === null) {
            $initialAgreement = null;

            foreach ($agreements as $agreement) {
                if (
                    $agreement['valid_from'] <= $paidOn
                    && (
                        $agreement['valid_to'] === null
                        || $paidOn < $agreement['valid_to']
                    )
                ) {
                    $initialAgreement = $agreement;
                    break;
                }

                if ($agreement['valid_from'] > $paidOn) {
                    $initialAgreement = $agreement;
                    break;
                }
            }

            if ($initialAgreement === null) {
                throw new DomainException(
                    'Δεν υπάρχει ισχύουσα συμφωνία συνδρομής.'
                );
            }

            $expected = (string) $initialAgreement['valid_from'];
        }

        // Cover up to 12 monthly periods in one transaction.
        $created = 0;

        for ($i = 0; $i < 12; $i++) {
            $totalCredit = array_sum(
                array_column($credits, 'available')
            );

            if ($totalCredit <= 0) {
                break;
            }

            $decision = self::decide(
                $db,
                $studentId,
                $expected,
                $paidOn
            );

            $start = $decision['start'];

            $agreement = self::agreementFor(
                $agreements,
                $start
            );

            if ($agreement === null) {
                throw new DomainException(
                    'Απαιτείται νέα συμφωνία για την περίοδο ανανέωσης.'
                );
            }

            if ($agreement['currency'] !== 'EUR') {
                throw new DomainException(
                    'Υποστηρίζονται μόνο συμφωνίες σε EUR.'
                );
            }

            $fee = self::cents(
                (string) $agreement['agreed_monthly_fee']
            );

            if ($fee <= 0) {
                throw new DomainException(
                    'Η συμφωνημένη μηνιαία τιμή πρέπει να είναι θετική.'
                );
            }

            if ($totalCredit < $fee) {
                // Partial receipt, not yet a covered month.
                break;
            }

            $newAnchor = $anchor;
            $newIndex = $nextIndex;

            if (
                $newAnchor === null
                || $start !== $expected
                || (
                    $previous !== null
                    && (int) $previous['membership_id']
                    !== (int) $agreement['id']
                )
            ) {
                $newAnchor = $start;
                $newIndex = 0;
            }

            $end = self::boundary(
                $newAnchor,
                $newIndex + 1
            );

            if (
                $agreement['valid_to'] !== null
                && $end > $agreement['valid_to']
            ) {
                throw new DomainException(
                    'Η συμφωνία λήγει πριν ολοκληρωθεί ο επόμενος μήνας.'
                );
            }

            $db->query(
                "INSERT INTO membership_charges
                    (student_id, membership_id,
                     period_start, period_end, due_on,
                     amount, currency, created_at_utc)
                 VALUES
                    (:student, :membership,
                     :start, :end, :start,
                     :amount, 'EUR', UTC_TIMESTAMP(6))",
                [
                    'student' => $studentId,
                    'membership' => (int) $agreement['id'],
                    'start' => $start,
                    'end' => $end,
                    'amount' => self::money($fee)
                ]
            );

            $chargeId = (int) $db->query(
                'SELECT LAST_INSERT_ID()'
            )->fetchColumn();

            self::allocateCredits(
                $db,
                $credits,
                $chargeId,
                $fee
            );

            // Save the decision and the attendance-based note.
            $db->query(
                "INSERT INTO membership_renewal_events
                    (charge_id, completed_by_payment_id,
                     expected_start, coverage_start,
                     coverage_end, anchor_date,
                     cycle_index, paid_on, decision,
                     attendance_count, automatic_note,
                     created_at_utc)
                 VALUES
                    (:charge, :payment,
                     :expected, :start, :end, :anchor,
                     :cycle, :paid_on, :decision,
                     :attendance, :note, UTC_TIMESTAMP(6))",
                [
                    'charge' => $chargeId,
                    'payment' => $paymentId,
                    'expected' => $expected,
                    'start' => $start,
                    'end' => $end,
                    'anchor' => $newAnchor,
                    'cycle' => $newIndex,
                    'paid_on' => $paidOn,
                    'decision' => $decision['kind'],
                    'attendance' => $decision['count'],
                    'note' => $decision['note']
                ]
            );

            $created++;

            $previous = [
                'id' => $chargeId,
                'membership_id' => $agreement['id']
            ];

            $expected = $end;
            $anchor = $newAnchor;
            $nextIndex = $newIndex + 1;
        }

        if (
            $created === 12
            && array_sum(array_column($credits, 'available')) > 0
        ) {
            throw new DomainException(
                'Η πληρωμή υπερβαίνει το όριο των 12 ανανεώσεων.'
            );
        }

        // Remaining money stays as advance credit.
        return $paymentId;
    }

    private static function allocateCredits(
        Database $db,
        array &$credits,
        int $chargeId,
        int &$needed
    ): void {
        foreach ($credits as &$payment) {
            if ($needed === 0) {
                break;
            }

            if ($payment['available'] <= 0) {
                continue;
            }

            $apply = min(
                $needed,
                $payment['available']
            );

            $db->query(
                "INSERT INTO payment_allocations
                    (payment_id, charge_id,
                     applied_amount, allocated_at_utc)
                 VALUES
                    (:payment, :charge,
                     :amount, UTC_TIMESTAMP(6))
                 ON DUPLICATE KEY UPDATE
                    applied_amount =
                        applied_amount + VALUES(applied_amount)",
                [
                    'payment' => $payment['id'],
                    'charge' => $chargeId,
                    'amount' => self::money($apply)
                ]
            );

            $payment['available'] -= $apply;
            $needed -= $apply;
        }

        unset($payment);
    }

    private static function decide(
        Database $db,
        int $studentId,
        string $expected,
        string $paidOn
    ): array {
        if ($paidOn < $expected) {
            return [
                'start' => $expected,
                'kind' => 'advance',
                'count' => 0,
                'note' => 'Προπληρωμή. Διατηρήθηκε η ημερομηνία ανανέωσης.'
            ];
        }

        if ($paidOn === $expected) {
            return [
                'start' => $expected,
                'kind' => 'on_time',
                'count' => 0,
                'note' => 'Πληρωμή στην ημερομηνία ανανέωσης.'
            ];
        }

        $dates = self::attendedDates(
            $db,
            $studentId,
            $expected,
            $paidOn
        );

        if (
            $paidOn >= self::boundary($expected, 1)
            && $dates !== []
        ) {
            throw new DomainException(
                'Υπάρχουν παρουσίες σε κενό μεγαλύτερο από έναν μήνα. ' .
                    'Απαιτείται χειροκίνητος έλεγχος.'
            );
        }

        if ($dates !== []) {
            return [
                'start' => $expected,
                'kind' => 'attended',
                'count' => count($dates),
                'note' => 'Πληρώθηκε στις ' . $paidOn .
                    '. Υπήρχαν παρουσίες στις ' .
                    implode(', ', $dates) .
                    '. Διατηρήθηκε η ανανέωση από ' .
                    $expected . '.'
            ];
        }

        return [
            'start' => $paidOn,
            'kind' => $paidOn >= self::boundary($expected, 1)
                ? 'restart'
                : 'shifted',
            'count' => 0,
            'note' => 'Δεν υπήρχαν παρουσίες από ' .
                $expected . ' έως ' . $paidOn .
                '. Νέα έναρξη από την ημέρα πληρωμής.'
        ];
    }

    private static function attendedDates(
        Database $db,
        int $studentId,
        string $from,
        string $until
    ): array {
        $zone = new DateTimeZone('Europe/Athens');
        $utc = new DateTimeZone('UTC');

        $fromUtc = (
            new DateTimeImmutable(
                $from . ' 00:00:00',
                $zone
            )
        )->setTimezone($utc)->format('Y-m-d H:i:s');

        $toUtc = (
            new DateTimeImmutable(
                $until . ' 00:00:00',
                $zone
            )
        )->modify('+1 day')
            ->setTimezone($utc)
            ->format('Y-m-d H:i:s');

        $rows = $db->query(
            'SELECT attended_at_utc
             FROM attendances
             WHERE student_id = :student
               AND attended_at_utc >= :start
               AND attended_at_utc < :end
             ORDER BY attended_at_utc
             LIMIT 501',
            [
                'student' => $studentId,
                'start' => $fromUtc,
                'end' => $toUtc
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
            )->setTimezone($zone)->format('Y-m-d');

            $dates[$date] = true;
        }

        return array_keys($dates);
    }

    private static function agreementFor(
        array $agreements,
        string $day
    ): ?array {
        foreach ($agreements as $agreement) {
            if (
                $agreement['valid_from'] <= $day
                && (
                    $agreement['valid_to'] === null
                    || $day < $agreement['valid_to']
                )
            ) {
                return $agreement;
            }
        }

        return null;
    }

    private static function boundary(
        string $anchor,
        int $months
    ): string {
        $base = new DateTimeImmutable(
            substr($anchor, 0, 7) . '-01'
        );

        $target = $base->modify(
            '+' . $months . ' months'
        );

        $day = min(
            (int) substr($anchor, 8, 2),
            (int) $target->format('t')
        );

        return $target->format('Y-m-')
            . sprintf('%02d', $day);
    }

    private static function cents(string $amount): int
    {
        $parts = explode('.', $amount, 2);

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
}
