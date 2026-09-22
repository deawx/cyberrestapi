<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Cookie
 *      ตั้ง / ลบ cookie แบบกลาง พร้อม HttpOnly Secure SameSite
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

final class Cookie
{
    /**
     * ค่าเริ่มต้นของ cookie จาก .env
     *
     * @return array{
     *     expires: int,
     *     path: string,
     *     domain: string,
     *     secure: bool,
     *     httponly: bool,
     *     samesite: string
     * }
     */
    public static function defaults(): array
    {
        return [
            'expires'  => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => self::secureDefault(),
            'httponly' => true,
            'samesite' => self::sameSiteDefault(),
        ];
    }

    /**
     * Secure flag — ค่าเริ่มต้นตาม APP_ENV=prod (override ได้ด้วย COOKIE_SECURE ถ้าจำเป็น)
     */
    public static function secureDefault(): bool
    {
        return self::envFlag('COOKIE_SECURE', ($_ENV['APP_ENV'] ?? 'prod') === 'prod');
    }

    /**
     * SameSite — ค่าเริ่มต้น Strict (override ได้ด้วย COOKIE_SAMESITE ถ้าจำเป็น)
     */
    public static function sameSiteDefault(): string
    {
        $raw = trim((string) ($_ENV['COOKIE_SAMESITE'] ?? 'Strict'));
        $normalized = match (strtolower($raw)) {
            'lax' => 'Lax',
            'none' => 'None',
            default => 'Strict',
        };

        return $normalized;
    }

    /**
     * พารามิเตอร์ cookie ของ PHP session (ใช้กับ session_set_cookie_params)
     *
     * @return array{
     *     lifetime: int,
     *     path: string,
     *     domain: string,
     *     secure: bool,
     *     httponly: bool,
     *     samesite: string
     * }
     */
    public static function sessionParams(int $lifetime = 0): array
    {
        $defaults = self::defaults();

        return [
            'lifetime' => max(0, $lifetime),
            'path'     => $defaults['path'],
            'domain'   => $defaults['domain'],
            'secure'   => $defaults['secure'],
            'httponly' => true,
            'samesite' => $defaults['samesite'],
        ];
    }

    /**
     * ตั้ง cookie (ค่าเริ่มต้น HttpOnly + Secure ตาม env + SameSite)
     *
     * @param array{
     *     expires?: int,
     *     path?: string,
     *     domain?: string,
     *     secure?: bool,
     *     httponly?: bool,
     *     samesite?: string
     * } $options
     */
    public static function set(string $name, string $value, array $options = []): bool
    {
        $name = trim($name);
        if ($name === '' || preg_match('/[=,; \t\r\n\013\014]/', $name) === 1) {
            return false;
        }

        $opts = array_merge(self::defaults(), $options);
        $opts['samesite'] = self::normalizeSameSite((string) $opts['samesite']);
        $opts['secure'] = (bool) $opts['secure'];
        $opts['httponly'] = (bool) $opts['httponly'];
        $opts['expires'] = (int) $opts['expires'];
        $opts['path'] = (string) $opts['path'];
        $opts['domain'] = (string) $opts['domain'];

        // SameSite=None ต้องใช้ Secure เสมอ
        if ($opts['samesite'] === 'None') {
            $opts['secure'] = true;
        }

        return setcookie($name, $value, [
            'expires'  => $opts['expires'],
            'path'     => $opts['path'],
            'domain'   => $opts['domain'],
            'secure'   => $opts['secure'],
            'httponly' => $opts['httponly'],
            'samesite' => $opts['samesite'],
        ]);
    }

    /**
     * ลบ cookie (คง path/domain/flags ให้เบราว์เซอร์ลบตัวเดิมได้)
     *
     * @param array{
     *     path?: string,
     *     domain?: string,
     *     secure?: bool,
     *     httponly?: bool,
     *     samesite?: string
     * } $options
     */
    public static function forget(string $name, array $options = []): bool
    {
        return self::set($name, '', array_merge($options, [
            'expires' => time() - 3600,
        ]));
    }

    private static function normalizeSameSite(string $value): string
    {
        return match (strtolower(trim($value))) {
            'lax' => 'Lax',
            'none' => 'None',
            default => 'Strict',
        };
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
}
