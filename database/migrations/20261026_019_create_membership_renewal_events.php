<?php

declare(strict_types=1);

use Framework\Database;

return static function (Database $db): void {
    $db->query("
        CREATE TABLE membership_renewal_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            charge_id BIGINT UNSIGNED NOT NULL,
            completed_by_payment_id BIGINT UNSIGNED NOT NULL,
            expected_start DATE NOT NULL,
            coverage_start DATE NOT NULL,
            coverage_end DATE NOT NULL,
            anchor_date DATE NOT NULL,
            cycle_index INT UNSIGNED NOT NULL,
            paid_on DATE NOT NULL,
            decision ENUM(
                'on_time',
                'advance',
                'shifted',
                'attended',
                'restart'
            ) NOT NULL,
            attendance_count INT UNSIGNED NOT NULL DEFAULT 0,
            automatic_note TEXT NOT NULL,
            created_at_utc DATETIME(6) NOT NULL,

            UNIQUE KEY uq_renewal_charge (charge_id),
            INDEX idx_renewal_payment (completed_by_payment_id),

            CONSTRAINT fk_renewal_charge
                FOREIGN KEY (charge_id)
                REFERENCES membership_charges(id)
                ON DELETE RESTRICT,

            CONSTRAINT fk_renewal_payment
                FOREIGN KEY (completed_by_payment_id)
                REFERENCES student_payments(id)
                ON DELETE RESTRICT
        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");
};
