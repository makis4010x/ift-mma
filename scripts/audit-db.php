<?php

declare(strict_types=1);

use Framework\Config;
use Framework\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli' || Config::get('app.environment') !== 'local') {
    exit("Local CLI only.\n");
}

try {
    $db = new Database(Config::get('database'));

    $count = static function (string $sql) use ($db): int {
        return (int) $db->query($sql)->fetchColumn();
    };

    $requiredTables = [
        'schema_migrations',
        'students',
        'users',
        'student_barcodes',
        'attendances',
        'student_billing_settings',
        'training_programs',
        'membership_plans',
        'membership_plan_programs',
        'student_memberships',
        'student_enrollments',
        'legacy_billing_snapshots',
        'import_batches',
        'import_issues',
    ];

    echo "=== IFT ACADEMY / DATABASE AUDIT ===\n\n";

    foreach ($requiredTables as $table) {
        $exists = (int) $db->query(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :name',
            ['name' => $table]
        )->fetchColumn();

        if ($exists !== 1) {
            throw new RuntimeException("Missing table: {$table}");
        }
    }

    echo '[OK] All ' . count($requiredTables) . " required tables exist.\n\n";

    $statistics = [
        'Applied migrations' => 'SELECT COUNT(*) FROM schema_migrations',
        'Students' => 'SELECT COUNT(*) FROM students',
        'Imported students (legacy IDs)' =>
            'SELECT COUNT(*) FROM students WHERE legacy_id IS NOT NULL',
        'Student barcodes' => 'SELECT COUNT(*) FROM student_barcodes',
        'Attendance records' => 'SELECT COUNT(*) FROM attendances',
        'Imported attendance records' =>
            "SELECT COUNT(*) FROM attendances WHERE source = 'import'",
        'Legacy billing snapshots' =>
            'SELECT COUNT(*) FROM legacy_billing_snapshots',
        'Current billing-day settings' =>
            'SELECT COUNT(*) FROM student_billing_settings',
        'Historical memberships' =>
            'SELECT COUNT(*) FROM student_memberships',
    ];

    echo "STATISTICS\n";
    $totals = [];
    foreach ($statistics as $label => $sql) {
        $totals[$label] = $count($sql);
        echo "  {$label}: {$totals[$label]}\n";
    }

    $checks = [
        'Deprecated billing amount columns still present' => '
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = \'student_billing_settings\'
              AND COLUMN_NAME IN (\'monthly_fee\', \'currency\')',
        'Students without barcode' => '
            SELECT COUNT(*) FROM students s
            LEFT JOIN student_barcodes b ON b.student_id = s.id
            WHERE b.id IS NULL',
        'Orphan attendance records' => '
            SELECT COUNT(*) FROM attendances a
            LEFT JOIN students s ON s.id = a.student_id
            WHERE s.id IS NULL',
        'Imported attendance without legacy ID' => "
            SELECT COUNT(*) FROM attendances
            WHERE source = 'import' AND legacy_id IS NULL",
        'Duplicate attendance legacy IDs' => '
            SELECT COUNT(*) FROM (
                SELECT legacy_id FROM attendances
                WHERE legacy_id IS NOT NULL
                GROUP BY legacy_id HAVING COUNT(*) > 1
            ) duplicates',
        'Imported students without billing snapshot' => '
            SELECT COUNT(*) FROM students s
            LEFT JOIN legacy_billing_snapshots b ON b.student_id = s.id
            WHERE s.legacy_id IS NOT NULL AND b.id IS NULL',
        'Orphan legacy billing snapshots' => '
            SELECT COUNT(*) FROM legacy_billing_snapshots b
            LEFT JOIN students s ON s.id = b.student_id
            WHERE s.id IS NULL',
        'Overlapping membership periods' => '
            SELECT COUNT(*) FROM student_memberships a
            JOIN student_memberships b
              ON a.student_id = b.student_id AND a.id < b.id
             AND a.valid_from < COALESCE(b.valid_to, \'9999-12-31\')
             AND b.valid_from < COALESCE(a.valid_to, \'9999-12-31\')',
        'Overlapping enrollment periods' => '
            SELECT COUNT(*) FROM student_enrollments a
            JOIN student_enrollments b
              ON a.student_id = b.student_id
             AND a.program_id = b.program_id AND a.id < b.id
             AND a.valid_from < COALESCE(b.valid_to, \'9999-12-31\')
             AND b.valid_from < COALESCE(a.valid_to, \'9999-12-31\')',
    ];

    echo "\nINTEGRITY CHECKS\n";
    $issues = 0;
    foreach ($checks as $label => $sql) {
        $found = $count($sql);
        $flag = $found === 0 ? 'OK' : 'ISSUE';
        if ($found > 0) {
            $issues++;
        }
        echo "  [{$flag}] {$label}: {$found}\n";
    }

    $reviews = [
        'Email values shared by multiple students' => '
            SELECT COUNT(*) FROM (
                SELECT LOWER(TRIM(email)) AS email_key
                FROM students
                WHERE email IS NOT NULL AND TRIM(email) <> \'\'
                GROUP BY LOWER(TRIM(email))
                HAVING COUNT(*) > 1
            ) repeated',
        'Same-student identical attendance timestamps' => '
            SELECT COUNT(*) FROM (
                SELECT student_id, attended_at_utc
                FROM attendances
                GROUP BY student_id, attended_at_utc
                HAVING COUNT(*) > 1
            ) repeated',
    ];

    echo "\nMANUAL REVIEW (not necessarily errors)\n";
    foreach ($reviews as $label => $sql) {
        echo "  [REVIEW] {$label}: " . $count($sql) . "\n";
    }

    echo "\n";
    if ($totals['Applied migrations'] < 14) {
        $issues++;
        echo "[ISSUE] Fewer than 14 migrations recorded.\n";
    }
    if ($totals['Imported students (legacy IDs)'] === 0) {
        $issues++;
        echo "[ISSUE] No CSV students found.\n";
    }
    if ($totals['Imported attendance records'] === 0) {
        $issues++;
        echo "[ISSUE] No historical attendances found.\n";
    }

    echo $issues === 0
        ? "AUDIT PASSED: no structural problems found.\n"
        : "AUDIT NEEDS REVIEW: {$issues} issue(s).\n";

    exit($issues === 0 ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, 'Audit failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
