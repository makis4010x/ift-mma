<?php

declare(strict_types=1);

use Framework\Database;

return static function (Database $db): void {
    $db->query("
        CREATE TABLE import_batches (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            source_type VARCHAR(40) NOT NULL,
            source_filename VARCHAR(255) NOT NULL,

            source_sha256 CHAR(64)
                CHARACTER SET ascii COLLATE ascii_bin NOT NULL,

            run_kind ENUM('import', 'reconciliation')
                NOT NULL DEFAULT 'import',

            status ENUM(
                'running',
                'completed',
                'completed_with_issues',
                'failed'
            ) NOT NULL DEFAULT 'running',

            total_rows INT UNSIGNED NOT NULL DEFAULT 0,
            inserted_rows INT UNSIGNED NOT NULL DEFAULT 0,
            existing_rows INT UNSIGNED NOT NULL DEFAULT 0,
            skipped_rows INT UNSIGNED NOT NULL DEFAULT 0,
            issue_rows INT UNSIGNED NOT NULL DEFAULT 0,

            started_at TIMESTAMP NOT NULL
                DEFAULT CURRENT_TIMESTAMP,

            finished_at TIMESTAMP NULL DEFAULT NULL,

            INDEX idx_import_batches_type_date (
                source_type, started_at
            ),

            INDEX idx_import_batches_fingerprint (
                source_type, source_sha256
            ),

            INDEX idx_import_batches_status (status)

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");
};
