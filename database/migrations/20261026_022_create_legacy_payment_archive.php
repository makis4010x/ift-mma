<?php

declare(strict_types=1);

use Framework\Database;

// Archive only: no writes to students, student_payments or coverage.
return static function (Database $db): void {
    $db->query("
        CREATE TABLE IF NOT EXISTS legacy_payment_archives (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            snapshot_id VARCHAR(80)
                CHARACTER SET ascii
                COLLATE ascii_bin NOT NULL,

            profiles_sha256 CHAR(64)
                CHARACTER SET ascii
                COLLATE ascii_bin NOT NULL,

            history_sha256 CHAR(64)
                CHARACTER SET ascii
                COLLATE ascii_bin NOT NULL,

            profile_count INT UNSIGNED NOT NULL,
            record_count INT UNSIGNED NOT NULL,
            proposed_links_count INT UNSIGNED NOT NULL,

            date_meaning ENUM(
                'unconfirmed',
                'receipt_date',
                'coverage_date',
                'mixed'
            ) NOT NULL DEFAULT 'unconfirmed',

            imported_at_utc DATETIME(6) NOT NULL,

            UNIQUE KEY uq_legacy_payment_snapshot (
                snapshot_id
            )

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");

    $db->query("
        CREATE TABLE IF NOT EXISTS legacy_payment_profiles (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            archive_id BIGINT UNSIGNED NOT NULL,

            snapshot_student_key CHAR(64)
                CHARACTER SET ascii
                COLLATE ascii_bin NOT NULL,

            source_list_no INT UNSIGNED NOT NULL,
            proposed_student_id BIGINT UNSIGNED NULL,

            link_state ENUM(
                'candidate',
                'review'
            ) NOT NULL,

            source_mapping_status VARCHAR(64) NOT NULL,
            verification_note VARCHAR(255) NOT NULL,
            source_profile_json LONGTEXT NOT NULL,

            UNIQUE KEY uq_legacy_profile (
                archive_id,
                snapshot_student_key
            ),

            INDEX idx_legacy_proposed_student (
                proposed_student_id
            ),

            CONSTRAINT fk_legacy_profile_archive
                FOREIGN KEY (archive_id)
                REFERENCES legacy_payment_archives(id)
                ON DELETE RESTRICT,

            CONSTRAINT fk_legacy_profile_student
                FOREIGN KEY (proposed_student_id)
                REFERENCES students(id)
                ON DELETE RESTRICT,

            CONSTRAINT chk_legacy_profile_json
                CHECK (
                    JSON_VALID(source_profile_json)
                )

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");

    $db->query("
        CREATE TABLE IF NOT EXISTS legacy_payment_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            profile_id BIGINT UNSIGNED NOT NULL,

            snapshot_row_key CHAR(64)
                CHARACTER SET ascii
                COLLATE ascii_bin NOT NULL,

            source_record_date DATE NULL,

            source_charged_amount DECIMAL(12,2) NULL,
            source_paid_amount DECIMAL(12,2) NULL,
            source_balance_amount DECIMAL(12,2) NULL,

            review_flags TEXT NULL,
            source_record_json LONGTEXT NOT NULL,

            UNIQUE KEY uq_legacy_history_row (
                profile_id,
                snapshot_row_key
            ),

            INDEX idx_legacy_history_date (
                profile_id,
                source_record_date
            ),

            CONSTRAINT fk_legacy_history_profile
                FOREIGN KEY (profile_id)
                REFERENCES legacy_payment_profiles(id)
                ON DELETE RESTRICT,

            CONSTRAINT chk_legacy_history_json
                CHECK (
                    JSON_VALID(source_record_json)
                )

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");
};
