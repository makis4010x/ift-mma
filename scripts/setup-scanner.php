<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('CLI only.');
}

$path = dirname(__DIR__) . '/config/scanner.local.php';
if (is_file($path)) {
    exit("Scanner already configured.\n");
}

$code = strtoupper(bin2hex(random_bytes(12)));
$hash = password_hash($code, PASSWORD_DEFAULT);
$content = "<?php\n\ndeclare(strict_types=1);\n\nreturn ['pin_hash' => "
    . var_export($hash, true) . "];\n";

if (file_put_contents($path, $content, LOCK_EX) === false) {
    throw new RuntimeException('Cannot save scanner config.');
}

echo "Scanner activation code (save privately): {$code}\n";
echo "The code is not stored in plaintext.\n";
