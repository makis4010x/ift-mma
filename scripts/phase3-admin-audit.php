<?php

declare(strict_types=1);

// Read-only audit. Δεν τροποποιεί αρχεία ή βάση.

if (PHP_SAPI !== 'cli') {
    exit('Run from CMD only.');
}

$root = dirname(__DIR__);

function readSource(string $relative): ?string
{
    global $root;

    $path = $root . '/' . $relative;

    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    return file_get_contents($path);
}

function result(
    string $name,
    bool $ok,
    string $note = ''
): void {
    echo ($ok ? '[FOUND] ' : '[CHECK] ') . $name;

    if ($note !== '') {
        echo ' - ' . $note;
    }

    echo PHP_EOL;
}

echo PHP_EOL;
echo "IFT ACADEMY - PHASE 3 ADMIN AUDIT";
echo PHP_EOL;
echo str_repeat('=', 42) . PHP_EOL;

$files = [
    'Session' => 'Framework/Session.php',
    'Authentication' => 'App/Controllers/AuthController.php',
    'AdminGuard' => 'App/Support/AdminGuard.php',
    'CSRF' => 'Framework/Csrf.php',
    'Routes' => 'routes/web.php'
];

$sources = [];

foreach ($files as $name => $path) {
    $sources[$name] = readSource($path);

    result(
        $name . ' file',
        $sources[$name] !== null
    );
}

// SESSION
echo PHP_EOL . "--- SESSION ---" . PHP_EOL;

$session = $sources['Session'] ?? '';

result(
    'Session regeneration',
    str_contains($session, 'session_regenerate_id(')
);

result(
    'Strict session IDs',
    str_contains($session, 'session.use_strict_mode')
);

result(
    'HttpOnly cookies',
    str_contains($session, "'httponly' => true")
);

result(
    'SameSite cookies',
    str_contains($session, "'samesite' => 'Lax'")
);

result(
    'Session retention configured',
    str_contains($session, 'session.gc_maxlifetime')
);

result(
    'Private session directory',
    str_contains($session, '/storage/sessions')
);

// AUTHENTICATION
echo PHP_EOL . "--- AUTHENTICATION ---" . PHP_EOL;

$auth = $sources['Authentication'] ?? '';

result(
    'Password verification',
    str_contains($auth, 'password_verify(')
);

result(
    'Login CSRF protection',
    str_contains($auth, 'Csrf::protect()')
);

result(
    'Login regenerates session',
    str_contains($auth, 'Session::regenerate()')
);

result(
    'Logout handler',
    str_contains($auth, 'function logout(')
);

result(
    'Login rate limiting',
    str_contains($auth, 'login_blocked_until'),
    'Check if rate limiting is session-only'
);

// ADMIN GUARD
echo PHP_EOL . "--- ADMIN GUARD ---" . PHP_EOL;

$guard = $sources['AdminGuard'] ?? '';

result(
    'Admin authorization method',
    str_contains($guard, 'function requireAdmin(')
);

result(
    'Active admin DB verification',
    str_contains($guard, "status = 'active'")
);

result(
    'Private cache protection',
    str_contains($guard, 'Cache-Control: no-store')
);

// CSRF
echo PHP_EOL . "--- CSRF ---" . PHP_EOL;

$csrf = $sources['CSRF'] ?? '';

result(
    'CSRF token verification',
    str_contains($csrf, 'hash_equals(')
);

// ROUTES
echo PHP_EOL . "--- ADMIN ROUTES ---" . PHP_EOL;

$routes = $sources['Routes'] ?? '';

preg_match_all(
    '~\$router->(?:get|post)\s*\(\s*[\'"](/admin[^\'"]*)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]~s',
    $routes,
    $matches,
    PREG_SET_ORDER
);

echo 'Admin route patterns: '
    . count($matches)
    . PHP_EOL;

$controllers = [];

foreach ($matches as $route) {
    if (!str_contains($route[2], '@')) {
        continue;
    }

    [$controller] = explode('@', $route[2], 2);

    $controllers[$controller] = true;
}

foreach (array_keys($controllers) as $controller) {
    $body = readSource(
        'App/Controllers/' . $controller . '.php'
    );

    result(
        'Guard in ' . $controller,
        $body !== null
            && str_contains(
                $body,
                'AdminGuard::requireAdmin('
            )
    );
}

result(
    'Public admin preview route removed',
    !str_contains($routes, "'/admin-preview'")
);

// TAILWIND
echo PHP_EOL . "--- TAILWIND ---" . PHP_EOL;

$viewsDirectory = $root . '/App/Views';

$cdnFiles = [];

if (is_dir($viewsDirectory)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $viewsDirectory,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        if (!preg_match(
            '/\.(php|html)$/i',
            $file->getFilename()
        )) {
            continue;
        }

        $contents = file_get_contents(
            $file->getPathname()
        );

        if (
            $contents !== false
            && (
                str_contains(
                    $contents,
                    'cdn.tailwindcss.com'
                )
                || str_contains(
                    $contents,
                    '@tailwindcss/browser'
                )
            )
        ) {
            $cdnFiles[] = $file->getFilename();
        }
    }
}

result(
    'Tailwind Play CDN not used',
    $cdnFiles === [],
    implode(', ', $cdnFiles)
);

echo PHP_EOL;
echo 'Audit completed. No changes made.';
echo PHP_EOL;
echo 'Static checks only: runtime security still requires testing.';
echo PHP_EOL;
