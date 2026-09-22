<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Security
 *      หัวความปลอดภัย CSRF sanitize และรหัสผ่าน
 *      ตามแนวทาง OWASP Top 10
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

final class Security
{
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
     *
     * CSP / HSTS ทำงานอัตโนมัติจาก APP_ENV — ไม่ต้องตั้งใน .env
     * (override ขั้นสูง: CSP_ENABLED / HSTS_ENABLED / CSP_REPORT_ONLY)
     */
    public static function setSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        if (self::cspEnabled()) {
            $header = self::cspReportOnly()
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';
            header($header . ': ' . self::buildCSP(), true);
            header('X-XSS-Protection: 0');
            header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        }

        if (self::hstsEnabled()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }

    /**
     * CSP เปิดอัตโนมัติเมื่อ APP_ENV ไม่ใช่ dev (override: CSP_ENABLED)
     */
    public static function cspEnabled(): bool
    {
        return self::envFlag('CSP_ENABLED', ($_ENV['APP_ENV'] ?? 'prod') !== 'dev');
    }

    /**
     * HSTS เปิดอัตโนมัติเมื่อ APP_ENV=prod (override: HSTS_ENABLED)
     */
    public static function hstsEnabled(): bool
    {
        return self::envFlag('HSTS_ENABLED', ($_ENV['APP_ENV'] ?? 'prod') === 'prod');
    }

    /**
     * ส่ง CSP แบบ report-only เมื่อตั้ง CSP_REPORT_ONLY=true (ค่าเริ่มต้นปิด)
     */
    public static function cspReportOnly(): bool
    {
        return self::envFlag('CSP_REPORT_ONLY', false);
    }

    /**
     * ปรับ / เพิ่ม CSP directive ก่อนเรียก setSecurityHeaders (เช่น ใน bootstrap แอป)
     */
    public static function setCspDirective(string $directive, string $value): void
    {
        $directive = preg_replace('/[^a-zA-Z0-9-]/', '', $directive) ?? '';
        $value = preg_replace('/[\r\n\t;]/', '', $value) ?? '';
        if ($directive === '' || $value === '') {
            return;
        }
        self::$cspDirectives[$directive] = $value;
    }

    /**
     * @return array<string, string>
     */
    public static function cspDirectives(): array
    {
        return self::$cspDirectives;
    }

    private static function buildCSP(): string
    {
        $parts = [];
        foreach (self::$cspDirectives as $directive => $value) {
            $directive = preg_replace('/[^a-zA-Z0-9-]/', '', $directive) ?? '';
            $value = preg_replace('/[\r\n\t;]/', '', $value) ?? '';
            if ($directive && $value) {
                $parts[] = $directive . ' ' . $value;
            }
        }
        return implode('; ', $parts);
    }

    private static function envFlag(string $key, bool $whenAuto): bool
    {
        $raw = strtolower(trim((string) ($_ENV[$key] ?? 'auto')));

        return match ($raw) {
            '1', 'true', 'on', 'yes' => true,
            '0', 'false', 'off', 'no' => false,
            default => $whenAuto,
        };
    }

    // ลบ secureSession() ออกทั้งหมด
    // ใช้ Session::start($timeout) แทนใน index.php

    /**
     * Sanitize input string
     */
    public static function sanitize(?string $input, string $type = 'text', int $maxLength = 1000): ?string
    {
        if ($input === null || $input === '') {
            return null;
        }

        $input = trim($input);
        if (mb_strlen($input, 'UTF-8') > $maxLength) {
            $input = mb_substr($input, 0, $maxLength, 'UTF-8');
        }

        return match ($type) {
            'email'     => filter_var($input, FILTER_VALIDATE_EMAIL) ?: null,
            'url'       => filter_var($input, FILTER_VALIDATE_URL) ?: null,
            'int'       => filter_var($input, FILTER_VALIDATE_INT) !== false ? (string) (int) $input : null,
            'float'     => filter_var($input, FILTER_VALIDATE_FLOAT) !== false ? (string) (float) $input : null,
            'alphanum'  => preg_match('/^[a-zA-Z0-9]+$/', $input) ? $input : null,
            default     => htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        };
    }

    public static function sanitizeArray(array $data, array $rules = []): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            $key = is_string($key) ? self::sanitize($key, 'alphanum') : $key;
            if ($key === null) {
                continue;
            }

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

    // CSRF, randomString, hashPassword, verifyPassword

    public static function generateCsrfToken(int $expiry = 3600): string
    {
        Session::start();
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = [
            'value'  => $token,
            'expiry' => time() + $expiry,
        ];
        return $token;
    }

    public static function verifyCsrfToken(string $token): bool
    {
        Session::start();
        if (
            empty($_SESSION['csrf_token']['value'])
            || empty($_SESSION['csrf_token']['expiry'])
            || time() > $_SESSION['csrf_token']['expiry']
            || !hash_equals($_SESSION['csrf_token']['value'], $token)
        ) {
            unset($_SESSION['csrf_token']);
            return false;
        }
        self::generateCsrfToken();
        return true;
    }

    public static function randomString(int $length = 32): string
    {
        return $length < 1 ? '' : bin2hex(random_bytes((int) ceil($length / 2)));
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
