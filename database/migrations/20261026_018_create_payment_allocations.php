<?php

declare(strict_types=1);

use Framework\Database;

return static function (Database $db): void {
    $db->query("
        CREATE TABLE payment_allocations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            payment_id BIGINT UNSIGNED NOT NULL,
            charge_id BIGINT UNSIGNED NOT NULL,
            applied_amount DECIMAL(10,2) NOT NULL,
            allocated_at_utc DATETIME(6) NOT NULL,

            UNIQUE KEY uq_payment_charge
                (payment_id, charge_id),

            INDEX idx_allocation_charge
                (charge_id),

            CONSTRAINT fk_allocation_payment
                FOREIGN KEY (payment_id)
                REFERENCES student_payments(id)
                ON DELETE RESTRICT,

            CONSTRAINT fk_allocation_charge
                FOREIGN KEY (charge_id)
                REFERENCES membership_charges(id)
                ON DELETE RESTRICT,

            CONSTRAINT chk_allocation_positive
                CHECK (applied_amount > 0)
        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");
};
