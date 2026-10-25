<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\AdminGuard;
use DateTimeImmutable;
use DateTimeZone;
use Framework\Config;
use Framework\Database;

class AdminController
{
    public function index(): void
    {
        $admin = AdminGuard::requireAdmin();

        $db = new Database(Config::get('database'));

        // Total registered students.
        $totalStudents = (int) $db->query(
            "SELECT COUNT(*) FROM students"
        )->fetchColumn();

        // Today's date in Greece.
        $athens = new DateTimeZone('Europe/Athens');
        $utc = new DateTimeZone('UTC');

        $todayStart = new DateTimeImmutable(
            'today',
            $athens
        );

        $tomorrowStart = $todayStart->modify('+1 day');

        // Convert Greek local day boundaries to UTC.
        $startUtc = $todayStart
            ->setTimezone($utc)
            ->format('Y-m-d H:i:s');

        $endUtc = $tomorrowStart
            ->setTimezone($utc)
            ->format('Y-m-d H:i:s');

        // Today's attendance records.
        $todayAttendances = (int) $db->query(
            "SELECT COUNT(*)
             FROM attendances
             WHERE attended_at_utc >= :start_utc
               AND attended_at_utc < :end_utc",
            [
                'start_utc' => $startUtc,
                'end_utc' => $endUtc
            ]
        )->fetchColumn();

        loadView('admin-header', [
            'adminPage' => 'dashboard',
            'adminEmail' => $admin['email'],
            'totalStudents' => $totalStudents,
            'todayAttendances' => $todayAttendances
        ]);
    }
}
