<?php

declare(strict_types=1);

namespace Framework;

use RuntimeException;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (headers_sent()) {
            throw new RuntimeException(
                'Cannot start session after output.'
            );
        }

        $https = !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off';

        $production = Config::get(
            'app.environment',
            'local'
        ) === 'production';

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_name('IFTSESSID');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $production || $https,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        if (!session_start()) {
            throw new RuntimeException(
                'Unable to start session.'
            );
        }
    }

    public static function put(
        string $key,
        mixed $value
    ): void {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get(
        string $key,
        mixed $default = null
    ): mixed {
        self::start();

        return $_SESSION[$key] ?? $default;
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        self::start();

        if (!session_regenerate_id(true)) {
            throw new RuntimeException(
                'Unable to regenerate session.'
            );
        }
    }

    public static function flash(
        string $key,
        mixed $value
    ): void {
        self::start();

        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(
        string $key,
        mixed $default = null
    ): mixed {
        self::start();

        $value = $_SESSION['_flash'][$key]
            ?? $default;

        unset($_SESSION['_flash'][$key]);

        return $value;
    }
}
