<?php

/**
 * Core\Application - Bootstrap เรียบง่ายสำหรับ REST API
 * ใช้งาน: $app = new Application(); $app->run();
 *
 * @version 1.0.0 (Dec 23, 2025)
 * @author (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 */

declare(strict_types=1);

namespace Core;

use Dotenv\Dotenv;
use Core\Cors;

class Application {
    /**
     * Constructor - เริ่มต้นระบบทั้งหมด
     */
    public function __construct() {
        // 1. Load .env
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->safeLoad();

        // 2. Session
        Session::start();

        // 3. Security Headers
        Security::setSecurityHeaders();

        // 4. CORS
        Cors::handle();

        // 5. โหลด routes (มีแค่ web.php)
        $this->loadRoutes();
    }

    /**
     * โหลดไฟล์ routes/web.php เท่านั้น
     */
    private function loadRoutes(): void {
        $webRoutes = __DIR__ . '/../routes/web.php';

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
    public function run(): void {
        Route::run();
    }
}
