<?php

declare(strict_types=1);

use Framework\Config;
use Framework\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli' || Config::get('app.environment') !== 'local') {
    fwrite(STDERR, "Local CLI only.\n");
    exit(1);
}

$apply = in_array('--apply', $argv, true);
$base = dirname(__DIR__) . '/storage/imports/';
$sourceFile = $base . 'attendance.csv';
$reviewFile = $base . 'attendance_review.csv';

/**
 * Read the CSV into an array indexed by its original No field.
 * CSV row numbers are 1-based records, with the header at row 1.
 */
function readImportCsv(string $path, array $expectedColumns): array
{
    if (!is_readable($path)) {
        throw new RuntimeException("CSV not found or unreadable: " . basename($path));
    }

    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Could not open ' . basename($path));
    }

    try {
        $columns = fgetcsv($handle, 0, ',', '"', '');
        if (!is_array($columns)) {
            throw new RuntimeException('Empty CSV: ' . basename($path));
        }
        $columns[0] = preg_replace('/^\xEF\xBB\xBF/', '', $columns[0]);

        if ($columns !== $expectedColumns) {
            throw new RuntimeException('Unexpected columns in ' . basename($path));
        }

        $records = [];
        $rowNumber = 1;

        while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rowNumber++;
            if ($values === [null]) {
                continue;
            }
            if (count($values) !== count($expectedColumns)) {
                throw new RuntimeException("Invalid CSV record {$rowNumber}");
            }

            $rawId = trim($values[0]);
            $id = filter_var(
                $rawId,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($id === false) {
                throw new RuntimeException("Invalid No at CSV record {$rowNumber}");
            }
            if (isset($records[$id])) {
                throw new RuntimeException("Duplicate No {$id} in " . basename($path));
            }

            $records[$id] = [
                'row_number' => $rowNumber,
                'values' => $values,
            ];
        }

        return $records;
    } finally {
        fclose($handle);
    }
}

