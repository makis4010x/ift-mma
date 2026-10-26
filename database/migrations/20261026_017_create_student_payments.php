<?php

declare(strict_types=1);

use Framework\Database;

return static function (Database $db): void {
    $db->query("
        CREATE TABLE student_payments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            student_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            currency CHAR(3) NOT NULL DEFAULT 'EUR',
            paid_on DATE NOT NULL,
            recorded_at_utc DATETIME(6) NOT NULL,

            method ENUM(
                'cash',
                'card',
                'bank_transfer'
            ) NOT NULL,

            status ENUM('posted', 'void')
                NOT NULL DEFAULT 'posted',

            notes VARCHAR(255) NULL,
            recorded_by_admin_id BIGINT UNSIGNED NOT NULL,

            voided_at_utc DATETIME(6) NULL,
            voided_by_admin_id BIGINT UNSIGNED NULL,
            void_reason VARCHAR(255) NULL,

            request_key CHAR(32)
                CHARACTER SET ascii
                COLLATE ascii_bin NOT NULL,

            UNIQUE KEY uq_payment_request
                (request_key),

            INDEX idx_payment_student_date
                (student_id, paid_on, id),

            CONSTRAINT fk_payment_student
                FOREIGN KEY (student_id)
                REFERENCES students(id)
                ON DELETE RESTRICT,

            CONSTRAINT fk_payment_recorder
                FOREIGN KEY (recorded_by_admin_id)
                REFERENCES users(id)
                ON DELETE RESTRICT,

            CONSTRAINT fk_payment_voider
                FOREIGN KEY (voided_by_admin_id)
                REFERENCES users(id)
                ON DELETE RESTRICT,

            CONSTRAINT chk_payment_positive
                CHECK (amount > 0)
        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");
};
