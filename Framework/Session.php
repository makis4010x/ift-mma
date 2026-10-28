<?php

declare(strict_types=1);

namespace Framework;

use RuntimeException;

final class Session
{
    // Διατήρηση ανενεργών session files για 30 ημέρες.
    // Δεν υπάρχει αυτόματο logout μετά από λίγα λεπτά.
    private const STORAGE_RETENTION_SECONDS =
    30 * 24 * 60 * 60;

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

        // Ξεχωριστός φάκελος sessions για το IFT.
        // Ο φάκελος βρίσκεται εκτός public/.
        $directory = dirname(__DIR__)
            . '/storage/sessions';

        if (
            !is_dir($directory)
            && !mkdir($directory, 0700, true)
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'Cannot create private session directory.'
            );
        }

        if (!is_writable($directory)) {
            throw new RuntimeException(
                'Private session directory is not writable.'
            );
        }

        session_save_path($directory);

        ini_set(
            'session.gc_maxlifetime',
            (string) self::STORAGE_RETENTION_SECONDS
        );

        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');

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
