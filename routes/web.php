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


// Development preview
$router->get(
    '/admin-preview',
    'controllers/admin-preview.php'
);

// Admin authentication
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->post('/logout', 'AuthController@logout');

// Protected admin landing page
$router->get('/admin', 'AdminController@index');

// Admin management
$router->get(
    '/admin/administrators',
    'AdminAdministratorsController@index'
);

$router->post(
    '/admin/administrators',
    'AdminAdministratorsController@store'
);

$router->post(
    '/admin/administrators/{id}/status',
    'AdminAdministratorsController@changeStatus'
);

$router->post(
    '/admin/administrators/{id}/delete',
    'AdminAdministratorsController@destroy'
);

// Student management
$router->get(
    '/admin/students',
    'AdminStudentsController@index'
);

$router->get(
    '/admin/students/{id}',
    'AdminStudentsController@show'
);
