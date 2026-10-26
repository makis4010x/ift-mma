<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\AdminGuard;
use DateTimeImmutable;
use DateTimeZone;
use Framework\Config;
use Framework\Database;

final class AdminAttendanceController
{
    public function index(): void
    {
        $admin = AdminGuard::requireAdmin();

        $db = new Database(Config::get('database'));

        // Filters
        $rawSearch = $_GET['q'] ?? '';
        $rawDate = $_GET['date'] ?? '';

        $search = is_string($rawSearch)
            ? trim($rawSearch)
            : '';

        $date = is_string($rawDate)
            ? trim($rawDate)
            : '';

        if (strlen($search) > 150) {
            http_response_code(400);
            exit('Invalid search query.');
        }

        $athens = new DateTimeZone('Europe/Athens');
        $utc = new DateTimeZone('UTC');

        $selectedDate = null;

        if ($date !== '') {
            $selectedDate = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $date,
                $athens
            );

            if (
                $selectedDate === false ||
                $selectedDate->format('Y-m-d') !== $date
            ) {
                http_response_code(400);
                exit('Invalid date.');
            }
        }

        $conditions = [];
        $params = [];

        // Search by student name or email.
        if ($search !== '') {
            $conditions[] = "(
                s.first_name LIKE :first_name
                OR s.last_name LIKE :last_name
                OR CONCAT(
                    s.first_name, ' ', s.last_name
                ) LIKE :full_name
                OR s.email LIKE :email
            )";

            $term = '%' . $search . '%';

            $params['first_name'] = $term;
            $params['last_name'] = $term;
            $params['full_name'] = $term;
            $params['email'] = $term;
        }

        // Filter by Greek local date, stored as UTC.
        if ($selectedDate !== null) {
            $params['start_utc'] = $selectedDate
                ->setTimezone($utc)
                ->format('Y-m-d H:i:s');

            $params['end_utc'] = $selectedDate
                ->modify('+1 day')
                ->setTimezone($utc)
                ->format('Y-m-d H:i:s');

            $conditions[] = "
                a.attended_at_utc >= :start_utc
                AND a.attended_at_utc < :end_utc
            ";
        }

        $where = $conditions === []
            ? ''
            : ' WHERE ' . implode(' AND ', $conditions);

        // Total historical records, without filters.
        $totalAttendances = (int) $db->query(
            'SELECT COUNT(*) FROM attendances'
        )->fetchColumn();


        // Today's attendance count in Greek local time.
        $todayStart = new DateTimeImmutable(
            'today',
            $athens
        );

        $todayStartUtc = $todayStart
            ->setTimezone($utc)
            ->format('Y-m-d H:i:s');

        $tomorrowStartUtc = $todayStart
            ->modify('+1 day')
            ->setTimezone($utc)
            ->format('Y-m-d H:i:s');

        $todayAttendances = (int) $db->query(
            "SELECT COUNT(*)
             FROM attendances
             WHERE attended_at_utc >= :start_utc
               AND attended_at_utc < :end_utc",
            [
                'start_utc' => $todayStartUtc,
                'end_utc' => $tomorrowStartUtc
            ]
        )->fetchColumn();


        // Total filtered records.
        $totalResults = (int) $db->query(
            "SELECT COUNT(*)
             FROM attendances a
             INNER JOIN students s
                 ON s.id = a.student_id
             {$where}",
            $params
        )->fetchColumn();

        // Pagination
        $perPage = 25;
        $totalPages = max(
            1,
            (int) ceil($totalResults / $perPage)
        );

        $rawPage = $_GET['page'] ?? '1';

        $page = 1;

        if (
            is_string($rawPage) &&
            ctype_digit($rawPage)
        ) {
            $parsedPage = filter_var(
                $rawPage,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($parsedPage !== false) {
                $page = min($parsedPage, $totalPages);
            }
        }

        $offset = ($page - 1) * $perPage;

        $attendances = $db->query(
            "SELECT
                a.id,
                a.student_id,
                a.attended_at_utc,
                a.source,
                s.first_name,
                s.last_name
             FROM attendances a
             INNER JOIN students s
                 ON s.id = a.student_id
             {$where}
             ORDER BY a.attended_at_utc DESC, a.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        )->fetchAll();

        loadView('admin-header', [
            'adminPage' => 'attendance',
            'adminEmail' => $admin['email'],
            'attendances' => $attendances,
            'totalAttendances' => $totalAttendances,
            'todayAttendances' => $todayAttendances,
            'totalResults' => $totalResults,
            'search' => $search,
            'dateFilter' => $date,
            'page' => $page,
            'totalPages' => $totalPages
        ]);
    }
}
