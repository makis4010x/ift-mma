<?php

declare(strict_types=1);


$environment = getenv('APP_ENV') ?: 'local';
$host = getenv('DB_HOST');

if ($host !== false && $host !== '') {
    $dbname = getenv('DB_NAME');
    $username = getenv('DB_USER');
    $password = getenv('DB_PASSWORD');

    if (
        !$dbname ||
        !$username
    ) {
        throw new RuntimeException(
            'Missing database environment configuration.'
        );
    }

    if (
        $environment === 'production' &&
        ($password === false || $password === '')
    ) {
        throw new RuntimeException(
            'Database password is required in production.'
        );
    }

    return [
        'host' => $host,
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'dbname' => $dbname,
        'username' => $username,
        'password' => $password === false ? '' : $password,
        'charset' => 'utf8mb4'
    ];
}

$localConfig = __DIR__ . '/database.local.php';

if (
    $environment !== 'production' &&
    is_file($localConfig)
) {
    return require $localConfig;
}

throw new RuntimeException(
    'Database configuration is missing.'
);
