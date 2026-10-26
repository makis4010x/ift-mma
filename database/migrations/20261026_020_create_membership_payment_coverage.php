<?php

declare(strict_types=1);

use Framework\Database;

return static function (Database $db): void {
    $db->query("
        CREATE TABLE membership_payment_coverage (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            student_id BIGINT UNSIGNED NOT NULL,
            membership_id BIGINT UNSIGNED NOT NULL,
            payment_id BIGINT UNSIGNED NOT NULL,

            coverage_start DATE NOT NULL,
            coverage_end DATE NOT NULL,

            expected_start DATE NOT NULL,

            anchor_date DATE NOT NULL,
            cycle_index_end INT UNSIGNED NOT NULL,

            months_count TINYINT UNSIGNED NOT NULL,

            agreed_monthly_fee DECIMAL(10,2) NOT NULL,
            paid_amount DECIMAL(10,2) NOT NULL,

            decision ENUM(
                'on_time',
                'advance',
                'shifted',
                'attended',
                'restart'
            ) NOT NULL,

            attendance_dates TEXT NULL,
            automatic_note TEXT NOT NULL,

            created_at_utc DATETIME(6) NOT NULL,

            UNIQUE KEY uq_coverage_payment (
                payment_id
            ),

            INDEX idx_coverage_student_end (
                student_id,
                coverage_end
            ),

            CONSTRAINT fk_coverage_student
                FOREIGN KEY (student_id)
                REFERENCES students(id)
                ON DELETE RESTRICT,

            CONSTRAINT fk_coverage_membership
                FOREIGN KEY (membership_id)
                REFERENCES student_memberships(id)
                ON DELETE RESTRICT,

            CONSTRAINT fk_coverage_payment
                FOREIGN KEY (payment_id)
                REFERENCES student_payments(id)
                ON DELETE RESTRICT,

            CONSTRAINT chk_coverage_dates
                CHECK (
                    coverage_end > coverage_start
                ),

            CONSTRAINT chk_coverage_months
                CHECK (
                    months_count BETWEEN 1 AND 12
                ),

            CONSTRAINT chk_coverage_amount
                CHECK (
                    paid_amount > 0
                )

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");
};
