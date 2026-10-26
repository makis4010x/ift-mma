<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\AdminGuard;
use App\Support\AttendanceRecorder;
use Framework\Config;
use Framework\Csrf;
use Framework\Database;
use Framework\Session;

final class AdminManualAttendanceController
{
    public function index(): void
    {
        $admin = AdminGuard::requireAdmin();

        loadView('admin-header', [
            'adminPage' => 'attendance-manual',
            'adminEmail' => $admin['email'],
            'manualStatus' => Session::getFlash('manual_status')
        ]);
    }

    public function search(): void
    {
        AdminGuard::requireAdmin();

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');

        $raw = $_GET['q'] ?? '';

        if (!is_string($raw) || strlen($raw) > 120) {
            http_response_code(400);

            echo json_encode([
                'error' => 'Μη έγκυρη αναζήτηση.'
            ]);

            return;
        }

        $search = trim(
            preg_replace('/\s+/u', ' ', $raw) ?? ''
        );

        if (
            $search === '' ||
            (
                preg_match('/\A.\z/us', $search) === 1 &&
                !ctype_digit($search)
            )
        ) {
            echo '[]';
            return;
        }

        // Escape SQL LIKE wildcards.
        $escaped = str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            $search
        );

        $like = '%' . $escaped . '%';
        $barcodeLike = '%' . strtoupper($escaped) . '%';

        $conditions = [
            "s.first_name COLLATE utf8mb4_unicode_ci LIKE :first_name ESCAPE '!'",
            "s.last_name COLLATE utf8mb4_unicode_ci LIKE :last_name ESCAPE '!'",
            "CONCAT_WS(' ', s.first_name, s.last_name) COLLATE utf8mb4_unicode_ci LIKE :full_name ESCAPE '!'",
            "CONCAT_WS(' ', s.last_name, s.first_name) COLLATE utf8mb4_unicode_ci LIKE :reverse_name ESCAPE '!'",
            "b.code LIKE :barcode ESCAPE '!'"
        ];

        $params = [
            'first_name' => $like,
            'last_name' => $like,
            'full_name' => $like,
            'reverse_name' => $like,
            'barcode' => $barcodeLike,
            'exact_barcode' => strtoupper($search),
            'exact_id' => ctype_digit($search)
                ? (int) $search
                : 0
        ];

        // Numeric search: internal or legacy student ID.
        if (ctype_digit($search)) {
            $id = filter_var(
                $search,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($id !== false) {
                $conditions[] = 's.id = :student_id';
                $conditions[] = 's.legacy_id = :legacy_id';

                $params['student_id'] = $id;
                $params['legacy_id'] = $id;
            }
        }

        $db = new Database(Config::get('database'));

        $students = $db->query(
            'SELECT
                s.id,
                s.first_name,
                s.last_name,
                s.email,
                b.code AS barcode
             FROM students s
             LEFT JOIN student_barcodes b
                ON b.student_id = s.id
             WHERE ' . implode(' OR ', $conditions) . '
             ORDER BY
                CASE
                    WHEN b.code = :exact_barcode THEN 0
                    WHEN s.id = :exact_id THEN 1
                    ELSE 2
                END,
                s.last_name,
                s.first_name,
                s.id
             LIMIT 15',
            $params
        )->fetchAll();

        echo json_encode(
            $students,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    public function store(): void
    {
        AdminGuard::requireAdmin();
        Csrf::protect();

        $rawId = $_POST['student_id'] ?? null;

        $id = is_string($rawId)
            ? filter_var(
                $rawId,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            )
            : false;

        $result = $id === false
            ? 'invalid'
            : AttendanceRecorder::recordManual((int) $id);

        Session::flash('manual_status', $result);

        header(
            'Location: /admin/attendance/manual',
            true,
            303
        );
        exit;
    }
}
