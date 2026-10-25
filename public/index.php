<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../helpers.php';

// Register error handling.
\Framework\ErrorHandler::register();

// Start session.
\Framework\Session::start();

// Initialize router.
$router = new \Framework\Router();

// Register web routes.
require basePath('routes/web.php');

// Dispatch request.
$router->dispatch(
    $_SERVER['REQUEST_URI'] ?? '/',
    $_SERVER['REQUEST_METHOD'] ?? 'GET'
);
