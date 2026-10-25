<?php

declare(strict_types=1);

use Framework\Database;

return static function (Database $db): void {
    $db->query("
        CREATE TABLE student_enrollments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            student_id BIGINT UNSIGNED NOT NULL,
            program_id BIGINT UNSIGNED NOT NULL,

            valid_from DATE NOT NULL,
            valid_to DATE NULL,

            created_at TIMESTAMP NOT NULL
                DEFAULT CURRENT_TIMESTAMP,

            UNIQUE KEY uq_student_program_start (
                student_id,
                program_id,
                valid_from
            ),

            INDEX idx_enrollment_program (program_id),

            CONSTRAINT fk_enrollment_student
                FOREIGN KEY (student_id)
                REFERENCES students(id)
                ON DELETE RESTRICT,

            CONSTRAINT fk_enrollment_program
                FOREIGN KEY (program_id)
                REFERENCES training_programs(id)
                ON DELETE RESTRICT,

            CONSTRAINT chk_enrollment_period
                CHECK (
                    valid_to IS NULL
                    OR valid_to > valid_from
                )

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");
};
