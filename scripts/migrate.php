<?php

declare(strict_types=1);

use Framework\Config;
use Framework\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

if (Config::get('app.environment') !== 'local') {
    fwrite(STDERR, "Local migrations only.\n");
    exit(1);
}

try {
    $db = new Database(Config::get('database'));

    // Table for tracking executed migrations.
    $db->query("
        CREATE TABLE IF NOT EXISTS schema_migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(190) NOT NULL UNIQUE,
            applied_at TIMESTAMP NOT NULL
                DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");

    // Get migrations already executed.
    $executed = $db->query(
        'SELECT migration FROM schema_migrations'
    )->fetchAll(PDO::FETCH_COLUMN);

    $executed = array_fill_keys($executed, true);

    $files = glob(
        dirname(__DIR__) . '/database/migrations/*.php'
    ) ?: [];

    sort($files, SORT_STRING);

    foreach ($files as $file) {
        $name = pathinfo($file, PATHINFO_FILENAME);

        if (isset($executed[$name])) {
            echo "[SKIP] {$name}\n";
            continue;
        }

        echo "[RUN] {$name}\n";

        $migration = require $file;

        if (!$migration instanceof Closure) {
            throw new RuntimeException(
                "Invalid migration: {$name}"
            );
        }

        $migration($db);

        $db->query(
            'INSERT INTO schema_migrations (migration)
             VALUES (:migration)',
            ['migration' => $name]
        );

        echo "[DONE] {$name}\n";
    }

    echo "Migrations completed.\n";

} catch (Throwable $exception) {
    fwrite(
        STDERR,
        "Migration failed: {$exception->getMessage()}\n"
    );

    exit(1);
}
