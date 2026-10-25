<?php

declare(strict_types=1);

use Framework\Database;

return static function (Database $db): void {

    $db->query("
        CREATE TABLE attendances (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            student_id BIGINT UNSIGNED NOT NULL,

            legacy_id BIGINT UNSIGNED NULL,

            attended_at_utc DATETIME NOT NULL,

            source ENUM(
                'import',
                'scanner',
                'manual'
            ) NOT NULL DEFAULT 'scanner',

            created_at TIMESTAMP NOT NULL
                DEFAULT CURRENT_TIMESTAMP,

            UNIQUE KEY uq_attendances_legacy (legacy_id),

            INDEX idx_attendances_student_date (
                student_id,
                attended_at_utc
            ),

            INDEX idx_attendances_date (
                attended_at_utc
            ),

            CONSTRAINT fk_attendances_student
                FOREIGN KEY (student_id)
                REFERENCES students(id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");

};
