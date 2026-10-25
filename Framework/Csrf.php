<?php

declare(strict_types=1);

namespace Framework;

final class Csrf
{
    private const TOKEN_KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::TOKEN_KEY);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));

            Session::put(self::TOKEN_KEY, $token);
        }

        return $token;
    }

    public static function field(): string
    {
        $token = htmlspecialchars(
            self::token(),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        return '<input type="hidden" name="_csrf" value="'
            . $token . '">';
    }

    public static function verify(?string $token): bool
    {
        $expected = Session::get(self::TOKEN_KEY);

        return is_string($expected)
            && is_string($token)
            && $token !== ''
            && hash_equals($expected, $token);
    }

    public static function protect(): void
    {
        $submitted = $_POST['_csrf']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? null;

        if (
            !is_string($submitted) ||
            !self::verify($submitted)
        ) {
            http_response_code(403);
            exit('Invalid request token.');
        }
    }
}
