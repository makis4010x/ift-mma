<?php

declare(strict_types=1);

namespace Framework;

use RuntimeException;

final class Config
{
    private static array $cache = [];

    public static function get(
        string $key,
        mixed $default = null
    ): mixed {
        $segments = explode('.', $key);

        $file = array_shift($segments);

        if (!preg_match('/^[a-zA-Z0-9_-]+$/D', $file)) {
            throw new RuntimeException(
                'Invalid configuration name.'
            );
        }

        if (!array_key_exists($file, self::$cache)) {
            $path = dirname(__DIR__)
                . '/config/' . $file . '.php';

            if (!is_file($path)) {
                throw new RuntimeException(
                    'Configuration file not found.'
                );
            }

            $config = require $path;

            if (!is_array($config)) {
                throw new RuntimeException(
                    'Invalid configuration format.'
                );
            }

            self::$cache[$file] = $config;
        }

        $value = self::$cache[$file];

        foreach ($segments as $segment) {
            if (
                !is_array($value) ||
                !array_key_exists($segment, $value)
            ) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
