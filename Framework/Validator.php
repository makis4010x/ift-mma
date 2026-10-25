<?php

declare(strict_types=1);

namespace Framework;

final class Validator
{
    public static function required(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    public static function email(string $value): bool
    {
        return filter_var(
            $value,
            FILTER_VALIDATE_EMAIL
        ) !== false;
    }

    public static function minLength(
        string $value,
        int $min
    ): bool {
        $length = preg_match_all('/./us', $value);

        return $length !== false && $length >= $min;
    }

    public static function maxLength(
        string $value,
        int $max
    ): bool {
        $length = preg_match_all('/./us', $value);

        return $length !== false && $length <= $max;
    }

    public static function positiveInteger(
        mixed $value
    ): bool {
        return filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        ) !== false;
    }
}
