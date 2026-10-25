<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../helpers.php';

$router = new \Framework\Router();

require basePath('routes.php');

$router->dispatch(
    $_SERVER['REQUEST_URI'] ?? '/',
    $_SERVER['REQUEST_METHOD'] ?? 'GET'
);
