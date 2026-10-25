<?php

declare(strict_types=1);

use Framework\Database;

return static function (Database $db): void {
    $db->query("
        CREATE TABLE membership_plans (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            code VARCHAR(50) NOT NULL,
            name VARCHAR(150) NOT NULL,

            default_monthly_fee DECIMAL(10,2) NOT NULL,

            currency CHAR(3) NOT NULL DEFAULT 'EUR',

            is_active BOOLEAN NOT NULL DEFAULT TRUE,

            created_at TIMESTAMP NOT NULL
                DEFAULT CURRENT_TIMESTAMP,

            updated_at TIMESTAMP NOT NULL
                DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            UNIQUE KEY uq_membership_plan_code (code),

            CONSTRAINT chk_plan_fee
                CHECK (default_monthly_fee >= 0)

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");
};
