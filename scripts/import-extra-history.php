<?php

declare(strict_types=1);

/**
 * IFT extra CSV reconciliation.
 * Dry run by default. --apply saves changes.
 * CLI only. Never place this file in public/.
 */

use Framework\Config;
use Framework\Database;

const EXTRA_DIR = 'C:\\ift-private\\selected-history';
const EXTRA_ARCHIVE = '20261026T153420Z_36410347';
const EXTRA_TABLE = 'legacy_extra_csv_payments';

function must(bool $ok, string $why): void
{
    if (!$ok) {
        throw new RuntimeException($why);
    }
}

function cleanName(string $s): string
{
    $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);

    $s = function_exists('mb_strtoupper')
        ? mb_strtoupper($s, 'UTF-8')
        : strtr($s, [
            'α' => 'Α',
            'β' => 'Β',
            'γ' => 'Γ',
            'δ' => 'Δ',
            'ε' => 'Ε',
            'ζ' => 'Ζ',
            'η' => 'Η',
            'θ' => 'Θ',
            'ι' => 'Ι',
            'κ' => 'Κ',
            'λ' => 'Λ',
            'μ' => 'Μ',
            'ν' => 'Ν',
            'ξ' => 'Ξ',
            'ο' => 'Ο',
            'π' => 'Π',
            'ρ' => 'Ρ',
            'σ' => 'Σ',
            'ς' => 'Σ',
            'τ' => 'Τ',
            'υ' => 'Υ',
            'φ' => 'Φ',
            'χ' => 'Χ',
            'ψ' => 'Ψ',
            'ω' => 'Ω',
            'ά' => 'Ά',
            'έ' => 'Έ',
            'ή' => 'Ή',
            'ί' => 'Ί',
            'ό' => 'Ό',
            'ύ' => 'Ύ',
            'ώ' => 'Ώ'
        ]);

    return strtr($s, [
        'Ά' => 'Α',
        'Έ' => 'Ε',
        'Ή' => 'Η',
        'Ί' => 'Ι',
        'Ϊ' => 'Ι',
        'Ό' => 'Ο',
        'Ύ' => 'Υ',
        'Ϋ' => 'Υ',
        'Ώ' => 'Ω',
        'ϊ' => 'Ι',
        'ΐ' => 'Ι',
        'ϋ' => 'Υ',
        'ΰ' => 'Υ'
    ]);
}

function fullName(string $first, string $last): string
{
    return cleanName($first) . '|' . cleanName($last);
}

function csvRead(string $file, array $headers): array
{
    $path = EXTRA_DIR . DIRECTORY_SEPARATOR . $file;

    must(is_readable($path), 'Λείπει το CSV: ' . $path);

    $h = fopen($path, 'rb');
    must($h !== false, 'Αδυναμία ανάγνωσης ' . $file);

    try {
        $head = fgetcsv($h, 0, ',', '"', '');
        must(is_array($head), 'Κενό CSV: ' . $file);

        $head[0] = preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            $head[0]
        );

        must($head === $headers, 'Λάθος στήλες: ' . $file);

        $out = [];

        while (($values = fgetcsv($h, 0, ',', '"', '')) !== false) {
            if ($values === [null]) {
                continue;
            }

            must(
                count($values) === count($head),
                'Χαλασμένη γραμμή: ' . $file
            );

            $out[] = array_combine($head, $values);
        }

        return $out;
    } finally {
        fclose($h);
    }
}

function amount(string $s): string
{
    $s = str_replace(',', '.', trim($s));

    must(
        preg_match(
            '/\A-?[0-9]{1,10}(?:\.[0-9]{1,2})?\z/D',
            $s
        ) === 1,
        'Μη αναγνωρίσιμο ποσό: ' . $s
    );

    $negative = str_starts_with($s, '-');

    [$a, $b] = array_pad(
        explode('.', $negative ? substr($s, 1) : $s, 2),
        2,
        ''
    );

    $a = ltrim($a, '0');
    $a = $a === '' ? '0' : $a;

    return (
        $negative && ($a !== '0' || (int) $b !== 0)
        ? '-'
        : ''
    ) . $a . '.' . str_pad($b, 2, '0');
}

