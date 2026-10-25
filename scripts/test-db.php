<?php
declare(strict_types=1);

use Framework\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $config = \Framework\Config::get('database');

    $db = new Database($config);

    $result = $db->query(
        'SELECT DATABASE() AS database_name'
    )->fetch();

    echo 'Database connection successful!' . PHP_EOL;

    echo 'Connected to: '
        . $result['database_name']
        . PHP_EOL;

} catch (PDOException $exception) {
    fwrite(
        STDERR,
        'Database connection failed. Check MySQL and your local configuration.'
        . PHP_EOL
    );

    exit(1);
}
