<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\LoginRateLimiter;
use Framework\Config;
use Framework\Csrf;
use Framework\Database;
use Framework\Session;

class AuthController
{
    public function showLogin(): void
    {
        $userId = Session::get('auth_user_id');
        $role = Session::get('auth_role');

        if (
            is_int($userId)
            && in_array(
                $role,
                ['admin', 'student'],
                true
            )
        ) {
            $this->redirectToRole($role);
        }

        loadView('login', [
            'error' => Session::getFlash('login_error')
        ]);
    }

    public function login(): void
    {
        Csrf::protect();

        $rawEmail = $_POST['email'] ?? null;
        $rawPassword = $_POST['password'] ?? null;

        $email = is_string($rawEmail)
            ? strtolower(trim($rawEmail))
            : '';

        $validEmail = strlen($email) <= 254
            && filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            ) !== false;

        $rateEmail = $validEmail
            ? $email
            : null;

        $db = new Database(
            Config::get('database')
        );

        // Check the MySQL limiter before verification.
        if (
            LoginRateLimiter::blocked(
                $db,
                $rateEmail
            )
        ) {
            $this->failLogin();
        }

        if (
            !$validEmail
            || !is_string($rawPassword)
            || $rawPassword === ''
            || strlen($rawPassword) > 1024
        ) {
            LoginRateLimiter::failure(
                $db,
                $rateEmail
            );

            $this->failLogin();
        }

        $user = $db->query(
            'SELECT
                id,
                role,
                status,
                password_hash

             FROM users

             WHERE email = :email

             LIMIT 1',
            ['email' => $email]
        )->fetch();

        if (
            !is_array($user)
            || $user['status'] !== 'active'
            || !is_string($user['password_hash'])
            || !password_verify(
                $rawPassword,
                $user['password_hash']
            )
            || !in_array(
                $user['role'],
                ['admin', 'student'],
                true
            )
        ) {
            LoginRateLimiter::failure(
                $db,
                $rateEmail
            );

            $this->failLogin();
        }

        // Credentials verified.
        LoginRateLimiter::success(
            $db,
            $email
        );

        Session::regenerate();

        Session::put(
            'auth_user_id',
            (int) $user['id']
        );

        Session::put(
            'auth_role',
            $user['role']
        );

        // Clear old session-based counters.
        Session::forget('login_failures');
        Session::forget('login_blocked_until');

        $db->query(
            'UPDATE users
             SET last_login_at = NOW()
             WHERE id = :id',
            ['id' => $user['id']]
        );

        $this->redirectToRole(
            $user['role']
        );
    }

    public function logout(): void
    {
        Csrf::protect();

        Session::forget('auth_user_id');
        Session::forget('auth_role');
        Session::regenerate();

        header(
            'Location: /login',
            true,
            303
        );
        exit;
    }

    private function redirectToRole(
        string $role
    ): void {
        $path = $role === 'admin'
            ? '/admin'
            : '/student/profile';

        header(
            'Location: ' . $path,
            true,
            303
        );
        exit;
    }

    private function failLogin(): never
    {
        Session::flash(
            'login_error',
            'Λανθασμένα στοιχεία σύνδεσης, '
                . 'μη ενεργός λογαριασμός '
                . 'ή προσωρινός περιορισμός προσπαθειών.'
        );

        header(
            'Location: /login',
            true,
            303
        );
        exit;
    }
}
