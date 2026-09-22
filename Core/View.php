<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\View
 *      เรนเดอร์เทมเพลต PHP อย่างปลอดภัย
 *      รองรับ layout (header/footer), dot notation, CSRF, View::asset()
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

final class View
{
    /** พาธฐานของ views จาก root โปรเจค */
    private static string $basePath = '';

    /**
     * เริ่มต้นพาธ views (เรียกครั้งเดียว)
     */
    private static function init(): void
    {
        if (self::$basePath !== '') {
            return;
        }

        $envPath = rtrim($_ENV['VIEW_PATH'] ?? 'Views', '/\\') . '/';

        // คำนวณจาก root โปรเจค (Core/ อยู่ลึก 1 ชั้น)
        $rootDir = dirname(__DIR__); // __DIR__ = Core/, ขึ้นไป = root
        $fullPath = $rootDir . '/' . $envPath;

        $realPath = realpath($fullPath);

        self::$basePath = self::normalizeDir($realPath ?: ($rootDir . '/Views'));
    }

    /**
     * URL ของไฟล์ใน public/assets/
     */
    public static function asset(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, '..')) {
            return '';
        }

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        $base = ($dir === '' || $dir === '/' || $dir === '.') ? '' : $dir;

        if ($base === '') {
            $parsed = parse_url(rtrim((string) ($_ENV['APP_URL'] ?? ''), '/'));
            $base = isset($parsed['path']) ? rtrim((string) $parsed['path'], '/') : '';
        }

        return $base . '/assets/' . $path;
    }

    private static function normalizeDir(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/') . '/';
    }

    /**
     * แปลงชื่อวิว/เลย์เอาต์เป็นพาธไฟล์จริงใน Views/
     */
    private static function resolveFile(string $name): string
    {
        self::init();

        $name = str_replace('.', '/', $name);
        $name = preg_replace('/[^a-zA-Z0-9_\/-]/', '', $name) ?? '';

        if ($name === '' || str_contains($name, '..')) {
            throw new \RuntimeException('ชื่อ View ไม่ถูกต้องหรือมี path traversal');
        }

        $viewFile = self::$basePath . $name . '.php';
        $realPath = realpath($viewFile);
        $normalizedReal = $realPath === false ? '' : str_replace('\\', '/', $realPath);

        if (
            $realPath === false
            || !str_starts_with($normalizedReal, self::$basePath)
            || !is_file($realPath)
        ) {
            throw new \RuntimeException("ไม่พบไฟล์ View: {$name}");
        }

        return $realPath;
    }

    /**
     * เรนเดอร์ไฟล์วิวเป็นสตริง (ไม่ exit)
     *
     * @param array<string, mixed> $data
     */
    private static function renderFile(string $absolutePath, array $data): string
    {
        ob_start();
        extract($data, EXTR_SKIP);
        include $absolutePath;

        return ob_get_clean() ?: '';
    }

    /**
     * แสดงผล View
     *
     * @param string $view ชื่อ view (เช่น 'home', 'admin.dashboard') — เนื้อหา body
     * @param array<string, mixed> $data ข้อมูลส่งไป view
     * @param bool $withJs ฝังข้อมูลเป็น JavaScript variable หรือไม่
     * @param string|null $layout เลย์เอาต์หุ้ม body เช่น 'layouts.app' → Views/layouts/app.php ใช้ <?= $content ?>
     * @return never
     */
    public static function render(
        string $view,
        array $data = [],
        bool $withJs = false,
        ?string $layout = null,
    ): never {
        self::init();

        try {
            $viewPath = self::resolveFile($view);

            // Sanitize ข้อมูลทั้งหมด
            $safeData = Security::sanitizeArray($data);

            // เพิ่ม CSRF token และ helper $asset() อัตโนมัติ
            $safeData['csrf_token'] = Security::generateCsrfToken();
            $safeData['asset'] = static fn(string $path): string => self::asset($path);

            $content = self::renderFile($viewPath, $safeData);

            if ($layout !== null && $layout !== '') {
                $layoutPath = self::resolveFile($layout);
                $safeData['content'] = $content;
                $content = self::renderFile($layoutPath, $safeData);
            }

            if ($withJs) {
                $json = json_encode($safeData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $script = "<script>window.appData = {$json};</script>\n";

                if (preg_match('/<head\b[^>]*>/i', $content)) {
                    $replaced = preg_replace('/(<head\b[^>]*>)/i', '$1' . $script, $content, 1);
                    $content = is_string($replaced) ? $replaced : $content;
                } elseif (preg_match('/<body\b[^>]*>/i', $content)) {
                    $replaced = preg_replace('/(<body\b[^>]*>)/i', '$1' . $script, $content, 1);
                    $content = is_string($replaced) ? $replaced : $content;
                } else {
                    $content = $script . $content;
                }
            }

            echo $content;
            exit;
        } catch (\Throwable $e) {
            ob_end_clean();

            if (($_ENV['APP_ENV'] ?? 'prod') === 'dev') {
                http_response_code(500);
                echo '<pre style="background:#000;color:#0f0;padding:20px;font-family:monospace;">';
                echo 'View Error: ' . htmlspecialchars($e->getMessage()) . "\n";
                echo 'File: ' . ($viewPath ?? 'N/A') . "\n";
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
     *
     * @param array<string, mixed> $data
     */
    public static function make(
        string $view,
        array $data = [],
        bool $withJs = false,
        ?string $layout = null,
    ): never {
        self::render($view, $data, $withJs, $layout);
    }
}
