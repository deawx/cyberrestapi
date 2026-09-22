<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\RouteHandler
 *      จับคู่ URI แล้วเรียก controller หรือ Closure
 *      ส่ง Request และพารามิเตอร์ของเส้นทาง
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

use Closure;
use Core\Request;
use Core\Response;

class RouteHandler
{
    private string $method;
    private string $path;
    private Closure|string $controller;
    private array $params = [];
    private array $middleware = [];
    /** @var list<string> */
    private array $requiredRoles = [];
    private static array $pathRegexCache = [];

    /**
     * @param list<string> $requiredRoles
     */
    public function __construct(
        string $method,
        string $path,
        Closure|string $controller,
        string|array|null $middleware = null,
        array $requiredRoles = [],
    ) {
        $this->method = strtoupper($method);
        $this->path = '/' . trim($path, '/');
        $this->controller = $controller;
        $this->requiredRoles = array_values(array_filter(
            $requiredRoles,
            static fn(mixed $role): bool => is_string($role) && $role !== '',
        ));

        if (is_string($middleware) && $middleware !== '') {
            $this->middleware = [$middleware];
        } elseif (is_array($middleware)) {
            $this->middleware = $middleware;
        } else {
            $this->middleware = [];
        }

        if (!isset(self::$pathRegexCache[$this->path])) {
            self::$pathRegexCache[$this->path] = $this->generatePathRegex($this->path);
        }
    }

    private function generatePathRegex(string $path): string
    {
        $path_regex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([a-zA-Z0-9_-]+)', $path) ?? $path;
        $path_regex = str_replace('/', '\/', $path_regex);
        return '/^' . $path_regex . '\/?(\?.*)?$/';
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getController()
    {
        return $this->controller;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * @return list<string>
     */
    public function getRequiredRoles(): array
    {
        return $this->requiredRoles;
    }

    public function match(string $request_method, string $request_uri): bool
    {
        $request_method = strtoupper($request_method);
        $request_path = '/' . trim($request_uri, '/');

        $methodOk = $this->method === 'ANY' || $this->method === $request_method;
        if (
            $methodOk
            && preg_match(self::$pathRegexCache[$this->path], $request_path, $matches) === 1
        ) {
            $this->params = array_slice($matches, 1);
            return true;
        }
        return false;
    }

    public function execute(): void
    {
        try {
            $request = Request::create();
            if ($this->requiredRoles !== []) {
                $request->setAttribute('required_roles', $this->requiredRoles);
            }

            foreach ($this->middleware as $middlewareClass) {
                if (!is_string($middlewareClass) || $middlewareClass === '') {
                    throw new \Exception('Middleware must be a class name');
                }
                if (!class_exists($middlewareClass)) {
                    throw new \Exception("Middleware class '{$middlewareClass}' not found");
                }
                $middleware = new $middlewareClass();
                if (!method_exists($middleware, 'handle')) {
                    throw new \Exception("Middleware '{$middlewareClass}' must have 'handle' method");
                }

                $nextCalled = false;
                $middleware->handle($request, function () use (&$nextCalled) {
                    $nextCalled = true;
                });

                if (!$nextCalled) {
                    return;
                }
            }

            if ($this->controller instanceof Closure) {
                call_user_func_array($this->controller, array_merge([$request], $this->params));
            } else {
                $this->invokeController($this->controller, $request);
            }
        } catch (\Throwable $e) {
            Log::write('Route execute error: ' . $e->getMessage(), 'error');
            Response::handleException($e);
        }
    }

    private function invokeController(string $controllerAction, Request $request): void
    {
        $parts = explode('@', $controllerAction, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \Exception("Invalid controller action '{$controllerAction}'");
        }
        [$controller, $method] = $parts;
        $controllerClass = "App\\Controllers\\" . $controller;

        if (!class_exists($controllerClass)) {
            throw new \Exception("Controller class '$controllerClass' not found");
        }

        $controllerInstance = new $controllerClass($request);

        if (!method_exists($controllerInstance, $method)) {
            throw new \Exception("Method '$method' not found in controller '$controllerClass'");
        }

        $params = array_merge([$request], $this->params);
        call_user_func_array([$controllerInstance, $method], $params);
    }
}
