<?php

declare(strict_types=1);

use Framework\Database;

return static function (Database $db): void {

    $db->query("
        CREATE TABLE student_billing_settings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            student_id BIGINT UNSIGNED NOT NULL,

            monthly_fee DECIMAL(10,2) NOT NULL,

            billing_day TINYINT UNSIGNED NOT NULL,

            currency CHAR(3) NOT NULL DEFAULT 'EUR',

            created_at TIMESTAMP NOT NULL
                DEFAULT CURRENT_TIMESTAMP,

            updated_at TIMESTAMP NOT NULL
                DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            UNIQUE KEY uq_billing_student (student_id),

            CONSTRAINT fk_billing_student
                FOREIGN KEY (student_id)
                REFERENCES students(id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE,

            CONSTRAINT chk_billing_day
                CHECK (billing_day BETWEEN 1 AND 31),

            CONSTRAINT chk_monthly_fee
                CHECK (monthly_fee >= 0)

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");

};
