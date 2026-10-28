<?php

declare(strict_types=1);

use Framework\Config;
use Framework\Database;

/**
 * Read UTF-8 CSV as strings.
 * Preserve every original column and cell value.
 */
function lpaReadCsv(string $path, array $required): array
{
    $bytes = is_readable($path)
        ? file_get_contents($path)
        : false;

    if (
        $bytes === false ||
        $bytes === '' ||
        strlen($bytes) > 30000000
    ) {
        throw new RuntimeException(
            'Missing, empty or oversized CSV: '
                . basename($path)
        );
    }

    if (preg_match('//u', $bytes) !== 1) {
        throw new RuntimeException(
            'CSV must be UTF-8: '
                . basename($path)
        );
    }

    $stream = fopen('php://temp', 'w+b');

    fwrite(
        $stream,
        preg_replace('/^\xEF\xBB\xBF/', '', $bytes)
    );

    rewind($stream);

    try {
        $header = fgetcsv(
            $stream,
            0,
            ',',
            '"',
            ''
        );

        if (
            !is_array($header) ||
            count($header) !== count(array_unique($header)) ||
            array_diff($required, $header) !== []
        ) {
            throw new RuntimeException(
                'Unexpected CSV columns: '
                    . basename($path)
            );
        }

        $rows = [];

        while (
            ($cells = fgetcsv($stream, 0, ',', '"', '')) !== false
        ) {
            if ($cells === [null]) {
                continue;
            }

            if (count($cells) !== count($header)) {
                throw new RuntimeException(
                    'Broken CSV record in '
                        . basename($path)
                );
            }

            $row = array_combine($header, $cells);

            // This importer archives records only.
            // It never promotes them to actual receipts.
            if (
                $row['approved_for_database_import'] !== '0' ||
                $row['receipt_date_meaning_confirmed'] !== '0'
            ) {
                throw new RuntimeException(
                    'Use the original unapproved mapping files.'
                );
            }

            $rows[] = $row;
        }

        return [
            'rows' => $rows,
            'sha256' => hash('sha256', $bytes)
        ];
    } finally {
        fclose($stream);
    }
}

/**
 * Accept integer identifiers, including values such as 38.0.
 * Never round a non-integer value.
 */
function lpaInteger(
    string $value,
    bool $zeroAllowed = false
): int {
    if (
        preg_match(
            '/\A(?:0|[1-9][0-9]*)(?:\.0+)?\z/D',
            $value
        ) !== 1
    ) {
        throw new RuntimeException(
            'Invalid integer identifier/count.'
        );
    }

    $id = filter_var(
        explode('.', $value, 2)[0],
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => $zeroAllowed ? 0 : 1
            ]
        ]
    );

    if ($id === false) {
        throw new RuntimeException(
            'Integer identifier/count out of range.'
        );
    }

    return $id;
}

function lpaJson(array $value): string
{
    return json_encode(
        $value,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
}

function lpaKey(string $value): string
{
    if (
        preg_match(
            '/\A[a-f0-9]{64}\z/D',
            $value
        ) !== 1
    ) {
        throw new RuntimeException(
            'Invalid snapshot key.'
        );
    }

    return $value;
}

function lpaText(string $value): string
{
    if (class_exists('Normalizer')) {
        $value = Normalizer::normalize(
            $value,
            Normalizer::FORM_C
        );
    }

    $value = trim(
        preg_replace('/\s+/u', ' ', $value)
    );

    // Without mbstring matching is more conservative for Greek.
    return function_exists('mb_strtolower')
        ? mb_strtolower($value, 'UTF-8')
        : strtolower($value);
}

function lpaIdentity(array $row): string
{
    $email = lpaText(
        (string) ($row['email'] ?? '')
    );

    if ($email === '') {
        return '';
    }

    return lpaJson([
        lpaText((string) $row['first_name']),
        lpaText((string) $row['last_name']),
        $email
    ]);
}

function lpaDate(string $value): ?string
{
    if ($value === '') {
        // Unknown dates remain unknown.
        // The original displayed text is preserved separately.
        return null;
    }

    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $value
    );

    if (
        preg_match(
            '/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}\z/D',
            $value
        ) !== 1 ||
        !$date ||
        $date->format('Y-m-d') !== $value
    ) {
        throw new RuntimeException(
            'Invalid normalized date in CSV.'
        );
    }

    return $value;
}

