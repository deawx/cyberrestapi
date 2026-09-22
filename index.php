<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Entry point ของ CyberApp API Core Framework
 *      Flat structure - index.php อยู่ที่ root
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

// 1. Require Composer autoload (สำคัญที่สุด - ต้องมาก่อนทุกอย่าง)
require_once __DIR__ . '/vendor/autoload.php';

use Core\Application;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// 2. Error & Exception Handling
if (($_ENV['APP_ENV'] ?? 'prod') === 'dev' && class_exists(\Whoops\Run::class)) {
    $handler = new \Whoops\Handler\PrettyPageHandler();
    foreach (['DB_PASSWORD', 'JWT_SECRET', 'ENCRYPTION_KEY'] as $secretKey) {
        if (method_exists($handler, 'hideSuperglobalKey')) {
            $handler->hideSuperglobalKey('_ENV', $secretKey);
            $handler->hideSuperglobalKey('_SERVER', $secretKey);
        } elseif (method_exists($handler, 'blacklist')) {
            $handler->blacklist('_ENV', $secretKey);
            $handler->blacklist('_SERVER', $secretKey);
        }
    }
    $whoops = new \Whoops\Run();
    $whoops->pushHandler($handler);
    $whoops->register();
} else {
    $failJson = static function (): never {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'message' => 'Internal Server Error',
            'timestamp' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    };

    set_error_handler(function ($severity, $message, $file, $line) use ($failJson) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        error_log("[ERROR] {$message} in {$file} on line {$line}");
        $failJson();
    });

    set_exception_handler(function ($exception) use ($failJson) {
        error_log(
            "[EXCEPTION] " . $exception->getMessage()
            . " in " . $exception->getFile()
            . " on line " . $exception->getLine(),
        );
        $failJson();
    });
}

// 3. รัน Application
$app = new Application();
$app->run();
