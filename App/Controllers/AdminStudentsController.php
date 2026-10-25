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
}