function lpaMoney(string $value): ?string
{
    if ($value === '') {
        return null;
    }

    if (
        preg_match(
            '/\A-?[0-9]{1,10}(?:\.[0-9]{1,2})?\z/D',
            $value
        ) !== 1
    ) {
        throw new RuntimeException(
            'Invalid normalized monetary amount in CSV.'
        );
    }

    // Keep decimal strings; do not convert to floating point.
    return $value;
}

/**
 * Read both files completely.
 * Cross-check their contents before connecting to MySQL.
 */
function lpaReadBundle(string $directory): array
{
    $common = [
        'snapshot_id',
        'snapshot_student_key',
        'source_list_no',
        'db_student_id',
        'legacy_student_no',
        'first_name',
        'last_name',
        'approved_for_database_import',
        'receipt_date_meaning_confirmed'
    ];

    $links = lpaReadCsv(
        $directory . '/student_links.csv',
        [
            ...$common,
            'email',
            'mapping_status',
            'payment_rows',
            'history_complete'
        ]
    );

    $history = lpaReadCsv(
        $directory . '/legacy_history_linked.csv',
        [
            ...$common,
            'snapshot_row_key',
            'record_date_iso',
            'charged_amount',
            'paid_amount',
            'balance_amount',
            'row_review',
            'reconciliation_flags',
            'db_mapping_status',
            'history_complete',
            'source_mode'
        ]
    );

    if ($links['rows'] === []) {
        throw new RuntimeException(
            'No profiles in CSV.'
        );
    }

    $snapshot = $links['rows'][0]['snapshot_id'];

    if (
        preg_match(
            '/\A[A-Za-z0-9_-]{1,80}\z/D',
            $snapshot
        ) !== 1
    ) {
        throw new RuntimeException(
            'Invalid snapshot name.'
        );
    }

    $profiles = [];
    $counts = [];

    foreach ($links['rows'] as $row) {
        $key = lpaKey(
            $row['snapshot_student_key']
        );

        if (
            $row['snapshot_id'] !== $snapshot ||
            isset($profiles[$key])
        ) {
            throw new RuntimeException(
                'Mixed snapshots or duplicate profile key.'
            );
        }

        lpaInteger($row['source_list_no']);
        lpaInteger($row['payment_rows'], true);

        if ($row['history_complete'] !== '1') {
            throw new RuntimeException(
                'Incomplete profile history; keep for review.'
            );
        }

        $profiles[$key] = $row;
        $counts[$key] = 0;
    }

    $seen = [];

    foreach ($history['rows'] as $row) {
        $key = lpaKey(
            $row['snapshot_row_key']
        );

        $profileKey = lpaKey(
            $row['snapshot_student_key']
        );

        if (
            $row['snapshot_id'] !== $snapshot ||
            isset($seen[$key]) ||
            !isset($profiles[$profileKey]) ||
            $row['history_complete'] !== '1' ||
            $row['source_mode'] !== 'live_ui'
        ) {
            throw new RuntimeException(
                'Invalid, duplicate-key or orphan history record.'
            );
        }

        $profile = $profiles[$profileKey];

        foreach (
            [
                'source_list_no',
                'db_student_id',
                'legacy_student_no',
                'first_name',
                'last_name'
            ] as $field
        ) {
            if ($row[$field] !== $profile[$field]) {
                throw new RuntimeException(
                    'Profile/history mismatch: ' . $field
                );
            }
        }

        if (
            $row['db_mapping_status'] !==
            $profile['mapping_status']
        ) {
            throw new RuntimeException(
                'Profile/history mapping status mismatch.'
            );
        }

        lpaDate($row['record_date_iso']);

        foreach (
            [
                'charged_amount',
                'paid_amount',
                'balance_amount'
            ] as $field
        ) {
            lpaMoney($row[$field]);
        }

        $seen[$key] = true;
        $counts[$profileKey]++;
    }

    foreach ($profiles as $key => $row) {
        if (
            $counts[$key] !==
            lpaInteger($row['payment_rows'], true)
        ) {
            throw new RuntimeException(
                'Profile history count does not match CSV.'
            );
        }
    }

    return [
        'snapshot' => $snapshot,
        'profiles' => $profiles,
        'rows' => $history['rows'],
        'profiles_hash' => $links['sha256'],
        'history_hash' => $history['sha256']
    ];
}

/**
 * Write only to the three archive tables.
 * Do not change live payments, coverage or student statuses.
 */
