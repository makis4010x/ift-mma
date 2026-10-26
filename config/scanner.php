<?php

declare(strict_types=1);

$local = __DIR__ . '/scanner.local.php';
return is_file($local)
    ? require $local
    : ['pin_hash' => ''];
