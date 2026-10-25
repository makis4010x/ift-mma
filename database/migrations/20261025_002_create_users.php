<?php

declare(strict_types=1);

use Framework\Database;

return static function (Database $db): void {

    $db->query("
        CREATE TABLE users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            student_id BIGINT UNSIGNED NULL,

            email VARCHAR(254) NOT NULL,

            password_hash VARCHAR(255) NULL,

            role ENUM('admin', 'student')
                NOT NULL,

            status ENUM('pending', 'active', 'disabled')
                NOT NULL DEFAULT 'pending',

            email_verified_at DATETIME NULL,

            last_login_at DATETIME NULL,

            created_at TIMESTAMP NOT NULL
                DEFAULT CURRENT_TIMESTAMP,

            updated_at TIMESTAMP NOT NULL
                DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            UNIQUE KEY uq_users_email (email),

            UNIQUE KEY uq_users_student (student_id),

            CONSTRAINT fk_users_student
                FOREIGN KEY (student_id)
                REFERENCES students(id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE,

            CONSTRAINT chk_users_student_role
                CHECK (
                    (role = 'admin' AND student_id IS NULL)
                    OR
                    (role = 'student' AND student_id IS NOT NULL)
                )

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");

};
