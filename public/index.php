<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../helpers.php';

// Start the session before any HTML output.
\Framework\Session::start();

$router = new \Framework\Router();

require basePath('routes/web.php');

$router->dispatch(
    $_SERVER['REQUEST_URI'] ?? '/',
    $_SERVER['REQUEST_METHOD'] ?? 'GET'
);