function greekDate(
    string $raw,
    bool $allowUnknown = false
): ?DateTimeImmutable {
    if ($raw === 'Invalid date' && $allowUnknown) {
        return null;
    }

    $months = [
        'Ιαν' => 1,
        'Φεβ' => 2,
        'Μαρ' => 3,
        'Απρ' => 4,
        'Μαϊ' => 5,
        'Ιουν' => 6,
        'Ιουλ' => 7,
        'Αυγ' => 8,
        'Σεπ' => 9,
        'Οκτ' => 10,
        'Νοε' => 11,
        'Δεκ' => 12
    ];

    $days = [
        'Δευ' => 1,
        'Τρι' => 2,
        'Τετ' => 3,
        'Πεμ' => 4,
        'Παρ' => 5,
        'Σαβ' => 6,
        'Κυρ' => 7
    ];

    must(
        preg_match(
            '/\A(Δευ|Τρι|Τετ|Πεμ|Παρ|Σαβ|Κυρ),\s*'
                . '(\d{1,2})\s+(\p{L}+)\s+(\d{4})\s+'
                . '(\d{1,2}):(\d{2})\s+(ΠΜ|ΜΜ)\z/u',
            trim($raw),
            $m
        ) === 1,
        'Λάθος ημερομηνία: ' . $raw
    );

    $mon = $months[$m[3]] ?? 0;
    $d = (int) $m[2];
    $y = (int) $m[4];
    $hr = (int) $m[5];
    $min = (int) $m[6];

    must(
        checkdate($mon, $d, $y)
            && $hr >= 1
            && $hr <= 12
            && $min <= 59,
        'Άκυρη ημερομηνία: ' . $raw
    );

    $hr = ($hr % 12) + ($m[7] === 'ΜΜ' ? 12 : 0);

    $str = sprintf(
        '%04d-%02d-%02d %02d:%02d:00',
        $y,
        $mon,
        $d,
        $hr,
        $min
    );

    $dt = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        $str,
        new DateTimeZone('Europe/Athens')
    );

    must(
        $dt !== false
            && $dt->format('Y-m-d H:i:s') === $str
            && (int) $dt->format('N') === $days[$m[1]],
        'Ασυμφωνία ημερομηνίας: ' . $raw
    );

    return $dt;
}

function payKey(
    ?string $date,
    string $charged,
    string $paid
): string {
    return ($date ?? 'INVALID')
        . '|' . amount($charged)
        . '|' . amount($paid);
}

function addCount(array &$counts, string $key): void
{
    $counts[$key] = ($counts[$key] ?? 0) + 1;
}

function useCount(array &$counts, string $key): bool
{
    if (($counts[$key] ?? 0) < 1) {
        return false;
    }

    $counts[$key]--;
    return true;
}

