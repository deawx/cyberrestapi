<?php

/**
 * Core\Session - จัดการ PHP Session อย่างปลอดภัยสูงสุด
 * รองรับ prefix, timeout, flash message, regenerate, destroy
 * ตามมาตรฐาน OWASP Session Management 2025
 *
 * @version 1.0.0 (December 23, 2025)
 * @author (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 * @license MIT https://cyberthai.net
 */

declare(strict_types=1);

namespace Core;

use Core\Response;
use Core\Security;

final class Session {
    /** อายุ session เริ่มต้น (วินาที) - 30 นาที */
    private static int $timeout = 1800;

    /** Prefix สำหรับ session key จาก .env (เช่น app_) */
    private static string $prefix = '';

    /** เริ่มต้น session แล้วหรือยัง */
    private static bool $started = false;

    /**
     * เริ่ม session อย่างปลอดภัย
     * เรียกครั้งเดียวต่อ request (แนะนำเรียกใน index.php)
     */
    public static function start(int $timeout = 1800): void {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        try {
            // ดึง prefix จาก .env (SESSION_PREFIX=app_)
            self::$prefix = rtrim($_ENV['SESSION_PREFIX'] ?? 'app_', '_') . '_';

            // ตั้งค่าความปลอดภัย
            ini_set('session.name', self::$prefix . 'sid');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.cookie_secure', $_ENV['APP_ENV'] === 'prod' ? '1' : '0');
            ini_set('session.gc_maxlifetime', (string)max($timeout, 300)); // ขั้นต่ำ 5 นาที

            self::$timeout = max($timeout, 300);

            session_start();
            self::$started = true;

            // ตรวจสอบ timeout และ last activity
            $lastActivityKey = self::$prefix . 'last_activity';
            $lastActivity = $_SESSION[$lastActivityKey] ?? 0;

            if ($lastActivity && (time() - $lastActivity > self::$timeout)) {
                self::destroy();
                session_start(); // เริ่มใหม่หลัง destroy
            }

            $_SESSION[$lastActivityKey] = time();

            // ป้องกัน session fixation - regenerate ID ครั้งแรก
            $initKey = self::$prefix . 'initiated';
            if (!isset($_SESSION[$initKey])) {
                session_regenerate_id(true);
                $_SESSION[$initKey] = true;
            }
        } catch (\Throwable $e) {
            error_log('[Session Error] Start failed: ' . $e->getMessage());
            Response::error('เซสชันเริ่มต้นล้มเหลว', 500);
        }
    }

    /**
     * เก็บข้อมูลใน session
     */
    public static function set(string $key, mixed $value): void {
        if (!self::$started) {
            self::start();
        }

        $safeKey = Security::sanitize($key, 'alphanum', 100);
        if ($safeKey === null) {
            throw new \InvalidArgumentException('Session key ไม่ถูกต้อง');
        }

        $_SESSION[self::$prefix . $safeKey] = $value;
    }

    /**
     * ดึงข้อมูลจาก session
     */
    public static function get(string $key, mixed $default = null): mixed {
        if (!self::$started) {
            return $default;
        }

        $safeKey = Security::sanitize($key, 'alphanum', 100);
        if ($safeKey === null) {
            return $default;
        }

        return $_SESSION[self::$prefix . $safeKey] ?? $default;
    }

    /**
     * ตรวจสอบว่ามี key หรือไม่
     */
    public static function has(string $key): bool {
        if (!self::$started) {
            return false;
        }

        $safeKey = Security::sanitize($key, 'alphanum', 100);
        return $safeKey !== null && isset($_SESSION[self::$prefix . $safeKey]);
    }

    /**
     * ลบ key จาก session
     */
    public static function remove(string $key): void {
        if (!self::$started) {
            return;
        }

        $safeKey = Security::sanitize($key, 'alphanum', 100);
        if ($safeKey !== null) {
            unset($_SESSION[self::$prefix . $safeKey]);
        }
    }

    /**
     * เก็บ flash message (แสดงครั้งเดียวแล้วหาย)
     */
    public static function flash(string $key, mixed $value): void {
        self::set('flash_' . $key, $value);
        self::set('flash_keys', array_merge(self::get('flash_keys', []), [$key]));
    }

    /**
     * ดึง flash message และลบออก
     */
    public static function getFlash(string $key, mixed $default = null): mixed {
        $value = self::get('flash_' . $key, $default);
        self::remove('flash_' . $key);

        // ลบ key ออกจากรายการ flash_keys
        $keys = array_filter(self::get('flash_keys', []), fn($k) => $k !== $key);
        if (empty($keys)) {
            self::remove('flash_keys');
        } else {
            self::set('flash_keys', array_values($keys));
        }

        return $value;
    }

    /**
     * ดึง flash message ทั้งหมดที่เหลือ
     */
    public static function allFlash(): array {
        $flashes = [];
        $keys = self::get('flash_keys', []);

        foreach ($keys as $key) {
            $flashes[$key] = self::getFlash($key);
        }

        return $flashes;
    }

    /**
     * สร้าง session ID ใหม่ (เช่น หลัง login)
     */
    public static function regenerate(): void {
        if (self::$started && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION[self::$prefix . 'last_activity'] = time();
        }
    }

    /**
     * ทำลาย session ทั้งหมด (logout)
     */
    public static function destroy(): void {
        if (self::$started && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 3600,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
            session_destroy();
            self::$started = false;
        }
    }

    /**
     * ดึงข้อมูล session ทั้งหมด (ระวังใช้ใน production)
     */
    public static function all(): array {
        return self::$started ? $_SESSION : [];
    }

    /**
     * ตั้งค่าอายุ session (วินาที)
     */
    public static function setTimeout(int $seconds): void {
        self::$timeout = max($seconds, 300);
        ini_set('session.gc_maxlifetime', (string)self::$timeout);
    }

    /**
     * ดึงค่าอายุ session ปัจจุบัน
     */
    public static function getTimeout(): int {
        return self::$timeout;
    }

    /**
     * ดึง user ID จาก session (ถ้ามี login system)
     */
    public static function userId(): ?int {
        return self::get('user_id');
    }

    /**
     * ตั้ง user ID (หลัง login)
     */
    public static function setUserId(int $id): void {
        self::set('user_id', $id);
        self::regenerate(); // ป้องกัน session fixation
    }
}
