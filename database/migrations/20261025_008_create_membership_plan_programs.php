<?php

declare(strict_types=1);

use Framework\Database;

return static function (Database $db): void {
    $db->query("
        CREATE TABLE membership_plan_programs (
            plan_id BIGINT UNSIGNED NOT NULL,
            program_id BIGINT UNSIGNED NOT NULL,

            PRIMARY KEY (plan_id, program_id),

            INDEX idx_plan_program (program_id),

            CONSTRAINT fk_plan_program_plan
                FOREIGN KEY (plan_id)
                REFERENCES membership_plans(id)
                ON DELETE RESTRICT,

            CONSTRAINT fk_plan_program_program
                FOREIGN KEY (program_id)
                REFERENCES training_programs(id)
                ON DELETE RESTRICT

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");
};
