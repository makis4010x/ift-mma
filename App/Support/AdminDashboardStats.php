<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use Framework\Database;

final class AdminDashboardStats
{
    public static function get(Database $db): array
    {
        // Ίδιος κανόνας με τη λίστα μαθητών.
        $source = StudentActivityService::source($db);

        $rows = $db->query(
            "SELECT activity_status, COUNT(*) AS total
             FROM ({$source['sql']}) activity
             GROUP BY activity_status",
            $source['params']
        )->fetchAll();

        $counts = [
            'active' => 0,
            'inactive' => 0,
            'review' => 0,
            'no_payment' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) $row['activity_status'];

            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row['total'];
            }
        }

        $athens = new DateTimeZone('Europe/Athens');
        $utc = new DateTimeZone('UTC');

        $today = new DateTimeImmutable('today', $athens);
        $tomorrow = $today->modify('+1 day');

        $monthStart = $today->modify(
            'first day of this month'
        );

        $nextMonth = $monthStart->modify('+1 month');

        $attendanceCount = static function (
            DateTimeImmutable $from,
            DateTimeImmutable $to
        ) use ($db, $utc): int {
            return (int) $db->query(
                'SELECT COUNT(*)
                 FROM attendances
                 WHERE attended_at_utc >= :start_utc
                   AND attended_at_utc < :end_utc',
                [
                    'start_utc' => $from
                        ->setTimezone($utc)
                        ->format('Y-m-d H:i:s'),

                    'end_utc' => $to
                        ->setTimezone($utc)
                        ->format('Y-m-d H:i:s'),
                ]
            )->fetchColumn();
        };

        // Μόνο πραγματικές πληρωμές νέας εφαρμογής.
        // Δεν προσμετράμε legacy archives.
        $payments = $db->query(
            "SELECT
                COUNT(*) AS payment_count,
                COALESCE(SUM(amount), 0) AS revenue_eur

             FROM student_payments

             WHERE status = 'posted'
               AND amount > 0
               AND paid_on >= :start_date
               AND paid_on < :end_date",
            [
                'start_date' => $monthStart->format('Y-m-d'),
                'end_date' => $nextMonth->format('Y-m-d'),
            ]
        )->fetch();

        return [
            'as_of' => $today->format('d/m/Y'),
            'month' => $monthStart->format('m/Y'),

            'total_students' => array_sum($counts),
            'active_students' => $counts['active'],
            'inactive_students' => $counts['inactive'],
            'review_students' => $counts['review'],
            'no_payment_students' => $counts['no_payment'],

            'today_attendances' => $attendanceCount(
                $today,
                $tomorrow
            ),

            'month_attendances' => $attendanceCount(
                $monthStart,
                $nextMonth
            ),

            'month_posted_payments' =>
            (int) $payments['payment_count'],

            'month_revenue_eur' =>
            (string) $payments['revenue_eur'],
        ];
    }
}
