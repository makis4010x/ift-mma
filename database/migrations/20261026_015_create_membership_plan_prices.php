<?php

declare(strict_types=1);

use Framework\Database;

return static function (Database $db): void {
    $db->query("
        CREATE TABLE membership_plan_prices (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            plan_id BIGINT UNSIGNED NOT NULL,
            monthly_fee DECIMAL(10,2) NOT NULL,
            currency CHAR(3) NOT NULL DEFAULT 'EUR',
            effective_at_utc DATETIME(6) NOT NULL,
            recorded_at_utc DATETIME(6) NOT NULL,
            change_type ENUM('migration', 'initial', 'change') NOT NULL,
            changed_by_admin_id BIGINT UNSIGNED NULL,

            INDEX idx_plan_prices_order (
                plan_id, effective_at_utc, id
            ),

            CONSTRAINT fk_plan_price_plan
                FOREIGN KEY (plan_id)
                REFERENCES membership_plans(id)
                ON DELETE RESTRICT,

            CONSTRAINT chk_plan_price_nonnegative
                CHECK (monthly_fee >= 0)
        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");

    // Καταγράφουμε μόνο την τρέχουσα γνωστή τιμή.
    // Δεν επινοούμε παλαιότερο ιστορικό.
    $db->query("
        INSERT INTO membership_plan_prices
            (plan_id, monthly_fee, currency,
             effective_at_utc, recorded_at_utc,
             change_type, changed_by_admin_id)
        SELECT
            id,
            default_monthly_fee,
            currency,
            UTC_TIMESTAMP(6),
            UTC_TIMESTAMP(6),
            'migration',
            NULL
        FROM membership_plans
    ");
};
