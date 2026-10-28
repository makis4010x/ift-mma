<?php

declare(strict_types=1);

use Framework\Database;

return static function (Database $db): void {
    $db->query("
        CREATE TABLE IF NOT EXISTS student_activity_settings (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,

            inactivity_value SMALLINT UNSIGNED NOT NULL,

            inactivity_unit ENUM(
                'days',
                'months',
                'years'
            ) NOT NULL,

            updated_at_utc DATETIME(6) NOT NULL,

            updated_by_admin_id BIGINT UNSIGNED NULL,

            CONSTRAINT chk_activity_settings_single_row
                CHECK (id = 1),

            CONSTRAINT chk_activity_settings_positive_value
                CHECK (inactivity_value BETWEEN 1 AND 999),

            CONSTRAINT fk_activity_settings_admin
                FOREIGN KEY (updated_by_admin_id)
                REFERENCES users(id)
                ON DELETE SET NULL

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");

    // Initial setting only.
    // Never reset an already saved setting.
    $db->query("
        INSERT INTO student_activity_settings (
            id,
            inactivity_value,
            inactivity_unit,
            updated_at_utc,
            updated_by_admin_id
        ) VALUES (
            1,
            90,
            'days',
            UTC_TIMESTAMP(6),
            NULL
        )
        ON DUPLICATE KEY UPDATE id = id
    ");
};
