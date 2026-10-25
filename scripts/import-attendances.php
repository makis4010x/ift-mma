<?php

declare(strict_types=1);

use Framework\Config;
use Framework\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli' || Config::get('app.environment') !== 'local') {
    exit("Local CLI only.\n");
}

$apply = in_array('--apply', $argv, true);
$file = dirname(__DIR__) . '/storage/imports/attendance.csv';
$report = dirname(__DIR__) . '/storage/imports/attendance_review.csv';

if (!is_readable($file)) {
    exit("Missing storage/imports/attendance.csv\n");
}

function attendanceNameKey(string $name): string
{
    $value = strtr(trim($name), [
        'α'=>'Α', 'β'=>'Β', 'γ'=>'Γ', 'δ'=>'Δ', 'ε'=>'Ε',
        'ζ'=>'Ζ', 'η'=>'Η', 'θ'=>'Θ', 'ι'=>'Ι', 'κ'=>'Κ',
        'λ'=>'Λ', 'μ'=>'Μ', 'ν'=>'Ν', 'ξ'=>'Ξ', 'ο'=>'Ο',
        'π'=>'Π', 'ρ'=>'Ρ', 'σ'=>'Σ', 'ς'=>'Σ', 'τ'=>'Τ',
        'υ'=>'Υ', 'φ'=>'Φ', 'χ'=>'Χ', 'ψ'=>'Ψ', 'ω'=>'Ω',
        'ά'=>'Α', 'έ'=>'Ε', 'ή'=>'Η', 'ί'=>'Ι',
        'ϊ'=>'Ι', 'ΐ'=>'Ι', 'ό'=>'Ο', 'ύ'=>'Υ',
        'ϋ'=>'Υ', 'ΰ'=>'Υ', 'ώ'=>'Ω',
    ]);
    $value = strtoupper($value);
    $value = strtr($value, [
        'Ά' => 'Α', 'Έ' => 'Ε', 'Ή' => 'Η',
        'Ί' => 'Ι', 'Ϊ' => 'Ι', 'Ό' => 'Ο',
        'Ύ' => 'Υ', 'Ϋ' => 'Υ', 'Ώ' => 'Ω',
    ]);
    $value = preg_replace('/\p{Mn}/u', '', $value);
    $value = preg_replace('/\s+/u', ' ', $value);

    if ($value === null) {
        throw new RuntimeException('Invalid UTF-8 in name.');
    }

    return $value;
}

function attendanceUtc(string $raw): ?string
{
    $months = [
        'Ιαν' => 1, 'Φεβ' => 2, 'Μαρ' => 3,
        'Απρ' => 4, 'Μαϊ' => 5, 'Ιουν' => 6,
        'Ιουλ' => 7, 'Αυγ' => 8, 'Σεπ' => 9,
        'Οκτ' => 10, 'Νοε' => 11, 'Δεκ' => 12,
    ];
    $weekdays = [
        'Δευ' => 1, 'Τρι' => 2, 'Τετ' => 3,
        'Πεμ' => 4, 'Παρ' => 5, 'Σαβ' => 6, 'Κυρ' => 7,
    ];

    if (!preg_match(
        '/^(Δευ|Τρι|Τετ|Πεμ|Παρ|Σαβ|Κυρ),\s*' .
        '(\d{1,2})\s+(\p{L}+)\s+(\d{4})\s+' .
        '(\d{1,2}):(\d{2})\s+(ΠΜ|ΜΜ)$/u',
        trim($raw),
        $m
    )) {
        return null;
    }

    $day = (int) $m[2];
    $month = $months[$m[3]] ?? null;
    $year = (int) $m[4];
    $hour12 = (int) $m[5];
    $minute = (int) $m[6];

    if (
        $month === null || !checkdate($month, $day, $year) ||
        $hour12 < 1 || $hour12 > 12 || $minute > 59
    ) {
        return null;
    }

    $hour24 = ($hour12 % 12) + ($m[7] === 'ΜΜ' ? 12 : 0);
    $localString = sprintf(
        '%04d-%02d-%02d %02d:%02d:00',
        $year, $month, $day, $hour24, $minute
    );
    $local = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        $localString,
        new DateTimeZone('Europe/Athens')
    );

    if (
        !$local ||
        $local->format('Y-m-d H:i:s') !== $localString ||
        (int) $local->format('N') !== $weekdays[$m[1]]
    ) {
        return null;
    }

    return $local->setTimezone(
        new DateTimeZone('UTC')
    )->format('Y-m-d H:i:s');
}

$db = new Database(Config::get('database'));

// A name must identify exactly one student.
$nameIndex = [];
$students = $db->query(
    'SELECT id, first_name, last_name FROM students'
)->fetchAll();