try {
    $source = readImportCsv(
        $sourceFile,
        ['No', 'Ημερομηνία', 'Ονομα', 'Επίθετο']
    );
    $review = readImportCsv(
        $reviewFile,
        ['No', 'Ημερομηνία', 'Ονομα', 'Επίθετο', 'reason']
    );

    $allowedReasons = [
        'empty_name', 'unmatched', 'ambiguous', 'invalid_date', 'conflict',
    ];
    $issueCounts = array_fill_keys($allowedReasons, 0);

    foreach ($review as $legacyId => $record) {
        if (!isset($source[$legacyId])) {
            throw new RuntimeException("Review No {$legacyId} is missing from attendance.csv");
        }

        $reviewValues = $record['values'];
        $originalValues = $source[$legacyId]['values'];
        if (array_slice($reviewValues, 1, 3) !== array_slice($originalValues, 1, 3)) {
            throw new RuntimeException("Stale review data for attendance No {$legacyId}");
        }

        $reason = trim($reviewValues[4]);
        if (!in_array($reason, $allowedReasons, true)) {
            throw new RuntimeException("Unknown issue reason for No {$legacyId}: {$reason}");
        }
        $issueCounts[$reason]++;
        $review[$legacyId]['reason'] = $reason;
    }

    $fingerprint = hash_file('sha256', $sourceFile);
    if ($fingerprint === false) {
        throw new RuntimeException('Could not calculate CSV fingerprint.');
    }

    $db = new Database(Config::get('database'));

    $imported = [];
    $rows = $db->query(
        "SELECT legacy_id FROM attendances
         WHERE source = 'import' AND legacy_id IS NOT NULL"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($rows as $rawId) {
        $id = (int) $rawId;
        if (!isset($source[$id])) {
            throw new RuntimeException("Imported attendance No {$id} not found in CSV.");
        }
        if (isset($review[$id])) {
            throw new RuntimeException("Attendance No {$id} exists both in DB and review CSV.");
        }
        $imported[$id] = true;
    }

    $unaccounted = count($source) - count($imported) - count($review);
    if ($unaccounted !== 0) {
        throw new RuntimeException(
            "Source coverage mismatch: {$unaccounted} records unaccounted for. " .
            'Regenerate attendance_review.csv by running import-attendances.php without --apply.'
        );
    }

    $previous = $db->query(
        "SELECT id, total_rows, existing_rows, issue_rows, status
         FROM import_batches
         WHERE source_type = 'attendances'
           AND run_kind = 'reconciliation'
           AND source_sha256 = :fingerprint
         ORDER BY id DESC LIMIT 1",
        ['fingerprint' => $fingerprint]
    )->fetch(PDO::FETCH_ASSOC);

    echo "=== ATTENDANCE IMPORT RECONCILIATION ===\n";
    echo 'Source rows: ' . count($source) . "\n";
    echo 'Already imported attendances: ' . count($imported) . "\n";
    echo 'Review issues: ' . count($review) . "\n";
    foreach ($issueCounts as $reason => $count) {
        echo "  {$reason}: {$count}\n";
    }
    echo "CSV SHA-256: {$fingerprint}\n";

    if ($previous !== false) {
        $recordedIssues = (int) $db->query(
            'SELECT COUNT(*) FROM import_issues WHERE batch_id = :batch_id',
            ['batch_id' => $previous['id']]
        )->fetchColumn();

        if (
            (int) $previous['total_rows'] !== count($source) ||
            (int) $previous['existing_rows'] !== count($imported) ||
            (int) $previous['issue_rows'] !== count($review) ||
            $recordedIssues !== count($review) ||
            $previous['status'] !== (count($review) > 0 ? 'completed_with_issues' : 'completed')
        ) {
            throw new RuntimeException(
                'Existing reconciliation batch is inconsistent. Manual review required.'
            );
        }

        echo "ALREADY RECORDED: batch #{$previous['id']}. No changes made.\n";
        exit(0);
    }

    if (!$apply) {
        echo "DRY RUN: no database changes. Use --apply.\n";
        exit(0);
    }

    $batchId = $db->transaction(
        static function (Database $db) use ($fingerprint, $source, $imported, $review): int {
            // Do not silently create a second reconciliation of the same file.
            $duplicate = (int) $db->query(
                "SELECT COUNT(*) FROM import_batches
                 WHERE source_type = 'attendances'
                   AND run_kind = 'reconciliation'
                   AND source_sha256 = :fingerprint",
                ['fingerprint' => $fingerprint]
            )->fetchColumn();

            if ($duplicate > 0) {
                throw new RuntimeException('Reconciliation batch already exists.');
            }

            $db->query(
                "INSERT INTO import_batches
                 (source_type, source_filename, source_sha256, run_kind,
                  status, total_rows, inserted_rows, existing_rows,
                  skipped_rows, issue_rows, finished_at)
                 VALUES
                 ('attendances', 'attendance.csv', :fingerprint, 'reconciliation',
                  :status, :total, 0, :already, 0, :issues, CURRENT_TIMESTAMP)",
                [
                    'fingerprint' => $fingerprint,
                    'status' => count($review) > 0 ? 'completed_with_issues' : 'completed',
                    'total' => count($source),
                    'already' => count($imported),
                    'issues' => count($review),
                ]
            );

            $batchId = (int) $db->query('SELECT LAST_INSERT_ID()')->fetchColumn();
            if ($batchId < 1) {
                throw new RuntimeException('Could not retrieve new batch ID.');
            }

            foreach ($review as $legacyId => $record) {
                // No names or other personal data are copied to the issue table.
                $db->query(
                    'INSERT INTO import_issues
                     (batch_id, source_record_id, source_row_number, issue_code)
                     VALUES (:batch_id, :record_id, :row_number, :issue_code)',
                    [
                        'batch_id' => $batchId,
                        'record_id' => (string) $legacyId,
                        'row_number' => $source[$legacyId]['row_number'],
                        'issue_code' => $record['reason'],
                    ]
                );
            }

            return $batchId;
        }
    );

    echo "RECONCILIATION COMPLETE: batch #{$batchId}; " .
         count($review) . " issues recorded.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'STOP: ' . $exception->getMessage() . "\n");
    exit(1);
}
