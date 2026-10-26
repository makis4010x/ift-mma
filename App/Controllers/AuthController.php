<?php

declare(strict_types=1);

namespace App\Controllers;

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

        if (is_int($userId) && in_array(
            $role,
            ['admin', 'student'],
            true
        )) {
            $this->redirectToRole($role);
        }

        loadView('login', [
            'error' => Session::getFlash('login_error')
        ]);
    }

    public function login(): void
    {
        Csrf::protect();

        $blockedUntil = (int) Session::get(
            'login_blocked_until',
            0
        );

        if ($blockedUntil > time()) {
            $this->failLogin();
        }

        if ($blockedUntil !== 0) {
            Session::forget('login_blocked_until');
            Session::forget('login_failures');
        }

        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if (!is_string($email) || !is_string($password)) {
            $this->failLogin();
        }

        $email = strtolower(trim($email));

        if (
            strlen($email) > 254 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            $password === '' ||
            strlen($password) > 1024
        ) {
            $this->failLogin();
        }

        $db = new Database(Config::get('database'));

        $user = $db->query(
            "SELECT id, role, status, password_hash
             FROM users
             WHERE email = :email
             LIMIT 1",
            ['email' => $email]
        )->fetch();

        if (
            !is_array($user) ||
            $user['status'] !== 'active' ||
            !is_string($user['password_hash']) ||
            !password_verify($password, $user['password_hash'])
        ) {
            $this->failLogin();
        }

        $role = $user['role'];

        if (!in_array($role, ['admin', 'student'], true)) {
            $this->failLogin();
        }

        Session::regenerate();

        Session::put('auth_user_id', (int) $user['id']);
        Session::put('auth_role', $role);

        Session::forget('login_failures');
        Session::forget('login_blocked_until');

        $db->query(
            "UPDATE users
             SET last_login_at = NOW()
             WHERE id = :id",
            ['id' => $user['id']]
        );

        $this->redirectToRole($role);
    }

    public function logout(): void
    {
        Csrf::protect();

        Session::forget('auth_user_id');
        Session::forget('auth_role');
        Session::regenerate();

        header('Location: /login', true, 303);
        exit;
    }

    private function redirectToRole(string $role): void
    {
        $path = $role === 'admin'
            ? '/admin'
            : '/student/profile';

        header('Location: ' . $path, true, 303);
        exit;
    }

    private function failLogin(): void
    {
        $failures = (int) Session::get(
            'login_failures',
            0
        ) + 1;

        Session::put('login_failures', $failures);

        if ($failures >= 5) {
            Session::put(
                'login_blocked_until',
                time() + 900
            );
        }

        Session::flash(
            'login_error',
            'Λανθασμένα στοιχεία σύνδεσης '
            . 'ή μη ενεργός λογαριασμός.'
        );

        header('Location: /login', true, 303);
        exit;
    }
}