function lpaImport(
    Database $db,
    array $bundle
): array {
    return $db->transaction(
        static function (Database $db) use ($bundle): array {
            $existing = $db->query(
                'SELECT *
                 FROM legacy_payment_archives
                 WHERE snapshot_id = :snapshot
                 FOR UPDATE',
                [
                    'snapshot' => $bundle['snapshot']
                ]
            )->fetch();

            if (is_array($existing)) {
                if (
                    !hash_equals(
                        $existing['profiles_sha256'],
                        $bundle['profiles_hash']
                    ) ||
                    !hash_equals(
                        $existing['history_sha256'],
                        $bundle['history_hash']
                    )
                ) {
                    throw new RuntimeException(
                        'This snapshot exists with different file hashes. '
                            . 'No overwrite.'
                    );
                }

                $profiles = (int) $db->query(
                    'SELECT COUNT(*)
                     FROM legacy_payment_profiles
                     WHERE archive_id = :archive',
                    [
                        'archive' => $existing['id']
                    ]
                )->fetchColumn();

                $rows = (int) $db->query(
                    'SELECT COUNT(*)
                     FROM legacy_payment_history h
                     JOIN legacy_payment_profiles p
                        ON p.id = h.profile_id
                     WHERE p.archive_id = :archive',
                    [
                        'archive' => $existing['id']
                    ]
                )->fetchColumn();

                if (
                    $profiles !== count($bundle['profiles']) ||
                    $rows !== count($bundle['rows'])
                ) {
                    throw new RuntimeException(
                        'Existing archive counts differ. '
                            . 'Manual review required.'
                    );
                }

                return [
                    'repeated' => true,
                    'profiles' => $profiles,
                    'rows' => $rows,
                    'proposed' => (int) $existing['proposed_links_count']
                ];
            }

            // Revalidate proposed links against the current database.
            $students = $db->query(
                'SELECT
                    id,
                    legacy_id,
                    first_name,
                    last_name,
                    email
                 FROM students
                 FOR UPDATE'
            )->fetchAll();

            $byId = [];
            $dbIdentityCount = [];

            foreach ($students as $student) {
                $byId[(int) $student['id']] = $student;

                $identity = lpaIdentity($student);

                $dbIdentityCount[$identity] =
                    ($dbIdentityCount[$identity] ?? 0) + 1;
            }

            $sourceIdentityCount = [];

            foreach ($bundle['profiles'] as $profile) {
                $identity = lpaIdentity($profile);

                $sourceIdentityCount[$identity] =
                    ($sourceIdentityCount[$identity] ?? 0) + 1;
            }

            $proposals = [];

            foreach ($bundle['profiles'] as $key => $profile) {
                if (
                    $profile['mapping_status'] !==
                    'unique_identity_and_legacy_verified' ||
                    $profile['db_student_id'] === '' ||
                    $profile['legacy_student_no'] === ''
                ) {
                    continue;
                }

                $id = lpaInteger(
                    $profile['db_student_id']
                );

                $student = $byId[$id] ?? null;
                $identity = lpaIdentity($profile);

                if (
                    $student !== null &&
                    $identity !== '' &&
                    lpaIdentity($student) === $identity &&
                    (string) $student['legacy_id'] ===
                    (string) lpaInteger(
                        $profile['legacy_student_no']
                    ) &&
                    ($dbIdentityCount[$identity] ?? 0) === 1 &&
                    $sourceIdentityCount[$identity] === 1
                ) {
                    $proposals[$key] = $id;
                }
            }

            $db->query(
                'INSERT INTO legacy_payment_archives (
                    snapshot_id,
                    profiles_sha256,
                    history_sha256,
                    profile_count,
                    record_count,
                    proposed_links_count,
                    imported_at_utc
                 ) VALUES (
                    :snapshot,
                    :profiles_hash,
                    :history_hash,
                    :profiles,
                    :records,
                    :proposed,
                    UTC_TIMESTAMP(6)
                 )',
                [
                    'snapshot' => $bundle['snapshot'],
                    'profiles_hash' => $bundle['profiles_hash'],
                    'history_hash' => $bundle['history_hash'],
                    'profiles' => count($bundle['profiles']),
                    'records' => count($bundle['rows']),
                    'proposed' => count($proposals)
                ]
            );

            $archiveId = (int) $db->query(
                'SELECT LAST_INSERT_ID()'
            )->fetchColumn();

            $profileIds = [];

            foreach ($bundle['profiles'] as $key => $profile) {
                $proposed = $proposals[$key] ?? null;

                $db->query(
                    'INSERT INTO legacy_payment_profiles (
                        archive_id,
                        snapshot_student_key,
                        source_list_no,
                        proposed_student_id,
                        link_state,
                        source_mapping_status,
                        verification_note,
                        source_profile_json
                     ) VALUES (
                        :archive,
                        :key,
                        :number,
                        :student,
                        :state,
                        :status,
                        :note,
                        :raw
                     )',
                    [
                        'archive' => $archiveId,
                        'key' => $key,

                        'number' => lpaInteger(
                            $profile['source_list_no']
                        ),

                        'student' => $proposed,

                        'state' => $proposed === null
                            ? 'review'
                            : 'candidate',

                        'status' => $profile['mapping_status'],

                        'note' => $proposed === null
                            ? 'Identity requires review; '
                            . 'no automatic assignment.'
                            : 'Unique identity and legacy_id rechecked; '
                            . 'receipt date meaning still unconfirmed.',

                        'raw' => lpaJson($profile)
                    ]
                );

                $profileIds[$key] = (int) $db->query(
                    'SELECT LAST_INSERT_ID()'
                )->fetchColumn();
            }

            foreach ($bundle['rows'] as $row) {
                $flags = implode(
                    ';',
                    array_filter([
                        $row['row_review'],
                        $row['reconciliation_flags']
                    ])
                );

                $db->query(
                    'INSERT INTO legacy_payment_history (
                        profile_id,
                        snapshot_row_key,
                        source_record_date,
                        source_charged_amount,
                        source_paid_amount,
                        source_balance_amount,
                        review_flags,
                        source_record_json
                     ) VALUES (
                        :profile,
                        :key,
                        :date,
                        :charged,
                        :paid,
                        :balance,
                        :flags,
                        :raw
                     )',
                    [
                        'profile' => $profileIds[$row['snapshot_student_key']],

                        'key' => $row['snapshot_row_key'],

                        'date' => lpaDate(
                            $row['record_date_iso']
                        ),

                        'charged' => lpaMoney(
                            $row['charged_amount']
                        ),

                        'paid' => lpaMoney(
                            $row['paid_amount']
                        ),

                        'balance' => lpaMoney(
                            $row['balance_amount']
                        ),

                        'flags' => $flags === ''
                            ? null
                            : $flags,

                        'raw' => lpaJson($row)
                    ]
                );
            }

            return [
                'repeated' => false,
                'profiles' => count($profileIds),
                'rows' => count($bundle['rows']),
                'proposed' => count($proposals)
            ];
        }
    );
}

