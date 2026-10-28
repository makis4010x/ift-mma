<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use Framework\Database;
use InvalidArgumentException;
use RuntimeException;

final class LegacyPaymentHistory
{
    /** Read-only display. Never creates receipts or changes activity. */
    public static function load(Database $db, int $studentId): array
    {
        if ($studentId < 1) {
            throw new InvalidArgumentException('Invalid student ID.');
        }

        $data = [
            'state' => 'unlinked',
            'snapshot' => null,
            'rows' => []
        ];

        $profiles = $db->query(
            "SELECT p.id, p.archive_id, a.snapshot_id
             FROM legacy_payment_profiles p
             JOIN legacy_payment_archives a ON a.id = p.archive_id
             WHERE p.proposed_student_id = :student
               AND p.link_state = 'candidate'
             ORDER BY a.imported_at_utc DESC, a.id DESC, p.id DESC",
            ['student' => $studentId]
        )->fetchAll();

        if ($profiles === []) {
            return $data;
        }

        // Show one snapshot, not several copies of the same history.
        $archiveId = (int) $profiles[0]['archive_id'];

        $latestProfiles = array_values(array_filter(
            $profiles,
            static fn(array $profile): bool =>
            (int) $profile['archive_id'] === $archiveId
        ));

        $data['snapshot'] = (string) $profiles[0]['snapshot_id'];

        if (count($latestProfiles) !== 1) {
            $data['state'] = 'review';
            return $data;
        }

        $rows = $db->query(
            'SELECT id, source_record_date,
                    source_charged_amount, source_paid_amount,
                    source_balance_amount, review_flags, source_record_json
             FROM legacy_payment_history
             WHERE profile_id = :profile
             ORDER BY (source_record_date IS NULL),
                      source_record_date DESC, id DESC',
            ['profile' => (int) $latestProfiles[0]['id']]
        )->fetchAll();

        $today = (new DateTimeImmutable(
            'today',
            new DateTimeZone('Europe/Athens')
        ))->format('Y-m-d');

        foreach ($rows as &$row) {
            $raw = json_decode(
                (string) $row['source_record_json'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (!is_array($raw)) {
                throw new RuntimeException('Invalid archived record.');
            }

            $row['raw_date'] = is_string($raw['date_raw'] ?? null)
                ? $raw['date_raw']
                : '';

            $row['needs_review'] =
                trim((string) ($row['review_flags'] ?? '')) !== ''
                || $row['source_record_date'] === null
                || $row['source_record_date'] > $today;

            unset($row['source_record_json']);
        }

        unset($row);

        $data['state'] = 'linked';
        $data['rows'] = $rows;

        return $data;
    }
}
