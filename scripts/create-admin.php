<?php

declare(strict_types=1);

use Framework\Config;
use Framework\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

// This script is only for local CLI setup.
if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

if (Config::get('app.environment') !== 'local') {
    exit("Local environment only.\n");
}

// Credentials are read from STDIN, never from arguments.
// Expected input: email followed by password.
$emailInput = fgets(STDIN);
$passwordInput = fgets(STDIN);

if ($emailInput === false || $passwordInput === false) {
    exit("Missing admin credentials.\n");
}

$email = strtolower(trim($emailInput));
$password = rtrim($passwordInput, "\r\n");

if (
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($email) > 254
) {
    exit("Invalid email address.\n");
}

// PASSWORD_DEFAULT currently uses bcrypt.
// Limit to 72 bytes to avoid password truncation.
if (
    strlen($password) < 8 ||
    strlen($password) > 72
) {
    exit("Password must be 14-72 bytes.\n");
}

$db = new Database(Config::get('database'));

// Bootstrap only the very first administrator.
$adminCount = (int) $db->query(
    "SELECT COUNT(*)
     FROM users
     WHERE role = 'admin'"
)->fetchColumn();

if ($adminCount > 0) {
    exit("An admin account already exists.\n");
}

$hash = password_hash($password, PASSWORD_DEFAULT);

if (!is_string($hash)) {
    exit("Password hashing failed.\n");
}

try {
    $db->query(
        "INSERT INTO users (
            student_id,
            email,
            password_hash,
            role,
            status,
            email_verified_at
        ) VALUES (
            NULL,
            :email,
            :password_hash,
            'admin',
            'active',
            NULL
        )",
        [
            'email' => $email,
            'password_hash' => $hash
        ]
    );
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        "Could not create admin. "
        . "Check whether the email is already used.\n"
    );

    exit(1);
}

echo "Admin account created successfully.\n";
echo "Email: {$email}\n";
echo "Role: admin\n";
echo "Status: active\n";
