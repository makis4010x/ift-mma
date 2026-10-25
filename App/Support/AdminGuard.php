<?php

declare(strict_types=1);

namespace App\Support;

use Framework\Config;
use Framework\Database;
use Framework\Session;

final class AdminGuard
{
    public static function requireAdmin(): array
    {
        $userId = Session::get('auth_user_id');
        $role = Session::get('auth_role');

        if (
            !is_int($userId) ||
            $userId < 1 ||
            $role !== 'admin'
        ) {
            self::deny();
        }

        $db = new Database(Config::get('database'));

        $admin = $db->query(
            "SELECT id, email
             FROM users
             WHERE id = :id
               AND role = 'admin'
               AND status = 'active'
             LIMIT 1",
            ['id' => $userId]
        )->fetch();

        if (!is_array($admin)) {
            Session::forget('auth_user_id');
            Session::forget('auth_role');
            Session::regenerate();

            self::deny();
        }

        header('Cache-Control: no-store, private');

        return $admin;
    }

    private static function deny(): never
    {
        header('Cache-Control: no-store, private');

        if (
            ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'
        ) {
            http_response_code(403);
            exit('Access denied.');
        }

        header('Location: /login', true, 302);
        exit;
    }
}
