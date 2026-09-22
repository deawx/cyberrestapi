<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Route
 *      กำหนดเส้นทาง REST API แบบ static facade
 *      รองรับทุก method, Closure, group, middleware
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

use Closure;

class Route
{
    /** @var RouteHandler[] */
    private static array $routes = [];

    private static string $prefix = '';

    private static array $groupMiddleware = [];

    /** @var list<string> */
    private static array $groupRoles = [];

    // ==============================
    // HTTP Methods
    // ==============================

    public static function get(string $path, Closure|string $controller, array $options = []): void
    {
        self::add('GET', $path, $controller, $options);
    }

    public static function post(string $path, Closure|string $controller, array $options = []): void
    {
        self::add('POST', $path, $controller, $options);
    }

    public static function put(string $path, Closure|string $controller, array $options = []): void
    {
        self::add('PUT', $path, $controller, $options);
    }

    public static function patch(string $path, Closure|string $controller, array $options = []): void
    {
        self::add('PATCH', $path, $controller, $options);
    }

    public static function delete(string $path, Closure|string $controller, array $options = []): void
    {
        self::add('DELETE', $path, $controller, $options);
    }

    public static function options(string $path, Closure|string $controller, array $options = []): void
    {
        self::add('OPTIONS', $path, $controller, $options);
    }

    public static function any(string $path, Closure|string $controller, array $options = []): void
    {
        self::add('ANY', $path, $controller, $options);
    }

    // ==============================
    // Group
    // ==============================

    public static function group(string $prefix, array|string|null $options, ?Closure $callback = null): void
    {
        $oldPrefix = self::$prefix;
        $oldMiddleware = self::$groupMiddleware;
        $oldRoles = self::$groupRoles;

        self::$prefix = '/' . implode('/', array_filter([
            trim(self::$prefix, '/'),
            trim($prefix, '/'),
        ], static fn(string $part): bool => $part !== '')) . '/';
        if (self::$prefix === '//') {
            self::$prefix = '/';
        }

        if (is_string($options)) {
            self::$groupMiddleware = array_merge(self::$groupMiddleware, [$options]);
        } elseif (is_array($options)) {
            $mw = $options['middleware'] ?? [];
            self::$groupMiddleware = array_merge(self::$groupMiddleware, is_array($mw) ? $mw : [$mw]);
            self::$groupRoles = array_values(array_unique(array_merge(
                self::$groupRoles,
                self::normalizeRoles($options),
            )));
        } elseif (is_callable($options)) {
            $callback = $options;
        }

        if ($callback !== null) {
            $callback();
        }

        self::$prefix = $oldPrefix;
        self::$groupMiddleware = $oldMiddleware;
        self::$groupRoles = $oldRoles;
    }

    // ==============================
    // Internal
    // ==============================

    /**
     * @param array<string, mixed> $options
     */
    private static function add(
        string $method,
        string $path,
        Closure|string $controller,
        array $options = [],
    ): void {
        $fullPath = self::$prefix . ltrim($path, '/');

        $allMiddleware = self::$groupMiddleware;
        $middleware = $options['middleware'] ?? null;
        if (is_string($middleware)) {
            $allMiddleware[] = $middleware;
        } elseif (is_array($middleware)) {
            $allMiddleware = array_merge($allMiddleware, $middleware);
        }

        $roles = array_values(array_unique(array_merge(
            self::$groupRoles,
            self::normalizeRoles($options),
        )));

        if ($roles !== []) {
            if (!in_array(Middleware\JwtAuth::class, $allMiddleware, true)) {
                array_unshift($allMiddleware, Middleware\JwtAuth::class);
            }
            if (!in_array(Middleware\RequireRole::class, $allMiddleware, true)) {
                $allMiddleware[] = Middleware\RequireRole::class;
            }
        }

        self::$routes[] = new RouteHandler($method, $fullPath, $controller, $allMiddleware, $roles);
    }

    /**
     * @param array<string, mixed> $options
     * @return list<string>
     */
    private static function normalizeRoles(array $options): array
    {
        $roles = [];
        if (isset($options['role']) && is_string($options['role']) && $options['role'] !== '') {
            $roles[] = $options['role'];
        }

        if (isset($options['roles'])) {
            $list = is_array($options['roles']) ? $options['roles'] : [$options['roles']];
            foreach ($list as $role) {
                if (is_string($role) && $role !== '') {
                    $roles[] = $role;
                }
            }
        }

        return array_values(array_unique($roles));
    }

    /**
     * รัน router - หา route ที่ match และ specific ที่สุด
     */
    public static function run(): void
    {
        $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $requestUri    = self::requestPath();

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

    public static function getRoutes(): array
    {
        return self::$routes;
    }

    public static function clear(): void
    {
        self::$routes = [];
        self::$prefix = '';
        self::$groupMiddleware = [];
        self::$groupRoles = [];
    }

    /**
     * path ของ request โดยตัดโฟลเดอร์ย่อยของ Apache/XAMPP ออก
     */
    private static function requestPath(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = is_string($uri) && $uri !== '' ? $uri : '/';
        $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

        if ($base !== '/' && $base !== '.' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }

        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
