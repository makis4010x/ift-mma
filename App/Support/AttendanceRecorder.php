<?php

declare(strict_types=1);

namespace App\Support;

use Framework\Config;
use Framework\Database;

final class AttendanceRecorder
{
    public const DUPLICATE_MINUTES = 90;

    public static function recordScan(
        string $rawBarcode,
        string $source = 'scanner'
    ): string {
        if (!in_array($source, ['scanner', 'manual'], true)) {
            return 'invalid';
        }
        $barcode = strtoupper(trim($rawBarcode));

        if (
            strlen($barcode) > 32 ||
            preg_match(
                '/\AIFT[0-9]{6,}\z/',
                $barcode
            ) !== 1
        ) {
            return 'invalid';
        }

        $db = new Database(Config::get('database'));

        return $db->transaction(
            static function (Database $db) use (
                $barcode,
                $source
            ): string {

                $row = $db->query(
                    "SELECT student_id
                     FROM student_barcodes
                     WHERE code = :code
                     LIMIT 1",
                    ['code' => $barcode]
                )->fetch();

                if (!is_array($row)) {
                    return 'unknown';
                }

                return self::recordForStudent(
                    $db,
                    (int) $row['student_id'],
                    $source
                );
            }
        );
    }

    public static function recordManual(
        int $studentId
    ): string {
        if ($studentId < 1) {
            return 'invalid';
        }

        $db = new Database(Config::get('database'));

        return $db->transaction(
            static fn(Database $db): string =>
            self::recordForStudent(
                $db,
                $studentId,
                'manual'
            )
        );
    }

    private static function recordForStudent(
        Database $db,
        int $studentId,
        string $source
    ): string {

        // Serialize both manual and scanner check-ins
        // using the same student row lock.
        $student = $db->query(
            "SELECT id
             FROM students
             WHERE id = :id
             FOR UPDATE",
            ['id' => $studentId]
        )->fetch();

        if (!is_array($student)) {
            return 'unknown';
        }

        $minutes = self::DUPLICATE_MINUTES;

        // Imported historical records are excluded.
        // Both live check-in sources are considered.
        $recent = $db->query(
            "SELECT id
             FROM attendances
             WHERE student_id = :student_id
               AND source IN ('scanner', 'manual')
               AND attended_at_utc >
                   UTC_TIMESTAMP() - INTERVAL {$minutes} MINUTE
             ORDER BY attended_at_utc DESC, id DESC
             LIMIT 1",
            ['student_id' => $studentId]
        )->fetchColumn();

        if ($recent !== false) {
            return 'duplicate';
        }

        $db->query(
            "INSERT INTO attendances (
                student_id,
                attended_at_utc,
                source
             ) VALUES (
                :student_id,
                UTC_TIMESTAMP(),
                :source
             )",
            [
                'student_id' => $studentId,
                'source' => $source
            ]
        );

        return 'saved';
    }
}
