<?php

declare(strict_types=1);

use App\Support\StudentActivityService;
use Framework\Config;
use Framework\Database;

if (PHP_SAPI !== 'cli') {
    exit('CLI only.');
}

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    if (Config::get('app.environment') !== 'local') {
        throw new RuntimeException(
            'This script is for local development only.'
        );
    }

    $db = new Database(Config::get('database'));

    if (
        $db->query('SELECT DATABASE()')->fetchColumn()
        !== 'ift_academy'
    ) {
        throw new RuntimeException('Unexpected database.');
    }

    // Όλες οι ημερομηνίες αναφέρονται στην Ελλάδα.
    $zone = new DateTimeZone('Europe/Athens');
    $utc = new DateTimeZone('UTC');

    $today = new DateTimeImmutable('today', $zone);
    $tomorrow = $today->modify('+1 day');

    $monthStart = $today->modify('first day of this month');
    $nextMonth = $monthStart->modify('+1 month');

    // Πραγματικός κανόνας κατάστασης μαθητών.
    $statusSource = StudentActivityService::source($db);

    $statusRows = $db->query(
        "SELECT activity_status, COUNT(*) AS students
         FROM ({$statusSource['sql']}) activity
         GROUP BY activity_status",
        $statusSource['params']
    )->fetchAll();

    $statuses = [
        'active' => 0,
        'inactive' => 0,
        'review' => 0,
        'no_payment' => 0
    ];

    foreach ($statusRows as $row) {
        $statuses[(string) $row['activity_status']] =
            (int) $row['students'];
    }

    // Παρουσίες σήμερα.
    // Τα timestamps αποθηκεύονται σε UTC.
    $dayAttendance = $db->query(
        'SELECT COUNT(*)
         FROM attendances
         WHERE attended_at_utc >= :start_utc
           AND attended_at_utc < :end_utc',
        [
            'start_utc' => $today
                ->setTimezone($utc)
                ->format('Y-m-d H:i:s'),

            'end_utc' => $tomorrow
                ->setTimezone($utc)
                ->format('Y-m-d H:i:s')
        ]
    )->fetchColumn();

    // Παρουσίες τρέχοντος μήνα.
    $monthAttendance = $db->query(
        'SELECT COUNT(*)
         FROM attendances
         WHERE attended_at_utc >= :start_utc
           AND attended_at_utc < :end_utc',
        [
            'start_utc' => $monthStart
                ->setTimezone($utc)
                ->format('Y-m-d H:i:s'),

            'end_utc' => $nextMonth
                ->setTimezone($utc)
                ->format('Y-m-d H:i:s')
        ]
    )->fetchColumn();

    // Μόνο πραγματικές καταχωρισμένες πληρωμές.
    // Δεν συμπεριλαμβάνουμε τα legacy archives.
    $revenue = $db->query(
        "SELECT
            COUNT(*) AS posted_payments,
            COALESCE(SUM(amount), 0) AS revenue_eur

         FROM student_payments

         WHERE status = 'posted'
           AND amount > 0
           AND paid_on >= :from_date
           AND paid_on < :to_date",
        [
            'from_date' => $monthStart->format('Y-m-d'),
            'to_date' => $nextMonth->format('Y-m-d')
        ]
    )->fetch();

    $result = [
        'result' => 'READ_ONLY_DASHBOARD_AUDIT',

        'as_of_athens' => $today->format('Y-m-d'),
        'month' => $monthStart->format('Y-m'),

        'students_by_activity' => $statuses,

        'today_attendances' => (int) $dayAttendance,
        'month_attendances' => (int) $monthAttendance,

        'month_posted_payments' =>
        (int) $revenue['posted_payments'],

        'month_revenue_eur' =>
        (string) $revenue['revenue_eur'],

        'historical_archive_in_revenue' => false
    ];

    echo json_encode(
        $result,
        JSON_PRETTY_PRINT
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(
        STDERR,
        '[STOP] ' . $error->getMessage() . PHP_EOL
    );

    exit(1);
}
