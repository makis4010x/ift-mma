<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\AdminGuard;
use App\Support\AttendanceRecorder;
use Framework\Config;
use Framework\Csrf;
use Framework\Database;
use Framework\Session;

final class AdminScannerController
{
    public function index(): void
    {
        $admin = AdminGuard::requireAdmin();

        loadView('admin-header', [
            'adminPage' => 'scanner',
            'adminEmail' => $admin['email'],
            'scannerStatus' => Session::getFlash('scanner_status')
        ]);
    }


    public function search(): void
    {
        AdminGuard::requireAdmin();

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');

        $raw = $_GET['q'] ?? '';

        if (
            !is_string($raw) ||
            strlen($raw) > 240 ||
            preg_match('//u', $raw) !== 1
        ) {
            http_response_code(400);
            echo json_encode(
                ['error' => 'Μη έγκυρη αναζήτηση.'],
                JSON_UNESCAPED_UNICODE
            );
            return;
        }

        $query = trim(
            preg_replace('/\s+/u', ' ', $raw) ?? ''
        );

        if (
            $query === '' ||
            (
                preg_match('/\A.\z/us', $query) === 1 &&
                !ctype_digit($query)
            )
        ) {
            echo '[]';
            return;
        }

        // Escape SQL LIKE special characters.
        $escaped = str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            $query
        );

        $like = '%' . $escaped . '%';

        // Names are always searched as Unicode.
        $where = [
            "s.first_name COLLATE utf8mb4_unicode_ci
            LIKE :first_name ESCAPE '!'",

            "s.last_name COLLATE utf8mb4_unicode_ci
            LIKE :last_name ESCAPE '!'",

            "CONCAT_WS(' ', s.first_name, s.last_name)
            COLLATE utf8mb4_unicode_ci
            LIKE :full_name ESCAPE '!'",

            "CONCAT_WS(' ', s.last_name, s.first_name)
            COLLATE utf8mb4_unicode_ci
            LIKE :reverse_name ESCAPE '!'"
        ];

        $params = [
            'first_name' => $like,
            'last_name' => $like,
            'full_name' => $like,
            'reverse_name' => $like
        ];

        $priorityOrder = '';

        // Barcode column is ASCII/BINARY.
        // Never compare Greek input to this column.
        $isAscii = preg_match(
            '/\A[\x20-\x7E]+\z/D',
            $query
        ) === 1;

        if ($isAscii) {
            $where[] = "b.code LIKE :barcode ESCAPE '!'";

            $params['barcode'] =
                '%' . strtoupper($escaped) . '%';

            $params['exact_barcode'] = strtoupper($query);

            $priorityOrder = "
            CASE WHEN b.code = :exact_barcode
                THEN 0 ELSE 1 END,
        ";
        }

        // Exact student ID or legacy ID.
        if (ctype_digit($query)) {
            $id = filter_var(
                $query,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($id !== false) {
                $where[] = 's.id = :student_id';
                $where[] = 's.legacy_id = :legacy_id';

                $params['student_id'] = $id;
                $params['legacy_id'] = $id;
            }
        }

        $db = new Database(Config::get('database'));

        $sql = "
        SELECT
            s.id,
            s.first_name,
            s.last_name,
            b.code AS barcode
        FROM students s
        LEFT JOIN student_barcodes b
            ON b.student_id = s.id
        WHERE " . implode(' OR ', $where) . "
        ORDER BY
            {$priorityOrder}
            s.last_name,
            s.first_name,
            s.id
        LIMIT 15
    ";

        $students = $db->query(
            $sql,
            $params
        )->fetchAll();

        echo json_encode(
            $students,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }



    public function scan(): void
    {
        AdminGuard::requireAdmin();
        Csrf::protect();

        $rawId = $_POST['student_id'] ?? null;

        $studentId = is_string($rawId)
            ? filter_var(
                $rawId,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            )
            : false;

        $result = $studentId === false
            ? 'invalid'
            : AttendanceRecorder::recordManual(
                (int) $studentId
            );

        Session::flash('scanner_status', $result);

        header('Location: /admin/scanner', true, 303);
        exit;
    }
}
