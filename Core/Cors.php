<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Cors
 *      จัดการ CORS อย่างปลอดภัย
 *      รองรับ origin, method, header, credentials, preflight
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

final class Cors
{
    /** Origins ที่อนุญาต (เช่น ['https://example.com']) */
    private static array $allowedOrigins = [];

    /** HTTP methods ที่อนุญาต */
    private static array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    /** Request headers ที่อนุญาต */
    private static array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin'];

    /** Response headers ที่ client เห็นได้ */
    private static array $exposedHeaders = [];

    /** อายุ cache preflight request (วินาที) */
    private static int $maxAge = 86400; // 1 วัน

    /** อนุญาต credentials (cookies, HTTP auth) */
    private static bool $allowCredentials = false;

    /**
     * ตั้งค่า allowed origins
     * @param string|array $origins '*' หรือ array ของ URL
     */
    public static function origins(string|array $origins): void
    {
        if (is_string($origins)) {
            $origins = array_map('trim', explode(',', $origins));
        }
        self::$allowedOrigins = array_values(array_unique(array_filter($origins, static fn($item) => $item !== '')));
    }

    /**
     * ตั้งค่า allowed methods
     */
    public static function methods(string|array $methods): void
    {
        if (is_string($methods)) {
            $methods = [$methods];
        }
        self::$allowedMethods = array_unique(array_map('strtoupper', array_map('trim', $methods)));
    }

    /**
     * ตั้งค่า allowed headers
     */
    public static function headers(string|array $headers): void
    {
        if (is_string($headers)) {
            $headers = [$headers];
        }
        self::$allowedHeaders = array_unique(array_map('trim', $headers));
    }

    /**
     * ตั้งค่า exposed headers
     */
    public static function expose(string|array $headers): void
    {
        if (is_string($headers)) {
            $headers = [$headers];
        }
        self::$exposedHeaders = array_unique(array_map('trim', $headers));
    }

    /**
     * ตั้งค่าอายุ preflight cache
     */
    public static function maxAge(int $seconds): void
    {
        self::$maxAge = max(0, $seconds);
    }

    /**
     * อนุญาต credentials
     */
    public static function credentials(bool $allow = true): void
    {
        self::$allowCredentials = $allow;
    }

    /**
     * ส่ง CORS headers และจัดการ preflight request
     * เรียกใน index.php ก่อน Route::run()
     */
    public static function handle(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Validate และตั้งค่า Origin
        if (in_array('*', self::$allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: *');
            // ถ้าใช้ * ห้าม allow credentials
            self::$allowCredentials = false;
        } elseif ($origin && in_array($origin, self::$allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: {$origin}");
            if (self::$allowCredentials) {
                header('Access-Control-Allow-Credentials: true');
            }
        } else {
            // Origin ไม่ได้รับอนุญาต → ไม่ส่ง header ใด ๆ (browser จะ block)
            return;
        }

        // Exposed headers
        if (!empty(self::$exposedHeaders)) {
            header('Access-Control-Expose-Headers: ' . implode(', ', self::$exposedHeaders));
        }

        // Max age
        if (self::$maxAge > 0) {
            header('Access-Control-Max-Age: ' . self::$maxAge);
        }

        // Allowed methods
        if (!empty(self::$allowedMethods)) {
            header('Access-Control-Allow-Methods: ' . implode(', ', self::$allowedMethods));
        }

        // Allowed headers
        if (!empty(self::$allowedHeaders)) {
            header('Access-Control-Allow-Headers: ' . implode(', ', self::$allowedHeaders));
        }

        // จัดการ Preflight request (OPTIONS)
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    /**
     * ตั้งค่า CORS แบบเร็วจาก .env หรือ config
     * ตัวอย่างใน index.php:
     * Cors::quick($_ENV['APP_URL'] ?? '*');
     */
    public static function quick(string $origin = '*', bool $credentials = false): void
    {
        $list = array_values(array_filter(array_map('trim', explode(',', $origin))));
        self::origins($list === [] ? ['*'] : $list);
        self::credentials($credentials);
        if ($credentials && $origin !== '*') {
            // เพิ่ม header พื้นฐานสำหรับ auth
            self::headers(['Content-Type', 'Authorization', 'X-Requested-With']);
        }
    }
}
