<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\EnvWriter
 *      อ่านและเขียนคีย์ในไฟล์ .env
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

final class EnvWriter
{
    public static function read(string $path, string $key): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            return null;
        }

        if (preg_match('/^' . preg_quote($key, '/') . '=(.*)$/m', $contents, $matches) !== 1) {
            return null;
        }

        $value = trim($matches[1]);
        if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
            $quote = $value[0];
            $end = strpos($value, $quote, 1);
            if ($end !== false) {
                return substr($value, 1, $end - 1);
            }
        }

        $hash = strpos($value, ' #');
        if ($hash !== false) {
            $value = trim(substr($value, 0, $hash));
        }

        return $value;
    }

    public static function isFilled(string $path, string $key): bool
    {
        $value = self::read($path, $key);

        return $value !== null && $value !== '';
    }

    public static function set(string $path, string $key, string $value): void
    {
        if (!is_file($path) || !is_writable($path)) {
            throw new \RuntimeException('เขียนไฟล์ .env ไม่ได้: ' . $path);
        }

        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            throw new \RuntimeException('อ่านไฟล์ .env ไม่ได้: ' . $path);
        }

        $line = $key . '=' . $value;
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
        if (preg_match($pattern, $contents) === 1) {
            $updated = preg_replace($pattern, $line, $contents, 1);
            if (!is_string($updated)) {
                throw new \RuntimeException('แก้คีย์ใน .env ไม่สำเร็จ: ' . $key);
            }
            $contents = $updated;
        } else {
            $contents = rtrim($contents) . PHP_EOL . $line . PHP_EOL;
        }

        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException('บันทึกไฟล์ .env ไม่สำเร็จ');
        }
    }
}
