<?php

/**
 * @var \Framework\Router $router
 */

// Public website
$router->get('/', 'HomeController@index');
$router->get('/academy', 'controllers/academy.php');
$router->get('/lessons', 'controllers/lessons.php');
$router->get('/schedule', 'controllers/schedule.php');

$router->get('/personal-training', 'controllers/personal-training.php');
$router->get('/contact', 'controllers/contact.php');
$router->get('/book-personal-training', 'controllers/book-personal-training.php');

// Authentication
$router->get('/login', 'controllers/login.php');

// Development only
$router->get('/admin-preview', 'controllers/admin-preview.php');