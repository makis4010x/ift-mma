<?php

declare(strict_types=1);

use Framework\Config;
use Framework\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

if (
    PHP_SAPI !== 'cli' ||
    Config::get('app.environment') !== 'local'
) {
    exit("Local CLI only.\n");
}

$apply = in_array('--apply', $argv, true);

$file = dirname(__DIR__)
    . '/storage/imports/students_detailed.csv';

if (!is_readable($file)) {
    exit("CSV file not found.\n");
}

$handle = fopen($file, 'rb');

if ($handle === false) {
    exit("Cannot open CSV.\n");
}

try {
    $headers = fgetcsv($handle, 0, ',', '"', '');

    if (!is_array($headers)) {
        throw new RuntimeException('Empty CSV.');
    }

    $headers[0] = preg_replace(
        '/^\xEF\xBB\xBF/',
        '',
        $headers[0]
    );

    if (count($headers) !== count(array_unique($headers))) {
        throw new RuntimeException('Duplicate CSV headers.');
    }

    $columns = array_flip($headers);

    foreach (['No', 'Χρέωση', 'Ημ. Πληρωμής'] as $name) {
        if (!array_key_exists($name, $columns)) {
            throw new RuntimeException(
                "Missing column: {$name}"
            );
        }
    }

    $records = [];
    $seen = [];
    $skipped = 0;
    $line = 1;

    while (
        ($values = fgetcsv($handle, 0, ',', '"', ''))
        !== false
    ) {
        $line++;

        if ($values === [null]) {
            continue;
        }

        if (count($values) !== count($headers)) {
            throw new RuntimeException(
                "Invalid columns at line {$line}."
            );
        }

        $legacyId = filter_var(
            trim($values[$columns['No']]),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if (
            $legacyId === false ||
            isset($seen[$legacyId])
        ) {
            throw new RuntimeException(
                "Invalid or duplicate student ID at line {$line}."
            );
        }

        $seen[$legacyId] = true;

        $feeRaw = trim($values[$columns['Χρέωση']]);
        $dayRaw = trim($values[$columns['Ημ. Πληρωμής']]);

        if ($feeRaw === '' || $dayRaw === '') {
            $skipped++;
            continue;
        }

        if (!preg_match(
            '/^\d{1,8}(?:[.,]\d{1,2})?$/D',
            $feeRaw
        )) {
            throw new RuntimeException(
                "Invalid fee at line {$line}."
            );
        }

        if (
            !ctype_digit($dayRaw) ||
            (int) $dayRaw < 1 ||
            (int) $dayRaw > 31
        ) {
            throw new RuntimeException(
                "Invalid billing day at line {$line}."
            );
        }

        $parts = explode(
            '.',
            str_replace(',', '.', $feeRaw),
            2
        );

        $fee = (string) (int) $parts[0]
            . '.'
            . str_pad($parts[1] ?? '', 2, '0');

        $records[$legacyId] = [
            'fee' => $fee,
            'day' => (int) $dayRaw
        ];
    }
} finally {
    fclose($handle);
}

$db = new Database(Config::get('database'));

// Match old student IDs to new database IDs.
$students = $db->query(
    'SELECT id, legacy_id
     FROM students
     WHERE legacy_id IS NOT NULL'
)->fetchAll();

$studentMap = [];

foreach ($students as $student) {
    $studentMap[(int) $student['legacy_id']]
        = (int) $student['id'];
}

// Read existing snapshots for safe re-import.
$existing = $db->query(
    'SELECT student_id,
            recorded_monthly_fee,
            recorded_billing_day
     FROM legacy_billing_snapshots'
)->fetchAll();

$existingMap = [];

foreach ($existing as $row) {
    $existingMap[(int) $row['student_id']] = $row;
}

$pending = [];
$already = 0;
$conflicts = 0;
$missing = 0;

foreach ($records as $legacyId => $record) {
    if (!isset($studentMap[$legacyId])) {
        $missing++;
        continue;
    }

    $studentId = $studentMap[$legacyId];

    if (isset($existingMap[$studentId])) {
        $old = $existingMap[$studentId];

        if (
            (string) $old['recorded_monthly_fee']
                === $record['fee'] &&
            (int) $old['recorded_billing_day']
                === $record['day']
        ) {
            $already++;
        } else {
            $conflicts++;
        }

        continue;
    }

    $pending[] = [
        'student_id' => $studentId,
        'fee' => $record['fee'],
        'day' => $record['day'],
        'source' => 'students_detailed.csv'
    ];
}

echo 'Valid billing records: '
    . count($records) . PHP_EOL;

echo "Skipped incomplete: {$skipped}\n";
echo 'New snapshots: ' . count($pending) . PHP_EOL;
echo "Already imported: {$already}\n";
echo "Conflicts: {$conflicts}\n";
echo "Missing students: {$missing}\n";

if ($conflicts > 0 || $missing > 0) {
    fwrite(
        STDERR,
        "Import blocked. No data changed.\n"
    );
    exit(1);
}

if (!$apply) {
    exit("DRY RUN: no changes made.\n");
}

try {
    $db->transaction(
        static function (Database $db) use ($pending): void {
            foreach ($pending as $row) {
                $db->query(
                    'INSERT INTO legacy_billing_snapshots
                     (
                        student_id,
                        recorded_monthly_fee,
                        recorded_billing_day,
                        source_filename
                     )
                     VALUES
                     (
                        :student_id,
                        :fee,
                        :day,
                        :source
                     )',
                    $row
                );
            }
        }
    );
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        "Import failed. Transaction rolled back.\n"
    );
    exit(1);
}

echo "IMPORT COMPLETE!\n";
echo 'Created snapshots: '
    . count($pending) . PHP_EOL;
