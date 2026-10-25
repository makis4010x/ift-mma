<?php

declare(strict_types=1);

namespace Framework;

use InvalidArgumentException;
use RuntimeException;

final class View
{
    public static function render(
        string $name,
        array $data = []
    ): void {
        self::display(
            $name,
            'views/',
            '.view.php',
            $data
        );
    }

    public static function partial(
        string $name,
        array $data = []
    ): void {
        self::display(
            $name,
            'views/partials/',
            '.php',
            $data
        );
    }

    private static function display(
        string $name,
        string $directory,
        string $extension,
        array $data
    ): void {
        // Only allow safe view paths.
        if (!preg_match(
            '~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~D',
            $name
        )) {
            throw new InvalidArgumentException(
                'Invalid view name.'
            );
        }

        $path = basePath(
            $directory . $name . $extension
        );

        if (!is_file($path)) {
            throw new RuntimeException(
                "View not found: {$name}"
            );
        }

        // Make data available to the view.
        extract($data, EXTR_SKIP);

        require $path;
    }
}
