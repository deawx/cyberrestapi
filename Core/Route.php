<?php

/**
 * Core\Route - Static Facade สำหรับกำหนดและรัน routes ของ REST API
 * รองรับทุก HTTP method, Closure, Controller@method, route group (prefix + middleware stack)
 * แก้ปัญหา Expected type 'array'. Found 'bool' โดยไม่ใช้ผลลัพธ์จาก match() เป็น params
 *
 * @version 1.0.1 (December 23, 2025) - แก้ bug match() return bool
 * @author (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 * @license MIT https://cyberthai.net
 */

declare(strict_types=1);

namespace Core;

use Closure;

class Route {
    /** @var RouteHandler[] */
    private static array $routes = [];

    private static string $prefix = '';

    private static array $groupMiddleware = [];

    // ==============================
    // HTTP Methods
    // ==============================

    public static function get(string $path, Closure|string $controller, array $options = []): void {
        self::add('GET', $path, $controller, $options['middleware'] ?? null);
    }

    public static function post(string $path, Closure|string $controller, array $options = []): void {
        self::add('POST', $path, $controller, $options['middleware'] ?? null);
    }

    public static function put(string $path, Closure|string $controller, array $options = []): void {
        self::add('PUT', $path, $controller, $options['middleware'] ?? null);
    }

    public static function patch(string $path, Closure|string $controller, array $options = []): void {
        self::add('PATCH', $path, $controller, $options['middleware'] ?? null);
    }

    public static function delete(string $path, Closure|string $controller, array $options = []): void {
        self::add('DELETE', $path, $controller, $options['middleware'] ?? null);
    }

    public static function options(string $path, Closure|string $controller, array $options = []): void {
        self::add('OPTIONS', $path, $controller, $options['middleware'] ?? null);
    }

    public static function any(string $path, Closure|string $controller, array $options = []): void {
        self::add('ANY', $path, $controller, $options['middleware'] ?? null);
    }

    // ==============================
    // Group
    // ==============================

    public static function group(string $prefix, array|string|null $options, ?Closure $callback = null): void {
        $oldPrefix = self::$prefix;
        $oldMiddleware = self::$groupMiddleware;

        self::$prefix = rtrim(self::$prefix . '/' . trim($prefix, '/'), '/') . '/';

        if (is_string($options)) {
            self::$groupMiddleware = array_merge(self::$groupMiddleware, [$options]);
        } elseif (is_array($options)) {
            $mw = $options['middleware'] ?? [];
            self::$groupMiddleware = array_merge(self::$groupMiddleware, is_array($mw) ? $mw : [$mw]);
        } elseif (is_callable($options)) {
            $callback = $options;
        }

        if ($callback !== null) {
            $callback();
        }

        self::$prefix = $oldPrefix;
        self::$groupMiddleware = $oldMiddleware;
    }

    // ==============================
    // Internal
    // ==============================

    private static function add(string $method, string $path, Closure|string $controller, array|string|null $middleware = null): void {
        $fullPath = self::$prefix . ltrim($path, '/');

        $allMiddleware = self::$groupMiddleware;
        if (is_string($middleware)) {
            $allMiddleware[] = $middleware;
        } elseif (is_array($middleware)) {
            $allMiddleware = array_merge($allMiddleware, $middleware);
        }

        self::$routes[] = new RouteHandler($method, $fullPath, $controller, $allMiddleware);
    }

    /**
     * รัน router - หา route ที่ match และ specific ที่สุด
     */
    public static function run(): void {
        $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $requestUri    = $_SERVER['REQUEST_URI'] ?? '/';

        $bestMatch = null;
        $longestLength = -1;

        /** @var RouteHandler $route */
        foreach (self::$routes as $route) {
            // ใช้ match() ที่คืน bool และ set params ภายใน
            if ($route->match($requestMethod, $requestUri)) {
                $pathLength = strlen($route->getPath());
                if ($pathLength > $longestLength) {
                    $longestLength = $pathLength;
                    $bestMatch = $route;
                }
            }
        }

        if ($bestMatch !== null) {
            $bestMatch->execute();
        } else {
            Response::error('Route not found', 404);
        }
    }

    public static function getRoutes(): array {
        return self::$routes;
    }

    public static function clear(): void {
        self::$routes = [];
        self::$prefix = '';
        self::$groupMiddleware = [];
    }
}
