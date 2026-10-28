<?php

declare(strict_types=1);

/**
 * IFT Academy - Selected Legacy History Import
 *
 * Read-only:
 * php scripts/import-selected-history.php
 *
 * Apply:
 * php scripts/import-selected-history.php --apply
 *
 * No migrations or new payment transactions.
 */

use Framework\Config;
use Framework\Database;

const IFT_SNAPSHOT = '20261027T125036Z_b2249516';
const IFT_ARCHIVE = '20261026T153420Z_36410347';
const IFT_FOLDER = 'C:\\ift-private\\selected-history';

// Fingerprints of the original verified CSV files.
const IFT_SHA = [
    'selected_students.csv' =>
    '8be6fe2b3fb6489e24caa624c7bdcc88df0769972d7483bced67c987ff381ca0',
    'payments.csv' =>
    '386513c3a87b25fccad5cc88f4651d475c9798879c2f277e882b0599ca1ddc6d',
    'attendances.csv' =>
    '609739fc0bd7fcecab2627e98bd63910d4ba24b3f16ef4015dc3794f3b05b699',
];

// names.txt request number => retained database student ID.
// Never interpret old source_list_no as a database ID.
const IFT_STUDENTS = [
    1 => 20,
    2 => 50,
    3 => 80,
    4 => 178,
    8 => 78,
    9 => 138,
    10 => 236,
    11 => 129,
    12 => 147,
    13 => 350,
    14 => 135,
    17 => 110,
    18 => 186,
    19 => 177,
    20 => 332,
    21 => 47,
    24 => 48,
    25 => 180,
    26 => 136,
    27 => 153,
    28 => 155,
    29 => 45,
    32 => 62,
    33 => 330,
    34 => 160,
    35 => 149,
    38 => 309,
    39 => 60,
    40 => 70,
    41 => 44,
    43 => 375,
    45 => 49,
    48 => 139,
    49 => 196,
    50 => 115,
    52 => 362,
    53 => 184,
];

function requireTrue(bool $condition, string $reason): void
{
    if (!$condition) {
        throw new RuntimeException($reason);
    }
}

function folded(?string $value): string
{
    $value = trim(
        preg_replace('/\s+/u', ' ', $value ?? '') ?? ''
    );

    return function_exists('mb_strtolower')
        ? mb_strtolower($value, 'UTF-8')
        : strtolower($value);
}

function identity(array $student): string
{
    return implode('|', [
        folded($student['first_name'] ?? ''),
        folded($student['last_name'] ?? ''),
        folded($student['email'] ?? ''),
    ]);
}

