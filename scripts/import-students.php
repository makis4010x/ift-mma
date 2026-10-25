<?php

declare(strict_types=1);

use Framework\Config;
use Framework\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

// Only allow command-line execution.
if (PHP_SAPI !== 'cli') {
    exit('CLI only.');
}

$apply = in_array('--apply', $argv, true);

$file = dirname(__DIR__)
    . '/storage/imports/students_detailed.csv';

if (!is_readable($file)) {
    exit("CSV not found in storage/imports.\n");
}

$handle = fopen($file, 'rb');

$header = fgetcsv($handle, 0, ',', '"', '');

if (!is_array($header)) {
    exit("CSV is empty.\n");
}

// Remove UTF-8 BOM if present.
$header[0] = preg_replace(
    '/^\xEF\xBB\xBF/',
    '',
    $header[0]
);

$expected = [
    'No',
    'Όνομα',
    'Επίθετο',
    'Τηλέφωνο',
    'Τηλέφωνο Επικοινωνίας',
    'Όνομα πατρός',
    'Ημ. Πληρωμής',
    'Γενέθλια',
    'Ταυτότητα',
    'Διεύθυνση',
    'Χρέωση',
    'Barcode',
    'Email'
];

if ($header !== $expected) {
    exit("Unexpected CSV headers. No changes made.\n");
}

// Convert empty optional fields to NULL.
$nullable = static fn(string $value): ?string =>
    trim($value) === '' ? null : trim($value);

$students = [];
$seenLegacyIds = [];
$skipped = 0;
$line = 1;

// Read and validate the complete CSV first.
while (
    ($values = fgetcsv($handle, 0, ',', '"', ''))
    !== false
) {
    $line++;

    if ($values === [null]) {
        continue;
    }

    if (count($values) !== count($expected)) {
        exit("Invalid columns at line {$line}.\n");
    }

    $row = array_combine($expected, $values);

    $legacyId = filter_var(
        trim($row['No']),
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (
        $legacyId === false ||
        isset($seenLegacyIds[$legacyId])
    ) {
        exit("Invalid or duplicate ID at line {$line}.\n");
    }

    $seenLegacyIds[$legacyId] = true;

    $firstName = trim($row['Όνομα']);
    $lastName = trim($row['Επίθετο']);

    if ($firstName === '' || $lastName === '') {
        $skipped++;
        continue;
    }

    $birthDate = $nullable($row['Γενέθλια']);

    if ($birthDate !== null) {
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $birthDate
        );

        if (
            !$date ||
            $date->format('Y-m-d') !== $birthDate
        ) {
            exit("Invalid birth date at line {$line}.\n");
        }
    }

    // Do not import the old barcode.
    $students[] = [
        'legacy_id' => $legacyId,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => $nullable($row['Τηλέφωνο']),
        'contact_phone' => $nullable(
            $row['Τηλέφωνο Επικοινωνίας']
        ),
        'father_name' => $nullable($row['Όνομα πατρός']),
        'birth_date' => $birthDate,
        'identity_number' => $nullable($row['Ταυτότητα']),
        'address' => $nullable($row['Διεύθυνση']),
        'email' => $nullable($row['Email'])
    ];
}

fclose($handle);

$db = new Database(Config::get('database'));

// Find previously imported students.
$existingIds = array_fill_keys(
    $db->query(
        'SELECT legacy_id FROM students
         WHERE legacy_id IS NOT NULL'
    )->fetchAll(PDO::FETCH_COLUMN),
    true
);

$new = count(array_filter(
    $students,
    static fn(array $student): bool =>
        !isset($existingIds[$student['legacy_id']])
));

echo 'Valid students: '
    . count($students) . PHP_EOL;

echo 'Skipped missing names: '
    . $skipped . PHP_EOL;

echo 'New students: '
    . $new . PHP_EOL;

echo 'Existing students: '
    . (count($students) - $new) . PHP_EOL;

// Default mode: no database changes.
if (!$apply) {
    exit("DRY RUN: no changes made. Use --apply.\n");
}

// Import everything inside a transaction.
[$inserted, $existing, $barcodes] = $db->transaction(
    static function (Database $db) use ($students): array {

        $inserted = 0;
        $existing = 0;
        $barcodes = 0;

        foreach ($students as $student) {

            $studentId = $db->query(
                'SELECT id FROM students
                 WHERE legacy_id = :legacy_id',
                ['legacy_id' => $student['legacy_id']]
            )->fetchColumn();

            if ($studentId === false) {

                $db->query(
                    'INSERT INTO students (
                        legacy_id,
                        first_name,
                        last_name,
                        phone,
                        contact_phone,
                        father_name,
                        birth_date,
                        identity_number,
                        address,
                        email
                    ) VALUES (
                        :legacy_id,
                        :first_name,
                        :last_name,
                        :phone,
                        :contact_phone,
                        :father_name,
                        :birth_date,
                        :identity_number,
                        :address,
                        :email
                    )',
                    $student
                );

                $studentId = $db->query(
                    'SELECT id FROM students
                     WHERE legacy_id = :legacy_id',
                    ['legacy_id' => $student['legacy_id']]
                )->fetchColumn();

                $inserted++;

            } else {
                // Preserve existing data on repeated imports.
                $existing++;
            }

            $barcodeId = $db->query(
                'SELECT id FROM student_barcodes
                 WHERE student_id = :student_id',
                ['student_id' => $studentId]
            )->fetchColumn();

            if ($barcodeId === false) {

                // New IFT Academy barcode from student ID.
                $code = 'IFT' . str_pad(
                    (string) $studentId,
                    6,
                    '0',
                    STR_PAD_LEFT
                );

                $db->query(
                    'INSERT INTO student_barcodes (
                        student_id,
                        code
                    ) VALUES (
                        :student_id,
                        :code
                    )',
                    [
                        'student_id' => $studentId,
                        'code' => $code
                    ]
                );

                $barcodes++;
            }
        }

        return [$inserted, $existing, $barcodes];
    }
);

echo "IMPORT COMPLETE!\n";
echo "Inserted students: {$inserted}\n";
echo "Existing students: {$existing}\n";
echo "Created barcodes: {$barcodes}\n";
