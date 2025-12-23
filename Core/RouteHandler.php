<?php

/**
 * Core\RouteHandler - ปรับปรุงจากโค้ดของคุณโดยตรง
 * แก้ปัญหา type error ถาวร + ลบ Whoops + ใช้ Response แทน
 */

declare(strict_types=1);

namespace Core;

use Core\Request;
use Core\Response;

class RouteHandler {
    private string $method;
    private string $path;
    private $controller;
    private array $params = [];
    private array $middleware = [];               // ← จุดที่ 1: แก้จาก ?string เป็น array เต็มตัว
    private static array $pathRegexCache = [];

    public function __construct(string $method, string $path, $controller, string|array|null $middleware = null) {
        $this->method = strtoupper($method);
        $this->path = '/' . trim($path, '/');
        $this->controller = $controller;

        // จุดที่ 2: แปลง middleware ให้เป็น array เสมอ
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

    private function generatePathRegex(string $path): string {
        $path_regex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([a-zA-Z0-9_-]+)', $path);
        $path_regex = str_replace('/', '\/', $path_regex);
        return '/^' . $path_regex . '\/?(\?.*)?$/';
    }

    public function getMethod(): string {
        return $this->method;
    }

    public function getPath(): string {
        return $this->path;
    }

    public function getController() {
        return $this->controller;
    }

    public function getParams(): array {
        return $this->params;
    }

    public function setParams(array $params): void {
        $this->params = $params;
    }

    public function getMiddleware(): array {
        return $this->middleware;
    }

    public function match(string $request_method, string $request_uri): bool {
        $request_method = strtoupper($request_method);
        $parsed_url = parse_url($request_uri);
        $request_path = '/' . trim($parsed_url['path'] ?? '', '/');

        if (
            $this->method === $request_method &&
            preg_match(self::$pathRegexCache[$this->path], $request_path, $matches)
        ) {
            $this->params = array_slice($matches, 1);
            return true;
        }
        return false;
    }

    public function execute(): void {
        try {
            $request = Request::create();

            // รัน middleware (ตอนนี้รองรับหลายตัวได้แล้ว)
            foreach ($this->middleware as $middlewareClass) {
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

            if (is_callable($this->controller)) {
                call_user_func_array($this->controller, array_merge([$request], $this->params));
            } else {
                $this->invokeController($this->controller, $request);
            }
        } catch (\Throwable $e) {
            // Log error
            $logFile = __DIR__ . '/../storage/logs/error.log';
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            error_log("[" . date('Y-m-d H:i:s') . "] Route execute error: {$e->getMessage()}\n", 3, $logFile);

            // ใช้ Response แทน whoops
            Response::handleException($e);
        }
    }

    private function invokeController(string $controllerAction, Request $request): void {
        [$controller, $method] = explode('@', $controllerAction);
        $controllerClass = "App\\Controllers\\" . $controller;

        if (!class_exists($controllerClass)) {
            throw new \Exception("Controller class '$controllerClass' not found");
        }

        $controllerInstance = new $controllerClass;

        if (!method_exists($controllerInstance, $method)) {
            throw new \Exception("Method '$method' not found in controller '$controllerClass'");
        }

        $params = array_merge([$request], $this->params);
        call_user_func_array([$controllerInstance, $method], $params);
    }
}
