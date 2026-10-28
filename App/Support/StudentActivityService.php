<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use Framework\Database;
use RuntimeException;

final class StudentActivityService
{
    public static function source(Database $db): array
    {
        $settings = $db->query(
            'SELECT inactivity_value, inactivity_unit
             FROM student_activity_settings
             WHERE id = 1'
        )->fetch();

        $units = [
            'days' => ['DAY', 'ημέρα', 'ημέρες'],
            'months' => ['MONTH', 'μήνας', 'μήνες'],
            'years' => ['YEAR', 'χρόνος', 'χρόνια'],
        ];

        $value = filter_var(
            $settings['inactivity_value'] ?? null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 999
                ]
            ]
        );

        $unit = $settings['inactivity_unit'] ?? '';

        if ($value === false || !isset($units[$unit])) {
            throw new RuntimeException(
                'Invalid or missing student activity settings.'
            );
        }

        $interval = $units[$unit][0];

        $zone = new DateTimeZone('Europe/Athens');

        $today = (
            new DateTimeImmutable('today', $zone)
        )->format('Y-m-d');

        // Χρησιμοποιούμε την πιο πρόσφατη εξαγωγή.
        $archive = $db->query(
            'SELECT id, imported_at_utc
             FROM legacy_payment_archives
             ORDER BY imported_at_utc DESC, id DESC
             LIMIT 1'
        )->fetch();

        $archiveId = is_array($archive)
            ? (int) $archive['id']
            : 0;

        $archiveDay = is_array($archive)
            ? (
                new DateTimeImmutable(
                    $archive['imported_at_utc'],
                    new DateTimeZone('UTC')
                )
            )->setTimezone($zone)->format('Y-m-d')
            : '9999-12-31';

        /*
         * Αρχικό ιστορικό.
         * Χρησιμοποιούνται μόνο θετικές πληρωμές
         * με κατάλληλες, μη μελλοντικές ημερομηνίες.
         */
        $oldRows = "
            SELECT
                h.profile_id,
                h.source_paid_amount,

                CASE
                    WHEN h.source_paid_amount > 0

                     AND h.source_record_date <= :old_cutoff

                     AND (
                         COALESCE(
                             TRIM(h.review_flags), ''
                         ) = ''

                         OR (
                             p.source_mapping_status IN (
                                 'unique_identity_and_legacy_verified',
                                 'selected_history_verified'
                             )

                             AND TRIM(h.review_flags) =
                                 'identical_raw_row_within_profile'
                         )
                     )

                    THEN h.source_record_date
                END AS usable_date

            FROM legacy_payment_history h

            JOIN legacy_payment_profiles p
                ON p.id = h.profile_id

            WHERE p.archive_id = {$archiveId}
        ";

        $oldSummary = "
            SELECT
                profile_id,
                MAX(usable_date) AS legacy_date,

                SUM(
                    CASE
                        WHEN (
                            source_paid_amount IS NULL
                            OR source_paid_amount <> 0
                        )
                        AND usable_date IS NULL
                        THEN 1
                        ELSE 0
                    END
                ) AS blocked_rows

            FROM ({$oldRows}) old_rows

            GROUP BY profile_id
        ";

        /*
         * Συμπληρωματικές ιστορικές πληρωμές CSV.
         * Δεν αποτελούν επιβεβαιωμένες νέες εισπράξεις.
         */
        $extraRows = "
            SELECT
                student_id,
                source_paid_amount,

                CASE
                    WHEN source_paid_amount > 0

                     AND source_record_date <= :extra_cutoff

                     AND date_meaning IN (
                         'unconfirmed',
                         'receipt_date'
                     )

                    THEN source_record_date
                END AS usable_date

            FROM legacy_extra_csv_payments
        ";

        $extraSummary = "
            SELECT
                student_id,

                MAX(usable_date) AS extra_date,

                SUM(
                    CASE
                        WHEN (
                            source_paid_amount IS NULL
                            OR source_paid_amount <> 0
                        )
                        AND usable_date IS NULL
                        THEN 1
                        ELSE 0
                    END
                ) AS blocked_rows

            FROM ({$extraRows}) extra_rows

            GROUP BY student_id
        ";

        /*
         * Συγκέντρωση δεδομένων από όλες τις πηγές.
         */
        $facts = "
            SELECT
                s.id,
                s.legacy_id,
                s.first_name,
                s.last_name,
                s.email,
                s.phone,
                s.contact_phone,

                clock.today,
                clock.archive_day,

                live.last_paid_on AS last_live_paid_on,

                old.legacy_date AS last_legacy_date,

                extra.extra_date AS last_extra_date,

                manual.decided_on AS manual_inactive_on,

                CASE
                    WHEN COALESCE(
                        links.profile_count, 0
                    ) > 1

                    OR (
                        s.legacy_id IS NOT NULL
                        AND links.profile_id IS NULL
                        AND extra.extra_date IS NULL
                    )

                    OR COALESCE(
                        old.blocked_rows, 0
                    ) > 0

                    OR COALESCE(
                        extra.blocked_rows, 0
                    ) > 0

                    THEN 1
                    ELSE 0

                END AS history_problem,

                COALESCE(
                    o.id, 0
                ) AS override_event_id,

                o.mode AS override_mode,
                o.active_until,
                o.reason AS override_reason,

                CASE
                    WHEN o.mode = 'active'
                     AND o.starts_on <= clock.today

                     AND (
                         o.active_until IS NULL
                         OR o.active_until >= clock.today
                     )

                    THEN 1
                    ELSE 0

                END AS override_active

            FROM students s

            CROSS JOIN (
                SELECT
                    CAST(:today AS DATE) AS today,
                    CAST(:archive_day AS DATE) AS archive_day
            ) clock

            /*
             * Χειροκίνητη απόφαση ανενεργού μαθητή.
             */
            LEFT JOIN student_activity_manual_decisions manual
                ON manual.student_id = s.id
               AND manual.decision = 'inactive'
               AND manual.decided_on <= clock.today

            /*
             * Πραγματικές πληρωμές νέας εφαρμογής.
             */
            LEFT JOIN (
                SELECT
                    student_id,
                    MAX(paid_on) AS last_paid_on

                FROM student_payments

                WHERE status = 'posted'
                  AND amount > 0
                  AND paid_on <= :live_cutoff

                GROUP BY student_id

            ) live
                ON live.student_id = s.id

            /*
             * Συνδεδεμένα παλιά προφίλ.
             */
            LEFT JOIN (
                SELECT
                    proposed_student_id,
                    MIN(id) AS profile_id,
                    COUNT(*) AS profile_count

                FROM legacy_payment_profiles

                WHERE archive_id = {$archiveId}
                  AND link_state = 'candidate'

                  AND source_mapping_status IN (
                     'unique_identity_and_legacy_verified',
                     'selected_history_verified'
                  )

                  AND proposed_student_id IS NOT NULL

                GROUP BY proposed_student_id

            ) links
                ON links.proposed_student_id = s.id

            /*
             * Αρχικό ιστορικό.
             */
            LEFT JOIN ({$oldSummary}) old
                ON old.profile_id = links.profile_id
               AND links.profile_count = 1

            /*
             * Συμπληρωματικό ιστορικό.
             */
            LEFT JOIN ({$extraSummary}) extra
                ON extra.student_id = s.id

            /*
             * Χειροκίνητες εξαιρέσεις ενεργού μαθητή.
             */
            LEFT JOIN (
                SELECT
                    student_id,
                    MAX(id) AS event_id

                FROM student_activity_overrides

                GROUP BY student_id

            ) latest
                ON latest.student_id = s.id

            LEFT JOIN student_activity_overrides o
                ON o.id = latest.event_id
        ";

        /*
         * Επιλέγουμε την πιο πρόσφατη κατάλληλη
         * ημερομηνία.
         *
         * Σε ισοπαλία προτιμάται πραγματική πληρωμή.
         */
        $reference = "
            SELECT
                facts.*,

                CASE
                    WHEN last_live_paid_on IS NOT NULL

                     AND (
                         last_legacy_date IS NULL
                         OR last_live_paid_on >= last_legacy_date
                     )

                     AND (
                         last_extra_date IS NULL
                         OR last_live_paid_on >= last_extra_date
                     )

                    THEN last_live_paid_on

                    WHEN last_legacy_date IS NOT NULL

                     AND (
                         last_extra_date IS NULL
                         OR last_legacy_date >= last_extra_date
                     )

                    THEN last_legacy_date

                    ELSE last_extra_date

                END AS reference_on

            FROM ({$facts}) facts
        ";

        /*
         * Υπολογισμός αδράνειας.
         */
        $dated = "
            SELECT
                ref.*,

                DATE_ADD(
                    reference_on,
                    INTERVAL {$value} {$interval}
                ) AS inactive_on,

                CASE
                    WHEN history_problem = 1

                     AND (
                         last_live_paid_on IS NULL
                         OR last_live_paid_on < archive_day
                     )

                    THEN 1
                    ELSE 0

                END AS needs_history_review

            FROM ({$reference}) ref
        ";

        /*
         * Υπολογισμός κατάστασης μαθητή.
         *
         * Προτεραιότητα:
         * 1. Ενεργή χειροκίνητη εξαίρεση
         * 2. Χειροκίνητη απόφαση ανενεργού
         * 3. Νεότερη ημερομηνία πληρωμής
         * 4. Έλεγχος ιστορικού
         */
        $status = "
            SELECT
                dated.*,

                CASE
                    WHEN override_active = 1
                    THEN 'active'

                    WHEN manual_inactive_on IS NOT NULL

                     AND (
                         last_live_paid_on IS NULL
                         OR last_live_paid_on < manual_inactive_on
                     )

                    THEN 'inactive'

                    WHEN inactive_on > today
                    THEN 'active'

                    WHEN needs_history_review = 1
                    THEN 'review'

                    WHEN reference_on IS NULL
                    THEN 'no_payment'

                    WHEN inactive_on IS NULL
                    THEN 'review'

                    ELSE 'inactive'

                END AS activity_status

            FROM ({$dated}) dated
        ";

        /*
         * Προέλευση κατάστασης.
         */
        $sql = "
            SELECT
                result.*,

                reference_on AS last_paid_on,

                CASE
                    WHEN override_active = 1
                    THEN 'manual'

                    WHEN manual_inactive_on IS NOT NULL

                     AND (
                         last_live_paid_on IS NULL
                         OR last_live_paid_on < manual_inactive_on
                     )

                     AND activity_status = 'inactive'

                    THEN 'manual_inactive'

                    WHEN activity_status = 'review'
                    THEN 'review'

                    WHEN reference_on IS NULL
                    THEN 'none'

                    WHEN last_live_paid_on = reference_on
                    THEN 'payment'

                    WHEN last_legacy_date = reference_on
                    THEN 'legacy'

                    ELSE 'legacy_extra'

                END AS activity_basis

            FROM ({$status}) result
        ";

        return [
            'sql' => $sql,

            'params' => [
                'today' => $today,
                'archive_day' => $archiveDay,
                'live_cutoff' => $today,
                'old_cutoff' => $today,
                'extra_cutoff' => $today,
            ],

            'rule' => $value
                . ' '
                . $units[$unit][$value === 1 ? 1 : 2]
        ];
    }

    public static function forStudent(
        Database $db,
        int $studentId
    ): array {
        $source = self::source($db);

        $row = $db->query(
            "SELECT *
             FROM ({$source['sql']}) activity_student
             WHERE id = :student_id",

            [
                ...$source['params'],
                'student_id' => $studentId
            ]
        )->fetch();

        if (!is_array($row)) {
            throw new RuntimeException(
                'Student not found.'
            );
        }

        $row['rule'] = $source['rule'];

        $row['override_history'] = $db->query(
            'SELECT
                id,
                mode,
                active_until,
                reason,
                changed_by_admin_id,
                admin_email_snapshot,
                changed_at_utc

             FROM student_activity_overrides

             WHERE student_id = :student

             ORDER BY id DESC
             LIMIT 10',

            ['student' => $studentId]
        )->fetchAll();

        return $row;
    }

    public static function label(string $status): string
    {
        return match ($status) {
            'active' => 'Ενεργός',
            'inactive' => 'Ανενεργός',
            'review' => 'Προς έλεγχο',
            default => 'Χωρίς ιστορικό πληρωμής',
        };
    }

    public static function basisLabel(string $basis): string
    {
        return match ($basis) {
            'manual' =>
            'Ενεργός κατ’ εξαίρεση — χωρίς απαίτηση πληρωμής',

            'manual_inactive' =>
            'Ανενεργός βάσει χειροκίνητου ελέγχου — χωρίς επιβεβαιωμένη πληρωμή',

            'legacy' =>
            'Βάσει παλιού ιστορικού — εκτίμηση',

            'legacy_extra' =>
            'Βάσει συμπληρωματικού ιστορικού CSV — εκτίμηση',

            'payment' =>
            'Βάσει έγκυρης πληρωμής της νέας εφαρμογής',

            'review' =>
            'Δεν προκύπτει ασφαλές συμπέρασμα από το διαθέσιμο ιστορικό',

            default =>
            'Δεν υπάρχει κατάλληλη ημερομηνία πληρωμής',
        };
    }

    public static function badgeClass(string $status): string
    {
        return match ($status) {
            'active' =>
            'bg-emerald-500/10 text-emerald-300',

            'inactive' =>
            'bg-amber-500/10 text-amber-300',

            'review' =>
            'bg-orange-500/10 text-orange-300',

            default =>
            'bg-white/5 text-zinc-400',
        };
    }
}
