<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\AdminGuard;
use Framework\Config;
use Framework\Csrf;
use Framework\Database;
use Framework\Session;
use RuntimeException;

final class AdminSettingsController
{
    public function index(): void
    {
        $admin = AdminGuard::requireAdmin();

        $db = new Database(
            Config::get('database')
        );

        $settings = $this->readSettings($db);

        $old = Session::getFlash(
            'activity_settings_old',
            []
        );

        loadView('admin-header', [
            'adminPage' => 'settings',
            'adminEmail' => $admin['email'],

            'activitySettings' => $settings,

            'settingsOld' => is_array($old)
                ? $old
                : [],

            'settingsError' => Session::getFlash(
                'activity_settings_error'
            ),

            'settingsSuccess' => Session::getFlash(
                'activity_settings_success'
            )
        ]);
    }

    public function update(): void
    {
        $admin = AdminGuard::requireAdmin();
        Csrf::protect();

        $rawValue = $_POST['inactivity_value'] ?? null;
        $rawUnit = $_POST['inactivity_unit'] ?? null;

        $value = is_string($rawValue)
            ? filter_var(
                trim($rawValue),
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                        'max_range' => 999
                    ]
                ]
            )
            : false;

        $validUnit = is_string($rawUnit)
            && in_array(
                $rawUnit,
                ['days', 'months', 'years'],
                true
            );

        if ($value === false || !$validUnit) {
            Session::flash(
                'activity_settings_old',
                [
                    'inactivity_value' => is_string($rawValue)
                        ? substr(trim($rawValue), 0, 10)
                        : '',

                    'inactivity_unit' => $validUnit
                        ? $rawUnit
                        : 'days'
                ]
            );

            Session::flash(
                'activity_settings_error',
                'Συμπλήρωσε ακέραιο αριθμό από 1 έως 999 '
                    . 'και επίλεξε ημέρες, μήνες ή χρόνια.'
            );

            $this->redirect();
        }

        $db = new Database(
            Config::get('database')
        );

        // Confirm the settings row exists.
        $this->readSettings($db);

        // Save value and unit together.
        $db->query(
            'UPDATE student_activity_settings
             SET inactivity_value = :value,
                 inactivity_unit = :unit,
                 updated_at_utc = UTC_TIMESTAMP(6),
                 updated_by_admin_id = :admin
             WHERE id = 1',
            [
                'value' => $value,
                'unit' => $rawUnit,
                'admin' => (int) $admin['id']
            ]
        );

        Session::getFlash('activity_settings_old');
        Session::getFlash('activity_settings_error');

        Session::flash(
            'activity_settings_success',
            'Το όριο αδράνειας αποθηκεύτηκε.'
        );

        $this->redirect();
    }

    private function readSettings(Database $db): array
    {
        $settings = $db->query(
            'SELECT
                inactivity_value,
                inactivity_unit,
                updated_at_utc,
                updated_by_admin_id
             FROM student_activity_settings
             WHERE id = 1'
        )->fetch();

        if (!is_array($settings)) {
            throw new RuntimeException(
                'Student activity settings missing. '
                    . 'Run migration 021.'
            );
        }

        return $settings;
    }

    private function redirect(): never
    {
        header(
            'Location: /admin/settings',
            true,
            303
        );

        exit;
    }
}
