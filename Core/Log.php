<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Log
 *      เขียนล็อกรายวันและกวาดไฟล์ที่หมดอายุ
 *      ช่องทาง app / error เก็บที่ Storage/logs
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

final class Log
{
    public static function directory(): string
    {
        return dirname(__DIR__) . '/Storage/logs';
    }

    public static function write(string $message, string $channel = 'app'): void
    {
        $dir = self::directory();
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }

        $channel = $channel === 'error' ? 'error' : 'app';
        $file = $dir . '/' . $channel . '_' . date('Y-m-d') . '.log';
        error_log('[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, 3, $file);
    }

    public static function maybePrune(): void
    {
        if (random_int(1, 10) !== 1) {
            return;
        }

        self::pruneExpired();
    }

    public static function pruneExpired(?string $dir = null, ?int $retentionDays = null, ?int $now = null): int
    {
        $dir ??= self::directory();
        if (!is_dir($dir)) {
            return 0;
        }

        $days = max(1, $retentionDays ?? (int) ($_ENV['LOG_RETENTION_DAYS'] ?? 14));
        $now ??= time();
        $cutoff = strtotime(date('Y-m-d', $now) . ' 00:00:00') - ($days * 86400);
        if ($cutoff === false) {
            return 0;
        }

        $removed = 0;

        foreach (glob($dir . '/*.log') ?: [] as $path) {
            if (!self::isExpired((string) $path, $cutoff, $now, $days)) {
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

    private static function isExpired(string $path, int $cutoff, int $now, int $days): bool
    {
        if (!is_file($path)) {
            return false;
        }

        $name = basename($path);
        if (preg_match('/^(?:app|error)_(\d{4}-\d{2}-\d{2})\.log$/', $name, $matches) === 1) {
            $fileDay = strtotime($matches[1] . ' 00:00:00');

            return $fileDay !== false && $fileDay <= $cutoff;
        }

        $mtime = filemtime($path);

        return $mtime !== false && $mtime < $now - ($days * 86400);
    }
}
