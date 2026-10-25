<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\AdminGuard;
use Framework\Config;
use Framework\Database;

class AdminStudentsController
{
    public function index(): void
    {
        $admin = AdminGuard::requireAdmin();

        $db = new Database(Config::get('database'));

        // Read and validate the search query.
        $searchInput = $_GET['q'] ?? '';

        $search = is_string($searchInput)
            ? trim($searchInput)
            : '';

        if (strlen($search) > 150) {
            http_response_code(400);
            exit('Invalid search query.');
        }

        $where = '';
        $params = [];

        if ($search !== '') {
            $where = "
                WHERE (
                    first_name LIKE :first_name
                    OR last_name LIKE :last_name
                    OR CONCAT(
                        first_name,
                        ' ',
                        last_name
                    ) LIKE :full_name
                    OR email LIKE :email
                )
            ";

            $term = '%' . $search . '%';

            $params = [
                'first_name' => $term,
                'last_name' => $term,
                'full_name' => $term,
                'email' => $term
            ];
        }

        // Count matching students.
        $totalResults = (int) $db->query(
            "SELECT COUNT(*)
             FROM students
             {$where}",
            $params
        )->fetchColumn();

        // Pagination: 25 students per page.
        $perPage = 25;

        $totalPages = max(
            1,
            (int) ceil($totalResults / $perPage)
        );

        $pageInput = $_GET['page'] ?? '1';

        $page = (
            is_string($pageInput) &&
            ctype_digit($pageInput)
        )
            ? max(1, (int) $pageInput)
            : 1;

        $page = min($page, $totalPages);

        $offset = ($page - 1) * $perPage;

        // Safe integers calculated by the application.
        $students = $db->query(
            "SELECT
                id,
                legacy_id,
                first_name,
                last_name,
                email,
                phone,
                contact_phone
             FROM students
             {$where}
             ORDER BY last_name, first_name, id
             LIMIT {$perPage}
             OFFSET {$offset}",
            $params
        )->fetchAll();

        loadView('admin-header', [
            'adminPage' => 'students',
            'adminEmail' => $admin['email'],
            'students' => $students,
            'search' => $search,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalResults' => $totalResults
        ]);
    }


    public function show(array $params): void
    {
        $admin = AdminGuard::requireAdmin();

        $rawId = $params['id'] ?? '';

        $studentId = filter_var(
            $rawId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($studentId === false) {
            http_response_code(404);
            exit('Ο μαθητής δεν βρέθηκε.');
        }

        $db = new Database(Config::get('database'));

        $student = $db->query(
            "SELECT
            s.id,
            s.legacy_id,
            s.first_name,
            s.last_name,
            s.father_name,
            s.birth_date,
            s.email,
            s.phone,
            s.contact_phone,
            s.address,
            s.created_at,
            b.code AS barcode
         FROM students s
         LEFT JOIN student_barcodes b
            ON b.student_id = s.id
         WHERE s.id = :id
         LIMIT 1",
            ['id' => $studentId]
        )->fetch();

        if (!is_array($student)) {
            http_response_code(404);
            exit('Ο μαθητής δεν βρέθηκε.');
        }


        // Attendance history: 20 records per page.
        $attendanceTotal = (int) $db->query(
            "SELECT COUNT(*)
             FROM attendances
             WHERE student_id = :student_id",
            ['student_id' => $studentId]
        )->fetchColumn();

        $perPage = 20;
        $attendanceTotalPages = max(
            1,
            (int) ceil($attendanceTotal / $perPage)
        );

        $pageInput = $_GET['attendance_page'] ?? '1';

        $attendancePage = (
            is_string($pageInput) &&
            ctype_digit($pageInput)
        )
            ? max(1, (int) $pageInput)
            : 1;

        $attendancePage = min(
            $attendancePage,
            $attendanceTotalPages
        );

        $offset = ($attendancePage - 1) * $perPage;

        $attendances = $db->query(
            "SELECT id, attended_at_utc, source
             FROM attendances
             WHERE student_id = :student_id
             ORDER BY attended_at_utc DESC, id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            ['student_id' => $studentId]
        )->fetchAll();

        // Membership history.
        $memberships = $db->query(
            "SELECT
                sm.id,
                sm.valid_from,
                sm.valid_to,
                sm.agreed_monthly_fee,
                sm.currency,
                mp.name AS plan_name
             FROM student_memberships sm
             LEFT JOIN membership_plans mp
                ON mp.id = sm.plan_id
             WHERE sm.student_id = :student_id
             ORDER BY sm.valid_from DESC, sm.id DESC",
            ['student_id' => $studentId]
        )->fetchAll();

        // Latest historical billing snapshot, if any.
        $legacyBilling = $db->query(
            "SELECT
                recorded_monthly_fee,
                recorded_billing_day,
                currency,
                source_effective_from
             FROM legacy_billing_snapshots
             WHERE student_id = :student_id
             ORDER BY imported_at DESC, id DESC
             LIMIT 1",
            ['student_id' => $studentId]
        )->fetch();


        loadView('admin-header', [
            'adminPage' => 'student-profile',
            'adminEmail' => $admin['email'],
            'student' => $student,
            'attendances' => $attendances,
            'attendanceTotal' => $attendanceTotal,
            'attendancePage' => $attendancePage,
            'attendanceTotalPages' => $attendanceTotalPages,
            'memberships' => $memberships,
            'legacyBilling' => $legacyBilling,
        ]);
    }
}
