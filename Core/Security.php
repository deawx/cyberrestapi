<?php

/**
 * Core\Security - คลาสจัดการความปลอดภัยทั้งหมดของ REST API
 * รวม Security Headers, CSP, Input Sanitization, CSRF, JWT, Password Hash
 * ตามมาตรฐาน OWASP Top 10 และ Best Practices ปี 2026
 *
 * @version 1.0.1 (December 23, 2025) - ลบ secureSession() (ใช้ Session::start() แทน)
 * @author (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 * @license MIT https://cyberthai.net
 */

declare(strict_types=1);

namespace Core;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Core\Response;

final class Security {
    private static array $cspDirectives = [
        'default-src' => "'self'",
        'script-src'  => "'self' 'unsafe-inline'",
        'style-src'   => "'self' 'unsafe-inline'",
        'img-src'     => "'self' data: https:",
        'font-src'    => "'self' https:",
        'connect-src' => "'self'",
        'frame-src'   => "'none'",
        'object-src'  => "'none'",
        'base-uri'    => "'self'",
        'form-action' => "'self'",
    ];

    /**
     * ตั้งค่า HTTP Security Headers ทั้งหมด
     */
    public static function setSecurityHeaders(): void {
        // ปิด CSP ชั่วคราวใน dev
        if ($_ENV['APP_ENV'] ?? 'prod' === 'dev') {
            return; // ไม่ตั้ง header อะไรเลย → โหลด CDN ได้
        }
        header('Content-Security-Policy: ' . self::buildCSP(), true);
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 0');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }

    private static function buildCSP(): string {
        $parts = [];
        foreach (self::$cspDirectives as $directive => $value) {
            $directive = preg_replace('/[^a-zA-Z0-9-]/', '', $directive);
            $value = preg_replace('/[\r\n\t;]/', '', $value);
            if ($directive && $value) {
                $parts[] = $directive . ' ' . $value;
            }
        }
        return implode('; ', $parts);
    }

    // ลบ secureSession() ออกทั้งหมด
    // ใช้ Session::start($timeout) แทนใน index.php

    /**
     * Sanitize input string
     */
    public static function sanitize(?string $input, string $type = 'text', int $maxLength = 1000): ?string {
        if ($input === null || $input === '') return null;

        $input = trim($input);
        if (mb_strlen($input, 'UTF-8') > $maxLength) {
            $input = mb_substr($input, 0, $maxLength, 'UTF-8');
        }

        return match ($type) {
            'email'     => filter_var($input, FILTER_VALIDATE_EMAIL) ? filter_var($input, FILTER_SANITIZE_EMAIL) : null,
            'url'       => filter_var($input, FILTER_VALIDATE_URL) ? filter_var($input, FILTER_SANITIZE_URL) : null,
            'int'       => filter_var($input, FILTER_VALIDATE_INT) !== false ? (string)(int)$input : null,
            'float'     => filter_var($input, FILTER_VALIDATE_FLOAT) !== false ? (string)(float)$input : null,
            'alphanum'  => preg_match('/^[a-zA-Z0-9]+$/', $input) ? $input : null,
            default     => htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        };
    }

    public static function sanitizeArray(array $data, array $rules = []): array {
        $clean = [];
        foreach ($data as $key => $value) {
            $key = is_string($key) ? self::sanitize($key, 'alphanum') : $key;
            if ($key === null) continue;

            if (is_array($value)) {
                $clean[$key] = self::sanitizeArray($value, $rules[$key] ?? []);
            } elseif (is_string($value)) {
                $type = $rules[$key] ?? 'text';
                $clean[$key] = self::sanitize($value, is_string($type) ? $type : 'text');
            } else {
                $clean[$key] = $value;
            }
        }
        return $clean;
    }

    // CSRF, JWT, randomString, hashPassword, verifyPassword → เหมือนเดิมของคุณ (ดีมาก!)

    public static function generateCsrfToken(int $expiry = 3600): string {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = [
            'value'  => $token,
            'expiry' => time() + $expiry
        ];
        return $token;
    }

    public static function verifyCsrfToken(string $token): bool {
        if (
            empty($_SESSION['csrf_token']['value']) ||
            empty($_SESSION['csrf_token']['expiry']) ||
            time() > $_SESSION['csrf_token']['expiry'] ||
            !hash_equals($_SESSION['csrf_token']['value'], $token)
        ) {
            unset($_SESSION['csrf_token']);
            return false;
        }
        self::generateCsrfToken();
        return true;
    }

    public static function createJwt(array $payload, ?string $secret = null, int $expiry = 3600): string {
        $secret = $secret ?? $_ENV['JWT_SECRET'] ?? '';
        if (strlen($secret) < 32) Response::error('JWT secret key ต้องยาวอย่างน้อย 32 ตัวอักษร', 500);
        if (empty($payload['sub'] ?? '')) Response::error('JWT payload ต้องมี sub (subject)', 400);

        $now = time();
        $token = ['iat' => $now, 'exp' => $now + $expiry, 'nbf' => $now] + $payload;
        return JWT::encode($token, $secret, 'HS256');
    }

    public static function verifyJwt(string $token, ?string $secret = null): ?array {
        $secret = $secret ?? $_ENV['JWT_SECRET'] ?? '';
        if (strlen($secret) < 32) return null;

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return (array) $decoded;
        } catch (\Throwable $e) {
            error_log('[JWT Error] ' . $e->getMessage());
            return null;
        }
    }

    public static function randomString(int $length = 32): string {
        return $length < 1 ? '' : bin2hex(random_bytes((int)ceil($length / 2)));
    }

    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
}
