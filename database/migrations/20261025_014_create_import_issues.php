<?php

declare(strict_types=1);

use Framework\Database;

return static function (Database $db): void {
    $db->query("
        CREATE TABLE import_issues (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            batch_id BIGINT UNSIGNED NOT NULL,

            source_record_id VARCHAR(64) NULL,
            source_row_number INT UNSIGNED NULL,

            issue_code VARCHAR(40) NOT NULL,

            status ENUM('open', 'resolved', 'ignored')
                NOT NULL DEFAULT 'open',

            resolution_note TEXT NULL,

            resolved_by BIGINT UNSIGNED NULL,
            resolved_at TIMESTAMP NULL DEFAULT NULL,

            created_at TIMESTAMP NOT NULL
                DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_import_issues_batch (
                batch_id, issue_code
            ),

            INDEX idx_import_issues_status (
                status, issue_code
            ),

            INDEX idx_import_issues_resolved_by (
                resolved_by
            ),

            UNIQUE KEY uq_import_issue_record (
                batch_id, source_record_id, issue_code
            ),

            CONSTRAINT fk_import_issue_batch
                FOREIGN KEY (batch_id)
                REFERENCES import_batches(id)
                ON DELETE RESTRICT,

            CONSTRAINT fk_import_issue_resolver
                FOREIGN KEY (resolved_by)
                REFERENCES users(id)
                ON DELETE SET NULL

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");
};
