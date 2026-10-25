<?php

declare(strict_types=1);

use Framework\Database;

return static function (Database $db): void {

    $db->query("
        CREATE TABLE students (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            legacy_id INT UNSIGNED NULL UNIQUE,

            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,

            phone VARCHAR(30) NULL,
            contact_phone VARCHAR(30) NULL,

            father_name VARCHAR(100) NULL,

            birth_date DATE NULL,

            identity_number VARCHAR(30) NULL,

            address VARCHAR(255) NULL,

            email VARCHAR(254) NULL,

            created_at TIMESTAMP NOT NULL
                DEFAULT CURRENT_TIMESTAMP,

            updated_at TIMESTAMP NOT NULL
                DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            INDEX idx_students_name (
                last_name,
                first_name
            ),

            INDEX idx_students_email (email)

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");

};