// Allow the functions to be loaded separately without executing the CLI.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }

    try {
        require dirname(__DIR__) . '/vendor/autoload.php';

        if (Config::get('app.environment') !== 'local') {
            throw new RuntimeException(
                'This one-time archive importer is local CLI only.'
            );
        }

        if (
            count($argv) < 2 ||
            count($argv) > 3 ||
            (
                isset($argv[2]) &&
                $argv[2] !== '--apply'
            )
        ) {
            throw new RuntimeException(
                'Usage: php scripts/import-legacy-payment-history.php '
                    . '"PRIVATE_FOLDER" --apply'
            );
        }

        $directory = realpath($argv[1]);

        $root = str_replace(
            '\\',
            '/',
            (string) realpath(dirname(__DIR__))
        );

        $path = str_replace(
            '\\',
            '/',
            (string) $directory
        );

        if (
            $directory === false ||
            !is_dir($directory) ||
            strcasecmp($path, $root) === 0 ||
            strncasecmp(
                $path,
                $root . '/',
                strlen($root) + 1
            ) === 0
        ) {
            throw new RuntimeException(
                'Use a readable private folder OUTSIDE the project.'
            );
        }

        $bundle = lpaReadBundle($directory);

        printf(
            "[READ] %d profiles; %d history rows.\n",
            count($bundle['profiles']),
            count($bundle['rows'])
        );

        if (($argv[2] ?? '') !== '--apply') {
            echo "No database changes. "
                . "Add --apply to archive these files.\n";

            exit(0);
        }

        $result = lpaImport(
            new Database(Config::get('database')),
            $bundle
        );

        echo $result['repeated']
            ? "[SKIP] Identical snapshot already archived.\n"
            : "[DONE] Historical snapshot archived.\n";

        printf(
            "Profiles: %d\n"
                . "Proposed links: %d\n"
                . "Profiles requiring identity review: %d\n"
                . "History rows: %d\n",
            $result['profiles'],
            $result['proposed'],
            $result['profiles'] - $result['proposed'],
            $result['rows']
        );

        echo "Live payments, coverage, balances "
            . "and student activity: UNCHANGED.\n";

        echo "Historical date meaning: UNCONFIRMED.\n";
    } catch (Throwable $exception) {
        fwrite(
            STDERR,
            "[STOP] "
                . $exception->getMessage()
                . "\nNo archive writes from this run committed.\n"
        );

        exit(1);
    }
}
