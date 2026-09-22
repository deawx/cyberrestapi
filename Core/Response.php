<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Response
 *      ส่ง JSON มาตรฐาน success / error / paginate
 *      หยุดด้วย :never ตาม PHP 8.2+
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

use Throwable;

final class Response
{
    private static bool $sent = false;

    /**
     * ส่ง JSON response มาตรฐาน (success)
     */
    public static function json(
        mixed $data = [],
        int $status = 200,
        string $message = 'Success',
        array $meta = [],
    ): never {
        if (self::$sent) {
            exit;  // ป้องกันส่งซ้ำ
        }
        self::$sent = true;

        http_response_code($status);

        $response = [
            'status'    => $status < 400 ? 'success' : 'error',
            'message'   => $message,
            'data'      => $data,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        self::send($response, $status);
    }

    /**
     * Error response
     */
    public static function error(string $message, int $status = 400, array $errors = []): never
    {
        if (self::$sent) {
            exit;
        }
        self::$sent = true;

        $response = [
            'status'    => 'error',
            'message'   => $message,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        // Dev mode: เพิ่ม trace ถ้าเป็น server error
        if (self::debugEnabled() && $status >= 500) {
            $response['trace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        }

        self::send($response, $status);
    }

    /**
     * Success with pagination
     */
    public static function paginate(array $data, int $total, int $page, int $perPage): never
    {
        $meta = [
            'pagination' => Paginator::meta($total, $page, $perPage),
        ];

        self::json($data, 200, 'Success', $meta);
    }

    /**
     * Handle exception (global error handler)
     */
    public static function handleException(Throwable $e): never
    {
        if (self::$sent) {
            exit;
        }
        self::$sent = true;

        $status = ($e->getCode() >= 100 && $e->getCode() < 600) ? $e->getCode() : 500;

        if (self::debugEnabled()) {
            self::send([
                'status'    => 'error',
                'message'   => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $e->getTrace(),
                'timestamp' => date('Y-m-d H:i:s'),
            ], $status);
        } else {
            self::send([
                'status'    => 'error',
                'message'   => 'Internal Server Error',
                'timestamp' => date('Y-m-d H:i:s'),
            ], $status);
        }
    }

    /**
     * ส่ง JSON และหยุด execution เสมอ (method กลางเพื่อแก้ :never issue)
     */
    private static function send(array $response, int $status): never
    {
        http_response_code($status);

        // Headers พื้นฐาน
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');

        // JSON options
        $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
        if (self::debugEnabled()) {
            $options |= JSON_PRETTY_PRINT;
        }

        echo json_encode($response, $options);

        // Log ถ้าเป็น error หนัก
        if ($status >= 500) {
            Log::write('ERROR ' . $status . ': ' . ($response['message'] ?? 'Unknown'));
        }

        // หยุด execution เสมอ - สำคัญมากสำหรับ :never
        exit;
    }

    private static function debugEnabled(): bool
    {
        if (array_key_exists('APP_DEBUG', $_ENV) && $_ENV['APP_DEBUG'] !== '') {
            return filter_var($_ENV['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN);
        }

        return ($_ENV['APP_ENV'] ?? 'prod') === 'dev';
    }
}
