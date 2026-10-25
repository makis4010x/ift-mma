<?php

// Επιτρέπουμε το Admin Preview μόνο τοπικά.

$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

if (!in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
    http_response_code(404);
    exit('Page not found');
}

loadView('admin-header');