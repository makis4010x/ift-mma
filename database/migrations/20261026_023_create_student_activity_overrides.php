<?php

declare(strict_types=1);

use Framework\Database;

return static function (Database $db): void {
    $db->query("
        CREATE TABLE student_activity_overrides (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            student_id BIGINT UNSIGNED NOT NULL,

            mode ENUM('auto', 'active') NOT NULL,

            starts_on DATE NOT NULL,
            active_until DATE NULL,

            reason VARCHAR(255) NOT NULL,

            changed_by_admin_id BIGINT UNSIGNED NULL,
            admin_email_snapshot VARCHAR(254) NOT NULL,

            changed_at_utc DATETIME(6) NOT NULL,

            request_key CHAR(32)
                CHARACTER SET ascii
                COLLATE ascii_bin NOT NULL,

            UNIQUE KEY uq_activity_request (
                request_key
            ),

            INDEX idx_activity_student_event (
                student_id,
                id
            ),

            CONSTRAINT fk_activity_override_student
                FOREIGN KEY (student_id)
                REFERENCES students(id)
                ON DELETE RESTRICT,

            CONSTRAINT fk_activity_override_admin
                FOREIGN KEY (changed_by_admin_id)
                REFERENCES users(id)
                ON DELETE SET NULL,

            CONSTRAINT chk_activity_override_period
                CHECK (
                    active_until IS NULL
                    OR active_until >= starts_on
                ),

            CONSTRAINT chk_activity_auto_end
                CHECK (
                    mode = 'active'
                    OR active_until IS NULL
                )

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");
};
