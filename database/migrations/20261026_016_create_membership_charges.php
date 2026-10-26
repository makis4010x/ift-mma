<?php

declare(strict_types=1);

use Framework\Database;

return static function (Database $db): void {
    $db->query("
        CREATE TABLE membership_charges (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            student_id BIGINT UNSIGNED NOT NULL,
            membership_id BIGINT UNSIGNED NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            due_on DATE NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            currency CHAR(3) NOT NULL DEFAULT 'EUR',
            created_at_utc DATETIME(6) NOT NULL,

            UNIQUE KEY uq_charge_membership_period
                (membership_id, period_start),

            INDEX idx_charge_student_due
                (student_id, due_on),

            CONSTRAINT fk_charge_student
                FOREIGN KEY (student_id)
                REFERENCES students(id)
                ON DELETE RESTRICT,

            CONSTRAINT fk_charge_membership
                FOREIGN KEY (membership_id)
                REFERENCES student_memberships(id)
                ON DELETE RESTRICT,

            CONSTRAINT chk_charge_amount
                CHECK (amount >= 0),

            CONSTRAINT chk_charge_period
                CHECK (period_end > period_start)
        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");
};
