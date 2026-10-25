<?php
require '../helpers.php';

$routes = [
    '/' => 'controllers/home.php',
    '/academy' => 'controllers/academy.php',
    '/lessons' => 'controllers/lessons.php',
    '/schedule' => 'controllers/schedule.php',
    '/personal-training' => 'controllers/personal-training.php',
    '/contact' => 'controllers/contact.php',
    '/book-personal-training' => 'controllers/book-personal-training.php',
    '/login' => 'controllers/login.php',
    '404' => 'controllers/error/404.php',

    '/admin-preview' => 'controllers/admin-preview.php',
];

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

if (array_key_exists($uri, $routes)) {
    require basePath($routes[$uri]);
} else {
    require basePath($routes['404']);
}