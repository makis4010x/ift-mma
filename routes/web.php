<?php

/**
 * @var \Framework\Router $router
 */

// Home
$router->get('/', 'HomeController@index');

// Public pages
$router->get('/academy', 'PageController@academy');

$router->get('/lessons', 'PageController@lessons');

$router->get('/schedule', 'PageController@schedule');

$router->get(
    '/personal-training',
    'PageController@personalTraining'
);

$router->get('/contact', 'PageController@contact');

$router->get(
    '/book-personal-training',
    'PageController@bookPersonalTraining'
);

// Authentication - temporary legacy controller
$router->get('/login', 'controllers/login.php');

// Development preview
$router->get(
    '/admin-preview',
    'controllers/admin-preview.php'
);
