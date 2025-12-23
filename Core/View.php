<?php

/**
 * Core\View - View Engine สำหรับแสดงผลไฟล์ .php template อย่างปลอดภัย
 * รองรับ dot notation (admin.dashboard → admin/dashboard.php)
 * ป้องกัน path traversal, XSS, auto CSRF token, optional JS data embed
 *
 * @version 1.0.0 (December 23, 2025)
 * @author (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 * @license MIT https://cyberthai.net
 */

declare(strict_types=1);

namespace Core;

use Core\Response;
use Core\Security;

final class View {
    /** พาธฐานของ views จาก root โปรเจค */
    private static string $basePath = '';

    /**
     * เริ่มต้นพาธ views (เรียกครั้งเดียว)
     */
    private static function init(): void {
        if (self::$basePath !== '') {
            return;
        }

        // ดึงจาก .env หรือ default 'views/'
        $envPath = rtrim($_ENV['VIEW_PATH'] ?? 'views', '/\\') . '/';

        // คำนวณจาก root โปรเจค (Core/ อยู่ลึก 1 ชั้น)
        $rootDir = dirname(__DIR__); // __DIR__ = Core/, ขึ้นไป = root
        $fullPath = $rootDir . '/' . $envPath;

        $realPath = realpath($fullPath);

        self::$basePath = $realPath ? rtrim($realPath, '/\\') . '/' : $rootDir . '/Views/';
    }

    /**
     * แสดงผล View
     *
     * @param string $view ชื่อ view (เช่น 'home', 'admin.dashboard')
     * @param array $data ข้อมูลส่งไป view
     * @param bool $withJs ฝังข้อมูลเป็น JavaScript variable หรือไม่
     * @return never
     */
    public static function render(string $view, array $data = [], bool $withJs = false): never {
        self::init();

        try {
            // แปลง dot notation → directory structure
            $view = str_replace('.', '/', $view);

            // กรองตัวอักษรที่ไม่ปลอดภัย
            $view = preg_replace('/[^a-zA-Z0-9_\/-]/', '', $view);

            if ($view === '' || str_contains($view, '..')) {
                throw new \RuntimeException('ชื่อ View ไม่ถูกต้องหรือมี path traversal');
            }

            $viewFile = self::$basePath . $view . '.php';
            $realPath = realpath($viewFile);

            // ป้องกัน path traversal อย่างเข้มงวด
            if (
                $realPath === false ||
                strpos($realPath, self::$basePath) !== 0 ||
                !is_file($realPath)
            ) {
                throw new \RuntimeException("ไม่พบไฟล์ View: {$view}");
            }

            // Sanitize ข้อมูลทั้งหมด
            $safeData = Security::sanitizeArray($data);

            // เพิ่ม CSRF token อัตโนมัติ
            $safeData['csrf_token'] = Security::generateCsrfToken();

            // Buffer output
            ob_start();

            // Extract ตัวแปรไปใน scope
            extract($safeData, EXTR_SKIP);

            // Include view file
            include $realPath;

            $content = ob_get_clean() ?: '';

            // ถ้าต้องการฝัง data เป็น JS (สำหรับ SPA)
            // if ($withJs) {
            //     $json = json_encode($safeData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            //     $script = "<script>window.appData = {$json};</script>\n";

            //     // ฝังใน <head> หรือ <body> แรกที่เจอ
            //     if (preg_match('/<head\b/i', $content)) {
            //         $content = preg_replace('/<head\b/i', '<head>' . $script, $content, 1);
            //     } elseif (preg_match('/<body\b/i', $content)) {
            //         $content = preg_replace('/<body\b/i', '<body>' . $script, $content, 1);
            //     } else {
            //         $content = $script . $content;
            //     }
            // }
            if ($withJs) {
                $json = json_encode($safeData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $script = "<script>window.appData = {$json};</script>\n";

                // แทรก script ไว้ข้างใน <head> หรือ <body> (ไม่ตัด attribute)
                if (preg_match('/<head\b[^>]*>/i', $content)) {
                    $content = preg_replace('/(<head\b[^>]*>)/i', '$1' . $script, $content, 1);
                } elseif (preg_match('/<body\b[^>]*>/i', $content)) {
                    $content = preg_replace('/(<body\b[^>]*>)/i', '$1' . $script, $content, 1);
                } else {
                    // ถ้าไม่มี head/body → ใส่ด้านบนสุด
                    $content = $script . $content;
                }
            }
            // if ($withJs) {
            //     $json = json_encode($safeData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
            //     $script = "<script>window.CyberApp = JSON.parse('{$json}');</script>\n";
            //     $content = preg_replace('/<head>/i', '<head>' . $script, $content, 1)
            //         ?: preg_replace('/<body>/i', '<body>' . $script, $content, 1)
            //         ?: $script . $content;
            // }

            // ส่ง content ออกไป
            echo $content;
            exit;
        } catch (\Throwable $e) {
            ob_end_clean(); // ล้าง buffer

            if ($_ENV['APP_ENV'] ?? 'prod' === 'dev') {
                http_response_code(500);
                echo '<pre style="background:#000;color:#0f0;padding:20px;font-family:monospace;">';
                echo "View Error: " . htmlspecialchars($e->getMessage()) . "\n";
                echo "File: " . ($viewFile ?? 'N/A') . "\n";
                echo "Trace:\n" . htmlspecialchars($e->getTraceAsString());
                echo '</pre>';
            } else {
                Response::error('ไม่สามารถแสดงหน้าได้', 500);
            }
            exit;
        }
    }

    /**
     * Helper: แสดง view และหยุด execution
     * ใช้ใน Controller ได้สะดวก
     */
    public static function make(string $view, array $data = [], bool $withJs = false): never {
        self::render($view, $data, $withJs);
    }
}
