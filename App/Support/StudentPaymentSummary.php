<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use Framework\Database;

final class StudentPaymentSummary
{
    public static function load(
        Database $db,
        int $studentId
    ): array {
        // These are the student's agreements, not the general price list.
        $agreements = $db->query(
            "SELECT
                sm.id,
                sm.valid_from,
                sm.valid_to,
                sm.agreed_monthly_fee,
                sm.currency,
                mp.name AS plan_name
             FROM student_memberships sm
             JOIN membership_plans mp
                ON mp.id = sm.plan_id
             WHERE sm.student_id = :student
               AND sm.currency = 'EUR'
               AND sm.agreed_monthly_fee > 0
             ORDER BY sm.valid_from DESC, sm.id DESC",
            ['student' => $studentId]
        )->fetchAll();

        $history = $db->query(
            "SELECT
                p.id,
                p.amount,
                p.currency,
                p.paid_on,
                p.method,
                p.status,
                p.notes,
                p.void_reason,
                p.voided_at_utc,
                p.voided_by_admin_id,
                c.id AS coverage_id,
                c.coverage_start,
                c.coverage_end,
                c.months_count,
                c.agreed_monthly_fee,
                c.automatic_note,
                mp.name AS plan_name,
                COALESCE((
                    SELECT SUM(a.applied_amount)
                    FROM payment_allocations a
                    WHERE a.payment_id = p.id
                ), 0) AS allocated_amount
             FROM student_payments p
             LEFT JOIN membership_payment_coverage c
                ON c.payment_id = p.id
             LEFT JOIN student_memberships sm
                ON sm.id = c.membership_id
             LEFT JOIN membership_plans mp
                ON mp.id = sm.plan_id
             WHERE p.student_id = :student
             ORDER BY p.paid_on DESC, p.id DESC",
            ['student' => $studentId]
        )->fetchAll();

        $legacy = $db->query(
            "SELECT
                c.period_start,
                c.period_end,
                c.amount,
                c.currency,
                COALESCE((
                    SELECT SUM(a.applied_amount)
                    FROM payment_allocations a
                    JOIN student_payments p
                        ON p.id = a.payment_id
                    WHERE a.charge_id = c.id
                      AND p.status = 'posted'
                ), 0) AS paid_amount
             FROM membership_charges c
             WHERE c.student_id = :student
             ORDER BY c.period_start, c.id",
            ['student' => $studentId]
        )->fetchAll();

        $review = false;
        $periods = [];
        $lastPaid = null;

        foreach ($history as $payment) {
            if ($payment['status'] !== 'posted') {
                continue;
            }

            $lastPaid ??= $payment['paid_on'];

            if ($payment['coverage_id'] !== null) {
                $periods[] = [
                    'start' => $payment['coverage_start'],
                    'end' => $payment['coverage_end']
                ];
            } elseif (
                $payment['currency'] !== 'EUR' ||
                MembershipCoverageService::cents(
                    (string) $payment['amount']
                ) !== MembershipCoverageService::cents(
                    (string) $payment['allocated_amount']
                )
            ) {
                // Existing money must not disappear during migration.
                $review = true;
            }
        }

        foreach ($legacy as $row) {
            $paid = MembershipCoverageService::cents(
                (string) $row['paid_amount']
            );

            if ($paid === 0) {
                continue;
            }

            if (
                $row['currency'] !== 'EUR' ||
                $paid !== MembershipCoverageService::cents(
                    (string) $row['amount']
                )
            ) {
                $review = true;
                continue;
            }

            $periods[] = [
                'start' => $row['period_start'],
                'end' => $row['period_end']
            ];
        }

        usort(
            $periods,
            static fn(array $a, array $b): int =>
            [$a['start'], $a['end']]
                <=> [$b['start'], $b['end']]
        );

        $today = (
            new DateTimeImmutable(
                'today',
                new DateTimeZone('Europe/Athens')
            )
        )->format('Y-m-d');

        $coveredUntil = null;
        $lastEnd = null;
        $futureFrom = null;
        $previousEnd = null;

        foreach ($periods as $period) {
            $start = (string) $period['start'];
            $end = (string) $period['end'];

            if (
                $end <= $start ||
                ($previousEnd !== null && $start < $previousEnd)
            ) {
                $review = true;
            }

            $previousEnd = max(
                $previousEnd ?? $end,
                $end
            );

            if ($end <= $today) {
                $lastEnd = max($lastEnd ?? $end, $end);
            }

            if ($start > $today) {
                $futureFrom = min(
                    $futureFrom ?? $start,
                    $start
                );
            }

            // Do not bridge gaps left by cancelled payments.
            if ($start <= $today && $today < $end) {
                $coveredUntil = max(
                    $coveredUntil ?? $end,
                    $end
                );
            } elseif (
                $coveredUntil !== null &&
                $start === $coveredUntil
            ) {
                $coveredUntil = $end;
            }
        }

        return [
            'agreements' => $agreements,
            'history' => $history,
            'legacy' => $legacy,
            'last_paid_on' => $lastPaid,
            'covered_until' => $review ? null : $coveredUntil,
            'last_end' => $lastEnd,
            'future_from' => $futureFrom,
            'review' => $review
        ];
    }
}