foreach ($students as $student) {
    $key = attendanceNameKey($student['first_name'])
        . "\x1F"
        . attendanceNameKey($student['last_name']);
    $nameIndex[$key][] = (int) $student['id'];
}

// Existing legacy IDs make the import safe to repeat.
$existing = [];
$oldRows = $db->query(
    'SELECT legacy_id, student_id, attended_at_utc, source
     FROM attendances WHERE legacy_id IS NOT NULL'
)->fetchAll();

foreach ($oldRows as $row) {
    $existing[(int) $row['legacy_id']] = $row;
}

$handle = fopen($file, 'rb');
if ($handle === false) {
    exit("Could not open CSV.\n");
}

$pending = [];
$review = [];
$seen = [];
$counts = [
    'empty_name' => 0,
    'unmatched' => 0,
    'ambiguous' => 0,
    'invalid_date' => 0,
    'already_imported' => 0,
    'conflict' => 0,
];
$total = 0;

try {
    $headers = fgetcsv($handle, 0, ',', '"', '');
    if (!is_array($headers)) {
        throw new RuntimeException('CSV is empty.');
    }
    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);

    if ($headers !== ['No', 'Ημερομηνία', 'Ονομα', 'Επίθετο']) {
        throw new RuntimeException('Unexpected attendance.csv columns.');
    }

    while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
        if ($values === [null]) {
            continue;
        }
        $total++;
        if (count($values) !== 4) {
            throw new RuntimeException("Invalid CSV row {$total}.");
        }
        [$no, $rawDate, $first, $last] = $values;
        $legacyId = filter_var(
            trim($no),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($legacyId === false || isset($seen[$legacyId])) {
            throw new RuntimeException("Invalid or duplicate No: {$no}");
        }
        $seen[$legacyId] = true;

        $reason = null;
        $studentId = null;
        $dateUtc = null;

        if (trim($first) === '' || trim($last) === '') {
            $reason = 'empty_name';
        } else {
            $key = attendanceNameKey($first)
                . "\x1F"
                . attendanceNameKey($last);
            $matches = $nameIndex[$key] ?? [];

            if (count($matches) === 0) {
                $reason = 'unmatched';
            } elseif (count($matches) > 1) {
                $reason = 'ambiguous';
            } else {
                $studentId = $matches[0];
                $dateUtc = attendanceUtc($rawDate);
                if ($dateUtc === null) {
                    $reason = 'invalid_date';
                }
            }
        }

        if ($reason === null && isset($existing[$legacyId])) {
            $old = $existing[$legacyId];
            if (
                (int) $old['student_id'] === $studentId &&
                $old['attended_at_utc'] === $dateUtc &&
                $old['source'] === 'import'
            ) {
                $counts['already_imported']++;
                continue;
            }
            $reason = 'conflict';
        }

        if ($reason !== null) {
            $counts[$reason]++;
            $review[] = [$legacyId, $rawDate, $first, $last, $reason];
            continue;
        }

        $pending[] = [
            'student_id' => $studentId,
            'legacy_id' => $legacyId,
            'attended_at_utc' => $dateUtc,
            'source' => 'import',
        ];
    }
} finally {
    fclose($handle);
}

// The review report stays local and excluded from Git.
$out = fopen($report, 'wb');
if ($out === false) {
    exit("Could not create review report.\n");
}
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['No', 'Ημερομηνία', 'Ονομα', 'Επίθετο', 'reason'], ',', '"', '');
foreach ($review as $item) {
    fputcsv($out, $item, ',', '"', '');
}
fclose($out);

echo "CSV entries: {$total}\n";
echo 'Ready to import: ' . count($pending) . "\n";
foreach ($counts as $reason => $count) {
    echo "{$reason}: {$count}\n";
}
echo "Review file: storage/imports/attendance_review.csv\n";

if ($counts['conflict'] > 0) {
    exit("STOP: existing legacy IDs conflict. No changes made.\n");
}

if (!$apply) {
    exit("DRY RUN: no database changes. Use --apply.\n");
}

try {
    $db->transaction(static function (Database $db) use ($pending): void {
        foreach ($pending as $item) {
            $db->query(
                'INSERT INTO attendances
                 (student_id, legacy_id, attended_at_utc, source)
                 VALUES (:student_id, :legacy_id, :attended_at_utc, :source)',
                $item
            );
        }
    });
} catch (Throwable $exception) {
    fwrite(STDERR, "Import failed; transaction rolled back.\n");
    exit(1);
}

echo 'IMPORT COMPLETE. Inserted: ' . count($pending) . "\n";
