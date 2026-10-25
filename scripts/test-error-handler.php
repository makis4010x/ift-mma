<?php

declare(strict_types=1);

use Framework\ErrorHandler;

require dirname(__DIR__) . '/vendor/autoload.php';

// Register the application's error handler.
ErrorHandler::register();

// Simulate an unexpected application error.
throw new RuntimeException('PRIVATE_TEST_TEXT');