function main(array $args): int
{
    $apply = count($args) === 2 && $args[1] === '--apply';

    $db = null;
    $locked = false;

    try {
        must(PHP_SAPI === 'cli', 'CLI μόνο.');

        must(
            count($args) === 1 || $apply,
            'Χρήση: php scripts/import-extra-history.php [--apply]'
        );

        require dirname(__DIR__) . '/vendor/autoload.php';

        must(
            Config::get('app.environment') === 'local',
            'Μόνο στο local περιβάλλον.'
        );

        $conf = Config::get('database');

        must(
            in_array(
                $conf['host'] ?? '',
                ['localhost', '127.0.0.1', '::1'],
                true
            ),
            'Μόνο τοπική βάση.'
        );

        $attendance = csvRead(
            'parousies.csv',
            ['Όνομα', 'Επώνυμο', 'Ημερομηνία']
        );

        $payments = csvRead(
            'pliromes_cleaned.csv',
            [
                'Όνομα',
                'Επώνυμο',
                'Ημερομηνία',
                'Χρέωση',
                'Πληρωμή'
            ]
        );

        must(
            count($attendance) === 1532
                && count($payments) === 247,
            'Απροσδόκητα πλήθη CSV.'
        );

        $groups = [];

        foreach ($attendance as $r) {
            $key = fullName($r['Όνομα'], $r['Επώνυμο']);

            $dt = greekDate($r['Ημερομηνία']);

            $utc = $dt
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:00');

            must(
                !isset($groups[$key]['att'][$utc]),
                'Διπλή στιγμή παρουσίας στο CSV: ' . $key
            );

            $groups[$key]['label'] = trim(
                $r['Όνομα'] . ' ' . $r['Επώνυμο']
            );

            $groups[$key]['att'][$utc] = 1;
        }

        foreach ($payments as $r) {
            $key = fullName($r['Όνομα'], $r['Επώνυμο']);

            $dt = greekDate($r['Ημερομηνία'], true);
            $date = $dt?->format('Y-m-d');

            $pay = payKey(
                $date,
                $r['Χρέωση'],
                $r['Πληρωμή']
            );

            $groups[$key]['label'] = trim(
                $r['Όνομα'] . ' ' . $r['Επώνυμο']
            );

            $groups[$key]['pay'][] = [
                'key' => $pay,
                'date' => $date,
                'raw' => $r['Ημερομηνία'],
                'charge' => amount($r['Χρέωση']),
                'paid' => amount($r['Πληρωμή'])
            ];
        }

        must(
            count($groups) === 23,
            'Δεν βρέθηκαν ακριβώς 23 ονοματεπώνυμα.'
        );

        $db = new Database($conf);

        must(
            $db->query('SELECT DATABASE()')->fetchColumn()
                === 'ift_academy',
            'Λάθος βάση.'
        );

        must(
            (int) $db->query(
                'SELECT @@FOREIGN_KEY_CHECKS'
            )->fetchColumn() === 1,
            'Foreign keys OFF.'
        );

        $locked = (int) $db->query(
            'SELECT GET_LOCK(:k,0)',
            ['k' => 'ift_extra_csv_reconcile_20261028']
        )->fetchColumn() === 1;

        must($locked, 'Τρέχει ήδη άλλη εισαγωγή.');

        $db->query("SET time_zone = '+00:00'");

        $exists = (int) $db->query(
            'SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = :t',
            ['t' => EXTRA_TABLE]
        )->fetchColumn() === 1;

        if ($apply && !$exists) {
            // DDL happens before the data transaction.
            $db->query(
                "CREATE TABLE legacy_extra_csv_payments (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    student_id BIGINT UNSIGNED NOT NULL,
                    row_key CHAR(64)
                        CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                    source_date_text VARCHAR(160) NOT NULL,
                    source_record_date DATE NULL,
                    source_charged_amount DECIMAL(12,2) NOT NULL,
                    source_paid_amount DECIMAL(12,2) NOT NULL,
                    date_meaning VARCHAR(20) NOT NULL
                        DEFAULT 'unconfirmed',
                    imported_at_utc DATETIME(6) NOT NULL,
                    UNIQUE KEY uq_extra_row (row_key),
                    INDEX idx_extra_student (
                        student_id,
                        source_record_date
                    ),
                    CONSTRAINT fk_extra_student
                        FOREIGN KEY (student_id)
                        REFERENCES students(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB
                DEFAULT CHARSET=utf8mb4
                COLLATE=utf8mb4_unicode_ci"
            );

            $exists = true;
        }

        $result = $db->transaction(
            static function (Database $db) use (
                $groups,
                $apply,
                $exists
            ): array {
                $students = $db->query(
                    'SELECT id, first_name, last_name
                     FROM students ORDER BY id'
                )->fetchAll();

                $byName = [];

                foreach ($students as $s) {
                    $byName[fullName(
                        (string) $s['first_name'],
                        (string) $s['last_name']
                    )][] = (int) $s['id'];
                }

                $ids = [];
                $skipped = [];

                foreach ($groups as $name => $g) {
                    $found = $byName[$name] ?? [];

                    if (count($found) !== 1) {
                        $skipped[] = $g['label'] . ' ('
                            . (count($found) === 0
                                ? 'δεν βρέθηκε'
                                : 'περισσότερες καρτέλες')
                            . ')';
                        continue;
                    }

                    $ids[$name] = $found[0];
                }

                $idList = array_values($ids);

                foreach ($idList as $id) {
                    $db->query(
                        'SELECT id FROM students
                         WHERE id = :i FOR UPDATE',
                        ['i' => $id]
                    );
                }

                $old = $db->query(
                    "SELECT p.proposed_student_id AS student_id,
                            p.link_state,
                            p.source_profile_json,
                            h.source_record_date,
                            h.source_charged_amount,
                            h.source_paid_amount
                     FROM legacy_payment_archives a
                     JOIN legacy_payment_profiles p
                       ON p.archive_id = a.id
                     JOIN legacy_payment_history h
                       ON h.profile_id = p.id
                     WHERE a.snapshot_id = :snap",
                    ['snap' => EXTRA_ARCHIVE]
                )->fetchAll();

                $linked = [];
                $unlinked = [];

                foreach ($old as $r) {
                    if (
                        $r['source_charged_amount'] === null
                        || $r['source_paid_amount'] === null
                    ) {
                        continue;
                    }

                    $date = $r['source_record_date'];

                    $k = payKey(
                        $date,
                        (string) $r['source_charged_amount'],
                        (string) $r['source_paid_amount']
                    );

                    if (
                        $r['student_id'] !== null
                        && $r['link_state'] === 'candidate'
                    ) {
                        $sid = (int) $r['student_id'];
                        $linked[$sid] ??= [];

                        addCount($linked[$sid], $k);
                    } else {
                        $who = json_decode(
                            (string) $r['source_profile_json'],
                            true
                        );

                        if (is_array($who)) {
                            $name = fullName(
                                (string) ($who['first_name'] ?? ''),
                                (string) ($who['last_name'] ?? '')
                            );

                            $unlinked[$name] ??= [];
                            addCount($unlinked[$name], $k);
                        }
                    }
                }

                $supp = [];

                if ($exists) {
                    $saved = $db->query(
                        'SELECT student_id,
                                source_record_date,
                                source_charged_amount,
                                source_paid_amount
                         FROM legacy_extra_csv_payments'
                    )->fetchAll();

                    foreach ($saved as $r) {
                        $sid = (int) $r['student_id'];
                        $supp[$sid] ??= [];

                        addCount(
                            $supp[$sid],
                            payKey(
                                $r['source_record_date'],
                                (string) $r['source_charged_amount'],
                                (string) $r['source_paid_amount']
                            )
                        );
                    }
                }

                $existingAtt = [];

                if ($ids) {
                    $marks = implode(
                        ',',
                        array_map('intval', $idList)
                    );

                    $savedAttendance = $db->query(
                        "SELECT student_id, attended_at_utc
                         FROM attendances
                         WHERE student_id IN ($marks)
                         FOR UPDATE"
                    )->fetchAll();

                    foreach ($savedAttendance as $a) {
                        $key = (int) $a['student_id']
                            . '|'
                            . substr(
                                (string) $a['attended_at_utc'],
                                0,
                                16
                            );

                        $existingAtt[$key] = true;
                    }
                }

                $newAtt = [];
                $newPay = [];
                $alreadyAtt = 0;
                $oldPay = 0;
                $heldPay = 0;

                foreach ($ids as $name => $sid) {
                    $linked[$sid] ??= [];
                    $supp[$sid] ??= [];
                    $unlinked[$name] ??= [];

                    foreach (
                        array_keys(
                            $groups[$name]['att'] ?? []
                        ) as $utc
                    ) {
                        $k = $sid . '|'
                            . substr($utc, 0, 16);

                        if (isset($existingAtt[$k])) {
                            $alreadyAtt++;
                            continue;
                        }

                        $newAtt[] = [
                            'student' => $sid,
                            'utc' => $utc
                        ];

                        $existingAtt[$k] = true;
                    }

                    $occur = [];

                    foreach (
                        $groups[$name]['pay'] ?? [] as $p
                    ) {
                        if (
                            useCount($linked[$sid], $p['key'])
                            || useCount($supp[$sid], $p['key'])
                        ) {
                            $oldPay++;
                            continue;
                        }

                        if (
                            useCount($unlinked[$name], $p['key'])
                        ) {
                            $heldPay++;
                            continue;
                        }

                        $n = ($occur[$p['key']] ?? 0) + 1;
                        $occur[$p['key']] = $n;

                        $newPay[] = [
                            'student' => $sid,
                            'row_key' => hash(
                                'sha256',
                                $sid . "\x1f"
                                    . $p['key'] . "\x1f" . $n
                            ),
                            'raw' => $p['raw'],
                            'date' => $p['date'],
                            'charge' => $p['charge'],
                            'paid' => $p['paid']
                        ];
                    }
                }

                $summary = [
                    'result' => $apply
                        ? 'DONE'
                        : 'READY_TO_APPLY',

                    'matched_students' => count($ids),
                    'skipped_students' => $skipped,

                    'attendance_existing' => $alreadyAtt,
                    'attendance_to_insert' => count($newAtt),

                    'payments_already_stored' => $oldPay,
                    'payments_held_unlinked_archive' => $heldPay,

                    'historical_payments_to_store' =>
                    count($newPay)
                ];

                if (!$apply) {
                    return $summary;
                }

                foreach ($newAtt as $a) {
                    $db->query(
                        "INSERT INTO attendances (
                            student_id,
                            legacy_id,
                            attended_at_utc,
                            source
                         ) VALUES (
                            :student,
                            NULL,
                            :utc,
                            'import'
                         )",
                        $a
                    );
                }

                foreach ($newPay as $p) {
                    $db->query(
                        "INSERT INTO legacy_extra_csv_payments (
                            student_id,
                            row_key,
                            source_date_text,
                            source_record_date,
                            source_charged_amount,
                            source_paid_amount,
                            date_meaning,
                            imported_at_utc
                         ) VALUES (
                            :student,
                            :row_key,
                            :raw,
                            :date,
                            :charge,
                            :paid,
                            'unconfirmed',
                            UTC_TIMESTAMP(6)
                         )",
                        $p
                    );
                }

                return $summary;
            }
        );

        echo json_encode(
            $result,
            JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
        ) . PHP_EOL;

        return 0;
    } catch (Throwable $e) {
        fwrite(
            STDERR,
            '[STOP] ' . $e->getMessage() . PHP_EOL
        );

        return 1;
    } finally {
        if ($locked && $db !== null) {
            try {
                $db->query(
                    'SELECT RELEASE_LOCK(:k)',
                    ['k' => 'ift_extra_csv_reconcile_20261028']
                );
            } catch (Throwable $ignored) {
            }
        }
    }
}

exit(main($argv));
