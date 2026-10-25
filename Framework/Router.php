<?php

declare(strict_types=1);

namespace Framework;

use App\Controllers\ErrorController;
use RuntimeException;

final class Router
{
    private array $routes = [];

    public function get(string $uri, string $action): void
    {
        $this->routes['GET'][$uri] = $action;
    }

    public function post(string $uri, string $action): void
    {
        $this->routes['POST'][$uri] = $action;
    }

    public function dispatch(
        string $requestUri,
        string $method
    ): void {
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $method = strtoupper($method);

        $allowedMethods = [];

        foreach ($this->routes as $routeMethod => $routes) {
            $match = null;

            // Exact routes have priority.
            if (isset($routes[$path])) {
                $match = [
                    'action' => $routes[$path],
                    'params' => []
                ];
            } else {
                foreach ($routes as $pattern => $action) {
                    $params = $this->matchPath(
                        $pattern,
                        $path
                    );

                    if ($params !== null) {
                        $match = [
                            'action' => $action,
                            'params' => $params
                        ];
                        break;
                    }
                }
            }

            if ($match === null) {
                continue;
            }

            if ($routeMethod === $method) {
                $this->execute(
                    $match['action'],
                    $match['params']
                );
                return;
            }

            $allowedMethods[] = $routeMethod;
        }

        // The URL exists, but not for this HTTP method.
        if ($allowedMethods !== []) {
            http_response_code(405);

            header(
                'Allow: ' . implode(', ', $allowedMethods)
            );
            header('Content-Type: text/plain; charset=utf-8');

            echo '405 Method Not Allowed';
            return;
        }

        ErrorController::notFound();
    }

    private function matchPath(
        string $pattern,
        string $path
    ): ?array {
        if ($pattern === $path) {
            return [];
        }

        $patternParts = explode(
            '/',
            trim($pattern, '/')
        );

        $pathParts = explode(
            '/',
            trim($path, '/')
        );

        if (count($patternParts) !== count($pathParts)) {
            return null;
        }

        $params = [];

        foreach ($patternParts as $index => $part) {
            if (preg_match(
                '/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/D',
                $part,
                $matches
            )) {
                if ($pathParts[$index] === '') {
                    return null;
                }

                $params[$matches[1]] = rawurldecode(
                    $pathParts[$index]
                );
            } elseif ($part !== $pathParts[$index]) {
                return null;
            }
        }

        return $params;
    }

    private function execute(
        string $action,
        array $params
    ): void {
        // MVC Controller@method
        if (str_contains($action, '@')) {
            [$controller, $controllerMethod] = explode(
                '@',
                $action,
                2
            );

            $controllerClass =
                'App\\Controllers\\' . $controller;

            if (!class_exists($controllerClass)) {
                throw new RuntimeException(
                    "Controller not found: {$controllerClass}"
                );
            }

            $instance = new $controllerClass();

            if (!is_callable([$instance, $controllerMethod])) {
                throw new RuntimeException(
                    "Controller method not found: {$controllerMethod}"
                );
            }

            if ($params === []) {
                $instance->$controllerMethod();
            } else {
                $instance->$controllerMethod($params);
            }

            return;
        }

        // Temporary support for legacy PHP controllers.
        require basePath($action);
    }
}
