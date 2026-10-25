<?php

declare(strict_types=1);

use Framework\Database;

return static function (Database $db): void {

    $db->query("
        CREATE TABLE student_barcodes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            student_id BIGINT UNSIGNED NOT NULL,

            code VARCHAR(32)
                CHARACTER SET ascii
                COLLATE ascii_bin
                NOT NULL,

            created_at TIMESTAMP NOT NULL
                DEFAULT CURRENT_TIMESTAMP,

            UNIQUE KEY uq_barcode_student (student_id),

            UNIQUE KEY uq_barcode_code (code),

            CONSTRAINT fk_barcode_student
                FOREIGN KEY (student_id)
                REFERENCES students(id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");

};
