<?php

declare(strict_types=1);

use Framework\Database;

return static function (Database $db): void {
    $db->query("
        CREATE TABLE student_memberships (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            student_id BIGINT UNSIGNED NOT NULL,
            plan_id BIGINT UNSIGNED NOT NULL,

            agreed_monthly_fee DECIMAL(10,2) NOT NULL,
            currency CHAR(3) NOT NULL DEFAULT 'EUR',

            valid_from DATE NOT NULL,
            valid_to DATE NULL,

            created_at TIMESTAMP NOT NULL
                DEFAULT CURRENT_TIMESTAMP,

            UNIQUE KEY uq_membership_start (
                student_id,
                valid_from
            ),

            INDEX idx_membership_plan (plan_id),

            CONSTRAINT fk_membership_student
                FOREIGN KEY (student_id)
                REFERENCES students(id)
                ON DELETE RESTRICT,

            CONSTRAINT fk_membership_plan
                FOREIGN KEY (plan_id)
                REFERENCES membership_plans(id)
                ON DELETE RESTRICT,

            CONSTRAINT chk_membership_fee
                CHECK (agreed_monthly_fee >= 0),

            CONSTRAINT chk_membership_period
                CHECK (
                    valid_to IS NULL
                    OR valid_to > valid_from
                )

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");
};
