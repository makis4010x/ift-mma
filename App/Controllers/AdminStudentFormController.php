<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\AdminGuard;
use DateTimeImmutable;
use Framework\Config;
use Framework\Csrf;
use Framework\Database;
use Framework\Session;
use RuntimeException;

final class AdminStudentFormController
{
    private const FIELDS = [
        'first_name',
        'last_name',
        'father_name',
        'birth_date',
        'email',
        'phone',
        'contact_phone',
        'identity_number',
        'address'
    ];

    public function create(): void
    {
        $this->renderForm(null);
    }

    public function edit(array $params): void
    {
        $this->renderForm($this->studentId($params));
    }

    public function store(): void
    {
        $this->save(null);
    }

    public function update(array $params): void
    {
        $this->save($this->studentId($params));
    }

    private function renderForm(?int $id): void
    {
        $admin = AdminGuard::requireAdmin();

        $student = array_fill_keys(self::FIELDS, '');

        if ($id !== null) {
            $db = new Database(Config::get('database'));

            $student = $db->query(
                'SELECT id, first_name, last_name,
                        father_name, birth_date,
                        email, phone, contact_phone,
                        identity_number, address
                 FROM students
                 WHERE id = :id
                 LIMIT 1',
                ['id' => $id]
            )->fetch();

            if (!is_array($student)) {
                ErrorController::notFound();
                return;
            }
        }

        // Restore inputs after a validation error.
        $old = Session::getFlash('student_form_old');

        if (is_array($old)) {
            $student = array_replace(
                $student,
                array_intersect_key($old, $student)
            );
        }

        loadView('admin-header', [
            'adminPage' => 'student-form',
            'adminEmail' => $admin['email'],
            'student' => $student,
            'studentFormMode' => $id === null
                ? 'create'
                : 'edit',
            'studentFormAction' => $id === null
                ? '/admin/students'
                : '/admin/students/' . $id . '/update',
            'studentFormError' => Session::getFlash(
                'student_form_error'
            )
        ]);
    }

    private function save(?int $id): void
    {
        AdminGuard::requireAdmin();
        Csrf::protect();

        $values = [];

        foreach (self::FIELDS as $field) {
            $raw = $_POST[$field] ?? '';

            $values[$field] = is_string($raw)
                ? trim($raw)
                : '';
        }

        $returnUrl = $id === null
            ? '/admin/students/new'
            : '/admin/students/' . $id . '/edit';

        $message = $this->validate($values);

        if ($message !== null) {
            Session::flash('student_form_error', $message);
            Session::flash('student_form_old', $values);

            $this->redirect($returnUrl);
        }

        // Store empty optional fields as SQL NULL.
        $data = [];

        foreach ($values as $key => $value) {
            $data[$key] = $value === '' ? null : $value;
        }

        $db = new Database(Config::get('database'));

        if ($id === null) {

            // Create student and barcode atomically.
            $id = $db->transaction(
                static function (Database $db) use ($data): int {

                    $db->query(
                        'INSERT INTO students (
                            first_name,
                            last_name,
                            father_name,
                            birth_date,
                            email,
                            phone,
                            contact_phone,
                            identity_number,
                            address,
                            created_at,
                            updated_at
                         ) VALUES (
                            :first_name,
                            :last_name,
                            :father_name,
                            :birth_date,
                            :email,
                            :phone,
                            :contact_phone,
                            :identity_number,
                            :address,
                            NOW(),
                            NOW()
                         )',
                        $data
                    );

                    // Use the same database connection.
                    $newId = (int) $db->query(
                        'SELECT LAST_INSERT_ID()'
                    )->fetchColumn();

                    if ($newId <= 0) {
                        throw new RuntimeException(
                            'Student insert failed.'
                        );
                    }

                    $barcode = 'IFT' . str_pad(
                        (string) $newId,
                        6,
                        '0',
                        STR_PAD_LEFT
                    );

                    $db->query(
                        'INSERT INTO student_barcodes (
                            student_id,
                            code,
                            created_at
                         ) VALUES (
                            :student_id,
                            :code,
                            NOW()
                         )',
                        [
                            'student_id' => $newId,
                            'code' => $barcode
                        ]
                    );

                    return $newId;
                }
            );

            Session::flash(
                'student_notice',
                'Ο μαθητής δημιουργήθηκε.'
            );
        } else {

            $exists = $db->query(
                'SELECT id
                 FROM students
                 WHERE id = :id
                 LIMIT 1',
                ['id' => $id]
            )->fetch();

            if (!is_array($exists)) {
                ErrorController::notFound();
                return;
            }

            $db->query(
                'UPDATE students SET
                    first_name = :first_name,
                    last_name = :last_name,
                    father_name = :father_name,
                    birth_date = :birth_date,
                    email = :email,
                    phone = :phone,
                    contact_phone = :contact_phone,
                    identity_number = :identity_number,
                    address = :address,
                    updated_at = NOW()
                 WHERE id = :id',
                [...$data, 'id' => $id]
            );

            Session::flash(
                'student_notice',
                'Οι αλλαγές αποθηκεύτηκαν.'
            );
        }

        $this->redirect('/admin/students/' . $id);
    }

    private function validate(array $values): ?string
    {
        if (
            $values['first_name'] === '' ||
            $values['last_name'] === ''
        ) {
            return 'Όνομα και επώνυμο είναι υποχρεωτικά.';
        }

        $limits = [
            'first_name' => 100,
            'last_name' => 100,
            'father_name' => 100,
            'birth_date' => 10,
            'email' => 254,
            'phone' => 30,
            'contact_phone' => 30,
            'identity_number' => 30,
            'address' => 255
        ];

        foreach ($limits as $field => $maxLength) {
            if (preg_match(
                '~\A.{0,' . $maxLength . '}\z~us',
                $values[$field]
            ) !== 1) {
                return 'Ένα από τα πεδία είναι υπερβολικά '
                    . 'μεγάλο ή μη έγκυρο.';
            }
        }

        if (
            $values['email'] !== '' &&
            filter_var(
                $values['email'],
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            return 'Το email δεν είναι έγκυρο.';
        }

        if ($values['birth_date'] !== '') {
            $date = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $values['birth_date']
            );

            if (
                $date === false ||
                $date->format('Y-m-d')
                !== $values['birth_date']
            ) {
                return 'Η ημερομηνία γέννησης '
                    . 'δεν είναι έγκυρη.';
            }
        }

        return null;
    }

    private function studentId(array $params): int
    {
        $raw = $params['id'] ?? null;

        $id = is_string($raw)
            ? filter_var(
                $raw,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            )
            : false;

        if ($id === false) {
            ErrorController::notFound();
            exit;
        }

        return (int) $id;
    }

    private function redirect(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }
}
