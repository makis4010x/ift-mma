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


// Create student
$router->get(
    '/admin/students/new',
    'AdminStudentFormController@create'
);

$router->post(
    '/admin/students',
    'AdminStudentFormController@store'
);

// Edit student
$router->get(
    '/admin/students/{id}/edit',
    'AdminStudentFormController@edit'
);

$router->post(
    '/admin/students/{id}/update',
    'AdminStudentFormController@update'
);

// Attendance management
$router->get(
    '/admin/attendance',
    'AdminAttendanceController@index'
);

// Entrance barcode scanner
$router->get(
    '/admin/scanner',
    'AdminScannerController@index'
);

// Live student search inside the existing scanner page
$router->get(
    '/admin/scanner/search',
    'AdminScannerController@search'
);

$router->post(
    '/admin/scanner/scan',
    'AdminScannerController@scan'
);

// Student-facing scanner kiosk
$router->get(
    '/scanner',
    'KioskScannerController@index'
);

$router->post(
    '/scanner/check-in',
    'KioskScannerController@checkIn'
);

// Membership plans
$router->get(
    '/admin/membership-plans',
    'AdminMembershipPlansController@index'
);

$router->post(
    '/admin/membership-plans',
    'AdminMembershipPlansController@store'
);

// Edit program name, code and status
$router->post(
    '/admin/membership-plans/update',
    'AdminMembershipPlansController@update'
);

// Record a new historical price
$router->post(
    '/admin/membership-plans/price',
    'AdminMembershipPlansController@changePrice'
);

// Delete only unused programs without price changes
$router->post(
    '/admin/membership-plans/delete',
    'AdminMembershipPlansController@delete'
);

// Assign a membership to a student
$router->post(
    '/admin/students/{id}/memberships',
    'AdminStudentMembershipsController@store'
);

// Register a real student payment
$router->post(
    '/admin/students/{id}/payments',
    'AdminStudentPaymentsController@store'
);

// Cancel an existing payment without deleting its history
$router->post(
    '/admin/students/{id}/payments/void',
    'AdminStudentPaymentsController@voidPayment'
);

// School settings
$router->get(
    '/admin/settings',
    'AdminSettingsController@index'
);

$router->post(
    '/admin/settings',
    'AdminSettingsController@update'
);


// Manual activity exception or return to automatic mode.
$router->post(
    '/admin/students/{id}/activity',
    'AdminStudentActivityController@update'
);
