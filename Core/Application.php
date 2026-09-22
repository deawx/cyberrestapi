<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Application
 *      Bootstrap เรียบง่ายสำหรับ REST API
 *      ใช้งาน: $app = new Application(); $app->run();
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

use Dotenv\Dotenv;

class Application
{
    /**
     * Constructor - เริ่มต้นระบบทั้งหมด
     */
    public function __construct()
    {
        // 1. Load .env
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->safeLoad();

        // 2. Security Headers
        Security::setSecurityHeaders();

        // 3. CORS จาก .env (REST API ไม่ผูก session อัตโนมัติ)
        Cors::quick($_ENV['CORS_ORIGIN'] ?? $_ENV['APP_URL'] ?? '*');
        Cors::handle();

        RateLimit::enforce();
        Log::maybePrune();

        // 4. โหลด routes
        $this->loadRoutes();
    }

    /**
     * โหลดไฟล์ routes/web.php เท่านั้น
     */
    private function loadRoutes(): void
    {
        $webRoutes = __DIR__ . '/../Routes/web.php';

        if (file_exists($webRoutes)) {
            require_once $webRoutes;
        } else {
            // ถ้าไม่มีไฟล์ routes เลย ให้ error ชัด ๆ (ดีตอน dev)
            trigger_error("Routes file not found: {$webRoutes}", E_USER_WARNING);
        }
    }

    /**
     * รัน application
     */
    public function run(): void
    {
        Route::run();
    }
}
