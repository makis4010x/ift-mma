<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\AdminGuard;
use App\Support\StudentActivityService;
use Framework\Config;
use Framework\Database;

class AdminStudentsController
{
    public function index(): void
    {
        $admin = AdminGuard::requireAdmin();

        $db = new Database(
            Config::get('database')
        );

        $search = $_GET['q'] ?? '';
        $activityFilter = $_GET['activity'] ?? 'all';

        if (
            !is_string($search) ||
            preg_match('/\A.{0,150}\z/us', $search) !== 1 ||
            !is_string($activityFilter) ||
            !in_array(
                $activityFilter,
                ['all', 'active', 'inactive', 'no_payment', 'review'],
                true
            )
        ) {
            http_response_code(400);
            exit('Invalid search filters.');
        }

        $search = trim($search);

        $source = StudentActivityService::source($db);

        $params = $source['params'];
        $conditions = [];

        if ($search !== '') {
            $term = '%' . str_replace(
                ['!', '%', '_'],
                ['!!', '!%', '!_'],
                $search
            ) . '%';

            $conditions[] = "(
            first_name LIKE :first_name ESCAPE '!'
            OR last_name LIKE :last_name ESCAPE '!'

            OR CONCAT_WS(' ', first_name, last_name)
                LIKE :full_name ESCAPE '!'

            OR CONCAT_WS(' ', last_name, first_name)
                LIKE :reverse_name ESCAPE '!'

            OR email LIKE :email ESCAPE '!'
        )";

            foreach (
                [
                    'first_name',
                    'last_name',
                    'full_name',
                    'reverse_name',
                    'email'
                ] as $key
            ) {
                $params[$key] = $term;
            }
        }

        if ($activityFilter !== 'all') {
            $conditions[] =
                'activity_status = :activity_filter';

            $params['activity_filter'] = $activityFilter;
        }

        $where = $conditions === []
            ? ''
            : ' WHERE ' . implode(' AND ', $conditions);

        $from = "FROM ({$source['sql']}) student_list";

        // Count the results after applying both filters.
        $totalResults = (int) $db->query(
            "SELECT COUNT(*) {$from} {$where}",
            $params
        )->fetchColumn();

        $perPage = 25;

        $totalPages = max(
            1,
            (int) ceil($totalResults / $perPage)
        );

        $rawPage = $_GET['page'] ?? '1';

        $parsedPage = is_string($rawPage)
            ? filter_var(
                $rawPage,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            )
            : false;

        $page = min(
            $parsedPage === false ? 1 : $parsedPage,
            $totalPages
        );

        $offset = ($page - 1) * $perPage;

        $students = $db->query(
            "SELECT *
     {$from} {$where}

     ORDER BY last_name, first_name, id

     LIMIT {$perPage} OFFSET {$offset}",

            $params
        )->fetchAll();

        loadView('admin-header', [
            'adminPage' => 'students',
            'adminEmail' => $admin['email'],

            'students' => $students,
            'search' => $search,

            'activityFilter' => $activityFilter,
            'activityRule' => $source['rule'],

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

        // Active plans available for manual assignment.
        $membershipPlans = $db->query(
            "SELECT
                id,
                code,
                name,
                default_monthly_fee,
                currency
                FROM membership_plans
                WHERE is_active = 1
                ORDER BY name ASC, id ASC"
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


        $studentActivity = StudentActivityService::forStudent(
            $db,
            $studentId
        );

        // New payment charges for this student.

        $paymentData = \App\Support\StudentPaymentSummary::load(
            $db,
            $studentId
        );


        $legacyPaymentData = \App\Support\LegacyPaymentHistory::load(
            $db,
            $studentId
        );

        // Συμπληρωματικό ιστορικό από τα CSV.
        // Εμφανίζεται στον ΙΔΙΟ πίνακα της παλιάς εφαρμογής.
        $extraLegacyPayments = $db->query(
            "SELECT
        id,
        source_date_text,
        source_record_date,
        source_charged_amount,
        source_paid_amount
     FROM legacy_extra_csv_payments
     WHERE student_id = :student_id
     ORDER BY source_record_date DESC, id DESC",
            ['student_id' => $studentId]
        )->fetchAll();

        // Διατηρούμε ό,τι φορτώθηκε ήδη από την παλιά εφαρμογή.
        $legacyPaymentData['rows'] = $legacyPaymentData['rows'] ?? [];

        // Προσθέτουμε τις συμπληρωματικές εγγραφές στο ίδιο array.
        foreach ($extraLegacyPayments as $extraPayment) {

            $unknownDate =
                $extraPayment['source_record_date'] === null;

            $legacyPaymentData['rows'][] = [
                'source_record_date' =>
                $extraPayment['source_record_date'],

                'raw_date' =>
                (string) $extraPayment['source_date_text'],

                'source_charged_amount' =>
                $extraPayment['source_charged_amount'],

                'source_paid_amount' =>
                $extraPayment['source_paid_amount'],

                // Το συμπληρωματικό CSV δεν περιέχει υπόλοιπο.
                'source_balance_amount' => null,

                'needs_review' => $unknownDate,

                'review_flags' => $unknownDate
                    ? 'Άγνωστη ημερομηνία στην παλιά εφαρμογή'
                    : '',
            ];
        }

        // Ταξινόμηση όλων των εγγραφών μαζί, νεότερες πρώτα.
        // Οι άγνωστες ημερομηνίες εμφανίζονται στο τέλος.
        usort(
            $legacyPaymentData['rows'],
            static function (array $a, array $b): int {
                $dateA = (string) ($a['source_record_date'] ?? '');
                $dateB = (string) ($b['source_record_date'] ?? '');

                return strcmp($dateB, $dateA);
            }
        );


        loadView('admin-header', [
            'adminPage' => 'student-profile',
            'adminEmail' => $admin['email'],
            'student' => $student,
            'attendances' => $attendances,
            'attendanceTotal' => $attendanceTotal,
            'attendancePage' => $attendancePage,
            'attendanceTotalPages' => $attendanceTotalPages,
            'memberships' => $memberships,
            'membershipPlans' => $membershipPlans,
            'legacyBilling' => $legacyBilling,
            'paymentData' => $paymentData,
            'legacyPaymentData' => $legacyPaymentData,
            'studentActivity' => $studentActivity,
        ]);
    }
}
