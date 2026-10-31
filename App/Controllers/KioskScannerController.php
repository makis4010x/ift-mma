<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\AdminGuard;
use App\Support\AttendanceRecorder;
use App\Support\StudentPaymentSummary;
use Framework\Config;
use Framework\Csrf;
use Framework\Database;
use Framework\Session;

final class KioskScannerController
{
    public function index(): void
    {
        // Scanner only for logged-in Admins.
        AdminGuard::requireAdmin();

        header('Cache-Control: no-store, private');
        header('X-Frame-Options: DENY');

        loadView('scanner-kiosk', [
            'authorized' => true,
            'configured' => true,
            'notice' => null
        ]);
    }

    public function checkIn(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');

        // Check Admin session.
        if (
            !is_int(Session::get('auth_user_id')) ||
            Session::get('auth_role') !== 'admin'
        ) {
            http_response_code(401);

            echo json_encode([
                'status' => 'unauthorized'
            ]);

            return;
        }

        // Verify that Admin is still active.
        AdminGuard::requireAdmin();

        Csrf::protect();

        $barcode = $_POST['barcode'] ?? null;

        $result = is_string($barcode)
            ? AttendanceRecorder::recordScan($barcode)
            : 'invalid';

        $name = '';
        $membershipStatus = null;
        $membershipNotice = null;
        $coveredUntil = null;

        if (in_array($result, ['saved', 'duplicate'], true)) {

            $db = new Database(Config::get('database'));

            $row = $db->query(
                "SELECT
                    s.id,
                    s.first_name,
                    s.last_name
                 FROM students s
                 JOIN student_barcodes b
                    ON b.student_id = s.id
                 WHERE b.code = :code
                 LIMIT 1",
                [
                    'code' => strtoupper(
                        trim((string) $barcode)
                    )
                ]
            )->fetch();

            if (is_array($row)) {
                $name = trim(
                    $row['first_name'] . ' ' .
                        $row['last_name']
                );


                try {
                    $summary = StudentPaymentSummary::load(
                        $db,
                        (int) $row['id']
                    );

                    if ($summary['review']) {
                        $membershipStatus = 'review';
                        $membershipNotice =
                            'Χρειάζεται έλεγχος των στοιχείων συνδρομής. '
                            . 'Η είσοδος επιτρέπεται.';
                    } elseif ($summary['covered_until'] !== null) {
                        $membershipStatus = 'covered';
                        $coveredUntil = $summary['covered_until'];
                        $membershipNotice =
                            'Ενεργή πληρωμένη συνδρομή.';
                    } else {
                        $membershipStatus = 'uncovered';
                        $membershipNotice =
                            'Δεν υπάρχει ενεργή πληρωμένη κάλυψη. '
                            . 'Ενημερώστε τον μαθητή. '
                            . 'Η είσοδος επιτρέπεται.';
                    }
                } catch (\Throwable $exception) {
                    error_log(
                        'IFT scanner membership error: '
                            . get_class($exception)
                            . ' - '
                            . $exception->getMessage()
                    );

                    $membershipStatus = 'review';
                    $membershipNotice =
                        'Δεν ήταν δυνατός ο έλεγχος συνδρομής. '
                        . 'Η είσοδος επιτρέπεται.';
                }
            }
        }

        echo json_encode(
            [
                'status' => $result,
                'name' => $name,
                'membership_status' => $membershipStatus,
                'membership_notice' => $membershipNotice,
                'covered_until' => $coveredUntil
            ],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}
