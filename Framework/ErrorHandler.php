<?php

declare(strict_types=1);

namespace Framework;

use Throwable;

final class ErrorHandler
{
    public static function register(): void
    {
        $logPath = dirname(__DIR__)
            . '/storage/logs/php-errors.log';

        // Do not expose PHP errors to visitors.
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');

        // Enable error logging.
        ini_set('log_errors', '1');
        ini_set('error_log', $logPath);

        error_reporting(E_ALL);

        // Handle uncaught exceptions and PHP Errors.
        set_exception_handler(
            [self::class, 'handleException']
        );
    }

    public static function handleException(
        Throwable $exception
    ): void {
        $reference = bin2hex(random_bytes(8));

        // Log only technical metadata, not user input
        // or exception messages.
        error_log(sprintf(
            '[IFT] Reference: %s | Type: %s | File: %s | Line: %d',
            $reference,
            $exception::class,
            basename($exception->getFile()),
            $exception->getLine()
        ));

        // CLI errors should also return a failure code.
        if (PHP_SAPI === 'cli') {
            fwrite(
                STDERR,
                "Internal error. Reference: {$reference}"
                . PHP_EOL
            );

            exit(1);
        }

        if (!headers_sent()) {
            http_response_code(500);
            header(
                'Content-Type: text/plain; charset=UTF-8'
            );
        }

        echo 'Internal server error. Reference: '
            . $reference;
    }
}
