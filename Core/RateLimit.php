<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\RateLimit
 *      จำกัดจำนวนคำขอต่อ IP
 *      เก็บไฟล์ที่ Storage/cache/rate และกวาดของหมดอายุ
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

final class RateLimit
{
    public static function enforce(): void
    {
        $max = (int) ($_ENV['RATE_LIMIT'] ?? 0);
        $window = max(10, (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 60));
        $dir = dirname(__DIR__) . '/Storage/cache/rate';

        if ($max > 0) {
            self::hit($dir, $window, $max);
        }

        if (random_int(1, 10) === 1) {
            self::pruneExpired($dir, $window);
        }
    }

    public static function pruneExpired(string $dir, int $window, ?int $now = null): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $cutoff = ($now ?? time()) - $window;
        $removed = 0;

        foreach (glob($dir . '/*.json') ?: [] as $path) {
            if (!is_file($path)) {
                continue;
            }

            $mtime = filemtime($path);
            if ($mtime === false || $mtime > $cutoff) {
                continue;
            }

            if (unlink($path)) {
                $removed++;
            }

            if ($removed >= 250) {
                break;
            }
        }

        return $removed;
    }

    private static function hit(string $dir, int $window, int $max): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $file = $dir . '/' . hash('sha256', $ip) . '.json';
        $now = time();
        $hits = [];

        $handle = fopen($file, 'c+');
        if ($handle === false) {
            return;
        }

        try {
            flock($handle, LOCK_EX);
            $raw = stream_get_contents($handle);
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $hits = $decoded;
                }
            }

            $hits = array_values(array_filter(
                $hits,
                static fn($timestamp): bool => is_int($timestamp) && $timestamp > $now - $window,
            ));
            $hits[] = $now;

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($hits, JSON_THROW_ON_ERROR));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        if (count($hits) > $max) {
            header('Retry-After: ' . $window);
            Response::error('Too Many Requests', 429);
        }
    }
}
