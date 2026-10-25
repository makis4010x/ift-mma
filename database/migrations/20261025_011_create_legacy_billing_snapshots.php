<?php

declare(strict_types=1);

use Framework\Database;

return static function (Database $db): void {
    $db->query("
        CREATE TABLE legacy_billing_snapshots (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            student_id BIGINT UNSIGNED NOT NULL,

            recorded_monthly_fee DECIMAL(10,2) NOT NULL,

            recorded_billing_day TINYINT UNSIGNED NOT NULL,

            currency CHAR(3) NOT NULL DEFAULT 'EUR',

            source_filename VARCHAR(120) NOT NULL,

            source_effective_from DATE NULL,

            imported_at TIMESTAMP NOT NULL
                DEFAULT CURRENT_TIMESTAMP,

            UNIQUE KEY uq_legacy_billing_student (
                student_id
            ),

            CONSTRAINT fk_legacy_billing_student
                FOREIGN KEY (student_id)
                REFERENCES students(id)
                ON DELETE RESTRICT,

            CONSTRAINT chk_legacy_billing_fee
                CHECK (recorded_monthly_fee >= 0),

            CONSTRAINT chk_legacy_billing_day
                CHECK (recorded_billing_day BETWEEN 1 AND 31)

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");
};
