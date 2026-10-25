<?php

declare(strict_types=1);

use Framework\Database;

return static function (Database $db): void {
    $db->query("
        CREATE TABLE training_programs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            code VARCHAR(40) NOT NULL,
            name VARCHAR(100) NOT NULL,
            description TEXT NULL,

            is_active BOOLEAN NOT NULL DEFAULT TRUE,

            created_at TIMESTAMP NOT NULL
                DEFAULT CURRENT_TIMESTAMP,

            updated_at TIMESTAMP NOT NULL
                DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            UNIQUE KEY uq_program_code (code)

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");
};
