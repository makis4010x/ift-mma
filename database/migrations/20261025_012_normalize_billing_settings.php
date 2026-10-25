<?php

declare(strict_types=1);

use Framework\Database;

return static function (Database $db): void {

    // Confirm legacy snapshots have been imported.
    $snapshotCount = (int) $db->query(
        "SELECT COUNT(*)
         FROM legacy_billing_snapshots"
    )->fetchColumn();

    if ($snapshotCount === 0) {
        throw new RuntimeException(
            'Import legacy billing snapshots first.'
        );
    }

    // Check that all previous billing amounts
    // are safely preserved in the legacy snapshots.
    $unsafeRows = (int) $db->query("
        SELECT COUNT(*)
        FROM student_billing_settings s
        LEFT JOIN legacy_billing_snapshots l
            ON l.student_id = s.student_id
        WHERE l.id IS NULL
           OR s.monthly_fee <> l.recorded_monthly_fee
           OR s.currency <> l.currency
    ")->fetchColumn();

    if ($unsafeRows > 0) {
        throw new RuntimeException(
            "Billing migration blocked: {$unsafeRows} "
            . "records require review."
        );
    }

    // The amounts are preserved in legacy snapshots.
    // Keep only the current billing day setting.
    $db->query("
        ALTER TABLE student_billing_settings
            DROP COLUMN monthly_fee,
            DROP COLUMN currency
    ");
};
