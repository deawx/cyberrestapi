<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Entry point ของ CyberApp API Core Framework
 *      Flat structure - index.php อยู่ที่ root
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

// 1. Require Composer autoload (สำคัญที่สุด - ต้องมาก่อนทุกอย่าง)
require_once __DIR__ . '/vendor/autoload.php';

// 2. Error & Exception Handling
if (($_ENV['APP_ENV'] ?? 'prod') === 'dev') {
    // Development: Whoops แสดง error สวย ๆ
    $whoops = new \Whoops\Run();
    $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler());
    $whoops->allowQuit(false);
    $whoops->writeToOutput(false);
    $whoops->register();
} else {
    // Production: แสดงหน้า error แบบ custom
    set_error_handler(function ($severity, $message, $file, $line) {
        error_log("[ERROR] {$message} in {$file} on line {$line}");
        http_response_code(500);
        if (file_exists(__DIR__ . '/Views/errors/500.php')) {
            require __DIR__ . '/Views/errors/500.php';
        } else {
            echo '<h1>500 Internal Server Error</h1><p>ขออภัย เกิดข้อผิดพลาดในระบบ</p>';
        }
        exit;
    });

    set_exception_handler(function ($exception) {
        error_log("[EXCEPTION] " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
        http_response_code(500);
        if (file_exists(__DIR__ . '/Views/errors/500.php')) {
            require __DIR__ . '/Views/errors/500.php';
        } else {
            echo '<h1>500 Internal Server Error</h1><p>ขออภัย เกิดข้อผิดพลาดในระบบ</p>';
        }
        exit;
    });
}

// 3. รัน Application
$app = new Core\Application();
$app->run();