function rawPayment(array $row): string
{
    return json_encode([
        (string) ($row['Ημερομηνία'] ?? $row['date_raw'] ?? ''),
        (string) ($row['Χρέωση'] ?? $row['charge_raw'] ?? ''),
        (string) ($row['Πληρωμή'] ?? $row['paid_raw'] ?? ''),
        (string) ($row['Υπόλοιπο'] ?? $row['balance_raw'] ?? ''),
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function signatureBag(array $rows): array
{
    $values = array_map('rawPayment', $rows);
    sort($values, SORT_STRING);

    return $values;
}

function readCsv(string $name): array
{
    $path = IFT_FOLDER . DIRECTORY_SEPARATOR . $name;

    requireTrue(
        is_file($path) && is_readable($path),
        "Δεν βρέθηκε το {$path}"
    );

    requireTrue(
        hash_equals(
            IFT_SHA[$name],
            hash_file('sha256', $path)
        ),
        "Το {$name} διαφέρει από το αρχικό ελεγμένο CSV."
    );

    $handle = fopen($path, 'rb');

    requireTrue(
        $handle !== false,
        "Δεν ανοίγει το {$name}"
    );

    try {
        $columns = fgetcsv(
            $handle,
            0,
            ',',
            '"',
            ''
        );

        requireTrue(
            is_array($columns) &&
                count($columns) === count(array_unique($columns)),
            "Λάθος στήλες στο {$name}"
        );

        $columns[0] = preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            $columns[0]
        );

        $required = match ($name) {
            'selected_students.csv' => [
                'snapshot_id',
                'request_id',
                'source_list_no',
                'first_name',
                'last_name',
                'email',
                'phone',
                'payments_state',
                'attendances_state',
                'payments_exported_rows',
                'attendances_exported_rows',
            ],
            'payments.csv' => [
                'snapshot_id',
                'request_id',
                'source_list_no',
                'raw_json',
                'history_complete',
            ],
            default => [
                'snapshot_id',
                'request_id',
                'source_list_no',
                'date_raw',
                'date_iso',
                'datetime_local',
                'history_complete',
            ],
        };

        requireTrue(
            array_diff($required, $columns) === [],
            "Λείπουν στήλες από {$name}"
        );

        $rows = [];

        while (
            ($values = fgetcsv(
                $handle,
                0,
                ',',
                '"',
                ''
            )) !== false
        ) {
            if ($values === [null]) {
                continue;
            }

            requireTrue(
                count($values) === count($columns),
                "Λανθασμένη γραμμή στο {$name}"
            );

            $row = array_combine($columns, $values);

            requireTrue(
                $row['snapshot_id'] === IFT_SNAPSHOT,
                "Ανακατεμένες εξαγωγές στο {$name}"
            );

            $rows[] = $row;
        }

        return $rows;
    } finally {
        fclose($handle);
    }
}

function groupsFromCsv(): array
{
    $headers = readCsv('selected_students.csv');
    $payments = readCsv('payments.csv');
    $attendances = readCsv('attendances.csv');

    $profiles = [];

    foreach ($headers as $row) {
        $request = $row['request_id'];

        requireTrue(
            !isset($profiles[$request]),
            "Επαναλαμβανόμενο request_id {$request}"
        );

        $profiles[$request] = $row + [
            'payments' => [],
            'attendances' => [],
        ];
    }

    foreach (
        [
            ['payments', $payments],
            ['attendances', $attendances],
        ] as [$kind, $rows]
    ) {
        foreach ($rows as $row) {
            $request = $row['request_id'];

            requireTrue(
                isset($profiles[$request]) &&
                    $row['source_list_no'] ===
                    $profiles[$request]['source_list_no'] &&
                    $row['history_complete'] === '1',
                "Ελλιπές ή ασύνδετο ιστορικό {$request}"
            );

            $profiles[$request][$kind][] = $row;
        }
    }

    foreach ($profiles as $row) {
        requireTrue(
            count($row['payments']) ===
                (int) $row['payments_exported_rows'] &&
                count($row['attendances']) ===
                (int) $row['attendances_exported_rows'],
            "Λάθος πλήθος στην καρτέλα {$row['request_id']}"
        );
    }

    $groups = [];
    $sourceNos = [];

    foreach ($profiles as $row) {
        $request = $row['request_id'];

        if (!preg_match(
            '/^q([0-9]+)_old([0-9]+)$/D',
            $request,
            $matches
        )) {
            continue;
        }

        $parent = (int) $matches[1];

        if (!isset(IFT_STUDENTS[$parent])) {
            continue;
        }

        requireTrue(
            $row['payments_state'] === 'complete' &&
                $row['attendances_state'] === 'complete',
            "Incomplete {$request}"
        );

        requireTrue(
            (string) $matches[2] === $row['source_list_no'] &&
                !isset($sourceNos[$matches[2]]),
            "Διπλό παλιό No {$request}"
        );

        $sourceNos[$matches[2]] = true;

        $groups[$parent][] = $row;
    }

    requireTrue(
        count($groups) === 37,
        'Δεν υπάρχουν 37 ολοκληρωμένοι μαθητές.'
    );

    $canonical = [];

    $paymentCount = 0;
    $attendanceCount = 0;
    $copiesCount = 0;

    foreach (IFT_STUDENTS as $request => $studentId) {
        requireTrue(
            isset($groups[$request]),
            "Λείπει η επιλογή {$request}"
        );

        usort(
            $groups[$request],
            static fn($a, $b) =>
            (int) $a['source_list_no'] <=>
                (int) $b['source_list_no']
        );

        $copies = $groups[$request];
        $first = $copies[0];

        $rawPayments = array_map(
            static fn($row) => json_decode(
                $row['raw_json'],
                true,
                512,
                JSON_THROW_ON_ERROR
            ),
            $first['payments']
        );

        $paymentBag = signatureBag($rawPayments);

        $studentIdentity = identity($first);
        $attendanceTimes = [];

        foreach ($first['attendances'] as $row) {
            $local = $row['datetime_local'];

            $date = DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i:s',
                $local,
                new DateTimeZone('Europe/Athens')
            );

            requireTrue(
                $date !== false &&
                    $date->format('Y-m-d H:i:s') === $local &&
                    $date->format('Y-m-d') === $row['date_iso'],
                "Λάθος ώρα παρουσίας {$studentId}"
            );

            $utc = $date
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:00');

            requireTrue(
                !isset($attendanceTimes[$utc]),
                "Διπλή παρουσία ίδιας στιγμής {$studentId}"
            );

            $attendanceTimes[$utc] = true;
        }

        foreach ($copies as $copy) {
            requireTrue(
                identity($copy) === $studentIdentity &&
                    $copy['phone'] === $first['phone'],
                "Διαφορετική ταυτότητα στην επιλογή {$request}"
            );

            $otherPayments = array_map(
                static fn($row) => json_decode(
                    $row['raw_json'],
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                ),
                $copy['payments']
            );

            requireTrue(
                signatureBag($otherPayments) === $paymentBag,
                "Οι διπλές καρτέλες έχουν διαφορετικές πληρωμές {$request}"
            );

            $otherAttendance = array_map(
                static fn($row) => $row['datetime_local'],
                $copy['attendances']
            );

            $firstAttendance = array_map(
                static fn($row) => $row['datetime_local'],
                $first['attendances']
            );

            sort($otherAttendance);
            sort($firstAttendance);

            requireTrue(
                $otherAttendance === $firstAttendance,
                "Οι διπλές καρτέλες έχουν διαφορετικές παρουσίες {$request}"
            );
        }

        $canonical[$studentId] = [
            'head' => $first,
            'copies' => $copies,
            'bag' => $paymentBag,
            'attendance' => array_keys($attendanceTimes),
        ];

        $copiesCount += count($copies);
        $paymentCount += count($paymentBag);
        $attendanceCount += count($attendanceTimes);
    }

    requireTrue(
        $copiesCount === 51 &&
            $paymentCount === 367 &&
            $attendanceCount === 2638,
        'Τα συνολικά πλήθη δεν συμφωνούν (51/367/2638).'
    );

    return $canonical;
}

function buildPlan(Database $db, array $groups): array
{
    // IDs originate only from the fixed verified mapping above.
    $ids = implode(',', array_keys($groups));

    $students = $db->query(
        "SELECT *
         FROM students
         WHERE id IN ({$ids})
         ORDER BY id
         FOR UPDATE"
    )->fetchAll();

    requireTrue(
        count($students) === 37,
        'Δεν υπάρχουν οι 37 κύριοι μαθητές στη βάση.'
    );

    $selectedIdentities = [];

    foreach ($students as $student) {
        $id = (int) $student['id'];
        $head = $groups[$id]['head'];

        requireTrue(
            (int) $student['legacy_id'] === $id &&
                identity($student) === identity($head) &&
                (string) $student['phone'] === $head['phone'],
            "Τα στοιχεία της βάσης διαφέρουν στο ID {$id}."
        );

        $key = identity($student);

        $selectedIdentities[$key] =
            ($selectedIdentities[$key] ?? 0) + 1;
    }

    requireTrue(
        count($selectedIdentities) === 37,
        'Οι επιλεγμένοι μαθητές δεν είναι μοναδικοί.'
    );

    // Recheck that the duplicate student cards were removed.
    $identityCounts = [];

    $allStudents = $db->query(
        'SELECT first_name, last_name, email
         FROM students'
    )->fetchAll();

    foreach ($allStudents as $student) {
        $key = identity($student);

        if (isset($selectedIdentities[$key])) {
            $identityCounts[$key] =
                ($identityCounts[$key] ?? 0) + 1;
        }
    }

    foreach ($selectedIdentities as $key => $count) {
        requireTrue(
            ($identityCounts[$key] ?? 0) === 1,
            'Παραμένει δεύτερη καρτέλα με ίδια στοιχεία στη βάση.'
        );
    }

    $archive = $db->query(
        'SELECT *
         FROM legacy_payment_archives
         ORDER BY imported_at_utc DESC, id DESC
         LIMIT 1
         FOR UPDATE'
    )->fetch();

    requireTrue(
        is_array($archive) &&
            $archive['snapshot_id'] === IFT_ARCHIVE,
        'Δεν βρέθηκε η αναμενόμενη τελευταία αρχειοθέτηση πληρωμών.'
    );

    $profiles = $db->query(
        'SELECT *
         FROM legacy_payment_profiles
         WHERE archive_id = :archive
         ORDER BY id
         FOR UPDATE',
        ['archive' => $archive['id']]
    )->fetchAll();

    $index = [];
    $linked = [];

    foreach ($profiles as $profile) {
        $sourceNo = (string) $profile['source_list_no'];

        requireTrue(
            !isset($index[$sourceNo]),
            "Διπλό source No στο αρχείο: {$sourceNo}"
        );

        $index[$sourceNo] = $profile;

        if ($profile['proposed_student_id'] !== null) {
            $linked[(int) $profile['proposed_student_id']][] = $sourceNo;
        }
    }

    $changes = [];
    $flagUpdates = [];

    $canonicalCount = 0;
    $profileCount = 0;

    foreach ($groups as $id => $group) {
        $sourceNos = array_map(
            static fn($copy) => $copy['source_list_no'],
            $group['copies']
        );

        foreach (($linked[$id] ?? []) as $sourceNo) {
            requireTrue(
                in_array($sourceNo, $sourceNos, true),
                "Υπάρχει πρόσθετο παλιό προφίλ στο ID {$id}."
            );
        }

        foreach ($group['copies'] as $offset => $copy) {
            $sourceNo = $copy['source_list_no'];

            requireTrue(
                isset($index[$sourceNo]),
                "Δεν υπάρχει παλιό προφίλ No {$sourceNo}"
            );

            $profile = $index[$sourceNo];

            $rawProfile = json_decode(
                $profile['source_profile_json'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            requireTrue(
                identity($rawProfile) === identity($copy),
                "Διαφορά ιστορικού προφίλ No {$sourceNo}"
            );

            requireTrue(
                $profile['proposed_student_id'] === null ||
                    (int) $profile['proposed_student_id'] === $id,
                "Το παλιό No {$sourceNo} συνδέεται σε άλλο μαθητή."
            );

            $rows = $db->query(
                'SELECT *
                 FROM legacy_payment_history
                 WHERE profile_id = :profile
                 ORDER BY id
                 FOR UPDATE',
                ['profile' => $profile['id']]
            )->fetchAll();

            $bag = [];

            foreach ($rows as $row) {
                $original = json_decode(
                    $row['source_record_json'],
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                $bag[] = rawPayment($original);

                if ($offset === 0) {
                    $flags = preg_split(
                        '/[;|]/',
                        (string) ($row['review_flags'] ?? '')
                    );

                    $flags = array_values(array_filter(
                        array_map('trim', $flags),
                        static fn($flag) =>
                        $flag !== '' &&
                            $flag !== 'unresolved_student_mapping'
                    ));

                    $newFlags = $flags
                        ? implode(';', array_unique($flags))
                        : null;

                    if ($newFlags !== $row['review_flags']) {
                        $flagUpdates[] = [
                            'id' => $row['id'],
                            'flags' => $newFlags,
                        ];
                    }
                }
            }

            sort($bag, SORT_STRING);

            requireTrue(
                $bag === $group['bag'],
                "Τα ποσά ή οι ημερομηνίες διαφέρουν στο No {$sourceNo}"
            );

            $profileCount++;

            if ($offset === 0) {
                $canonicalCount += count($rows);
            }

            $status = $offset === 0
                ? 'unique_identity_and_legacy_verified'
                : 'duplicate_profile_retained';

            $state = $offset === 0
                ? 'candidate'
                : 'review';

            $wantedId = $offset === 0
                ? $id
                : null;

            $currentId =
                $profile['proposed_student_id'] === null
                ? null
                : (int) $profile['proposed_student_id'];

            if (
                $currentId !== $wantedId ||
                $profile['link_state'] !== $state ||
                $profile['source_mapping_status'] !== $status
            ) {
                $changes[] = [
                    'id' => $profile['id'],
                    'student' => $wantedId,
                    'state' => $state,
                    'status' => $status,
                    'note' => $offset === 0
                        ? 'Verified selected export 20261027. Historical date remains unconfirmed.'
                        : 'Identical duplicate preserved unlinked; original history retained.',
                ];
            }
        }
    }

    requireTrue(
        $profileCount === 51 &&
            $canonicalCount === 367,
        'Λάθος καταμέτρηση αρχειοθετημένων πληρωμών.'
    );

    $attendanceRows = $db->query(
        "SELECT student_id, attended_at_utc
         FROM attendances
         WHERE student_id IN ({$ids})
         ORDER BY id
         FOR UPDATE"
    )->fetchAll();

    $present = [];

    foreach ($attendanceRows as $row) {
        $key = (int) $row['student_id']
            . '|'
            . substr($row['attended_at_utc'], 0, 16);

        $present[$key] = true;
    }

    $missing = [];
    $existing = 0;

    foreach ($groups as $id => $group) {
        foreach ($group['attendance'] as $utc) {
            $key = $id . '|' . substr($utc, 0, 16);

            if (isset($present[$key])) {
                $existing++;
            } else {
                $missing[] = [
                    'id' => $id,
                    'utc' => $utc,
                ];
            }
        }
    }

    requireTrue(
        count($missing) + $existing === 2638,
        'Δεν συμφωνούν οι παρουσίες.'
    );

    return [
        'archive' => (int) $archive['id'],
        'changes' => $changes,
        'flags' => $flagUpdates,
        'missing' => $missing,
        'existing' => $existing,
    ];
}

function main(array $argv): int
{
    try {
        requireTrue(
            PHP_SAPI === 'cli',
            'Το script εκτελείται μόνο από CMD.'
        );

        requireTrue(
            count($argv) <= 2 &&
                (
                    !isset($argv[1]) ||
                    $argv[1] === '--apply'
                ),
            'Χρήση: php scripts/import-selected-history.php [--apply]'
        );

        $apply = ($argv[1] ?? '') === '--apply';

        require dirname(__DIR__) . '/vendor/autoload.php';

        requireTrue(
            Config::get('app.environment') === 'local',
            'Επιτρέπεται μόνο στο local περιβάλλον.'
        );

        $config = Config::get('database');

        requireTrue(
            in_array(
                $config['host'] ?? '',
                ['localhost', '127.0.0.1', '::1'],
                true
            ),
            'Επιτρέπεται μόνο τοπική MySQL.'
        );

        $groups = groupsFromCsv();

        echo "[CSV] 37 μαθητές, 51 παλιές καρτέλες, "
            . "367 ιστορικές πληρωμές, 2638 παρουσίες.\n";

        $db = new Database($config);

        requireTrue(
            $db->query('SELECT DATABASE()')->fetchColumn()
                === 'ift_academy',
            'Λάθος βάση δεδομένων.'
        );

        requireTrue(
            (int) $db->query(
                'SELECT @@FOREIGN_KEY_CHECKS'
            )->fetchColumn() === 1,
            'Τα foreign keys πρέπει να είναι ενεργά.'
        );

        $result = $db->transaction(
            static function (Database $db) use (
                $apply,
                $groups
            ): string {
                $plan = buildPlan($db, $groups);

                echo '[DB] Παρουσίες ήδη στη βάση: '
                    . $plan['existing']
                    . ' | λείπουν: '
                    . count($plan['missing'])
                    . "\n";

                echo '[DB] Συνδέσεις προφίλ προς ενημέρωση: '
                    . count($plan['changes'])
                    . ' | επισημάνσεις προς ενημέρωση: '
                    . count($plan['flags'])
                    . "\n";

                if (!$apply) {
                    return 'READY_TO_APPLY';
                }

                foreach ($plan['changes'] as $row) {
                    $db->query(
                        'UPDATE legacy_payment_profiles
                         SET proposed_student_id = :student,
                             link_state = :state,
                             source_mapping_status = :status,
                             verification_note = :note
                         WHERE id = :id',
                        $row
                    );
                }

                foreach ($plan['flags'] as $row) {
                    $db->query(
                        'UPDATE legacy_payment_history
                         SET review_flags = :flags
                         WHERE id = :id',
                        $row
                    );
                }

                $linkedCount = (int) $db->query(
                    "SELECT COUNT(*)
                     FROM legacy_payment_profiles
                     WHERE archive_id = :archive
                       AND link_state = 'candidate'
                       AND proposed_student_id IS NOT NULL",
                    ['archive' => $plan['archive']]
                )->fetchColumn();

                $db->query(
                    'UPDATE legacy_payment_archives
                     SET proposed_links_count = :count
                     WHERE id = :archive',
                    [
                        'count' => $linkedCount,
                        'archive' => $plan['archive'],
                    ]
                );

                foreach ($plan['missing'] as $attendance) {
                    $db->query(
                        "INSERT INTO attendances (
                            student_id,
                            legacy_id,
                            attended_at_utc,
                            source
                         ) VALUES (
                            :id,
                            NULL,
                            :utc,
                            'import'
                         )",
                        $attendance
                    );
                }

                // Repeat all checks before committing.
                $after = buildPlan($db, $groups);

                requireTrue(
                    $after['changes'] === [] &&
                        $after['flags'] === [] &&
                        $after['missing'] === [],
                    'Ο τελικός έλεγχος απέτυχε. Η συναλλαγή αναιρείται.'
                );

                return 'DONE';
            }
        );

        echo "[{$result}] ";

        echo $apply
            ? "Η συναλλαγή ολοκληρώθηκε.\n"
            : "Δεν άλλαξαν δεδομένα.\n";

        return 0;
    } catch (Throwable $exception) {
        fwrite(
            STDERR,
            '[STOP] '
                . $exception->getMessage()
                . "\n"
        );

        return 1;
    }
}

exit(main($argv));
