<?php

declare(strict_types=1);

namespace Framework;

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

    public function dispatch(string $requestUri, string $method): void
    {
        $uri = parse_url($requestUri, PHP_URL_PATH) ?: '/';

        $action = $this->routes[strtoupper($method)][$uri] ?? null;

        if ($action === null) {
            \App\Controllers\ErrorController::notFound();
            return;
        }

        // Νέο MVC routing: HomeController@index
        if (str_contains($action, '@')) {
            [$controller, $controllerMethod] = explode('@', $action, 2);

            $controllerClass = 'App\\Controllers\\' . $controller;

            if (!class_exists($controllerClass)) {
                throw new RuntimeException(
                    "Controller {$controllerClass} not found."
                );
            }

            $instance = new $controllerClass();

            if (!is_callable([$instance, $controllerMethod])) {
                throw new RuntimeException(
                    "Method {$controllerMethod} not found in {$controllerClass}."
                );
            }

            $instance->$controllerMethod();

            return;
        }

        // Συμβατότητα με τους παλιούς PHP controllers
        require basePath($action);
    }
}
