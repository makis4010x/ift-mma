<?php

declare(strict_types=1);

use Framework\Session;
use Framework\Csrf;
use Framework\Validator;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/helpers.php';

Session::start();

Session::put('test_key', 'IFT Academy');

$token = Csrf::token();

Session::flash('success', 'Saved successfully');

$tests = [
    'Session' =>
        Session::get('test_key') === 'IFT Academy',

    'CSRF valid token' =>
        Csrf::verify($token),

    'CSRF invalid token' =>
        !Csrf::verify('invalid-token'),

    'Required validation' =>
        Validator::required('IFT'),

    'Invalid email rejected' =>
        !Validator::email('invalid-email'),

    'Valid email accepted' =>
        Validator::email('student@example.com'),

    'Greek UTF-8 length' =>
        Validator::maxLength('Μαρία', 5),

    'Flash message' =>
        Session::getFlash('success')
            === 'Saved successfully',

    'HTML escaping' =>
        e('<script>') === '&lt;script&gt;'
];

$failed = false;

foreach ($tests as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ')
        . $name . PHP_EOL;

    if (!$passed) {
        $failed = true;
    }
}

Session::forget('test_key');

exit($failed ? 1 : 0);
