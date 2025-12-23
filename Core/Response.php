<?php

/**
 * Core\Response - จัดการ HTTP Response เป็น JSON มาตรฐาน
 * รองรับ success, error, pagination, CORS, status code
 * แก้ปัญหา "Not all paths return a value" สำหรับ :never (PHP 8.2+ strict)
 *
 * @version 1.0.1 (Dec 23, 2025) - Fix :never type issue
 * @author (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 * @license MIT https://cyberthai.net
 */

declare(strict_types=1);

namespace Core;

use Throwable;

final class Response {
    private static bool $sent = false;

    /**
     * ส่ง JSON response มาตรฐาน (success)
     */
    public static function json(
        mixed $data = [],
        int $status = 200,
        string $message = 'Success',
        array $meta = []
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
    public static function error(string $message, int $status = 400, array $errors = []): never {
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
        if (($_ENV['APP_ENV'] ?? 'prod') === 'dev' && $status >= 500) {
            $response['trace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        }

        self::send($response, $status);
    }

    /**
     * Success with pagination
     */
    public static function paginate(array $data, int $total, int $page, int $perPage): never {
        $meta = [
            'pagination' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / $perPage),
                'from'         => ($page - 1) * $perPage + 1,
                'to'           => min($page * $perPage, $total),
            ]
        ];

        self::json($data, 200, 'Success', $meta);
    }

    /**
     * Handle exception (global error handler)
     */
    public static function handleException(Throwable $e): never {
        if (self::$sent) {
            exit;
        }
        self::$sent = true;

        $status = ($e->getCode() >= 100 && $e->getCode() < 600) ? $e->getCode() : 500;

        if (($_ENV['APP_ENV'] ?? 'prod') === 'dev') {
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
    private static function send(array $response, int $status): never {
        // Headers พื้นฐาน
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');

        // CORS (จะย้ายไป Cors.php ในอนาคต)
        if (!empty($_ENV['APP_URL'])) {
            header('Access-Control-Allow-Origin: ' . $_ENV['APP_URL']);
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        }

        // JSON options
        $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
        if (($_ENV['APP_ENV'] ?? 'prod') === 'dev') {
            $options |= JSON_PRETTY_PRINT;
        }

        echo json_encode($response, $options);

        // Log ถ้าเป็น error หนัก
        if ($status >= 500) {
            $logDir = __DIR__ . '/../storage/logs/';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            $logMessage = '[' . date('Y-m-d H:i:s') . '] ERROR ' . $status . ': ' . ($response['message'] ?? 'Unknown') . PHP_EOL;
            error_log($logMessage, 3, $logDir . 'app_' . date('Y-m-d') . '.log');
        }

        // หยุด execution เสมอ - สำคัญมากสำหรับ :never
        exit;
    }
}
