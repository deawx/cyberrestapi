<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Upload
 *      เก็บไฟล์อัปโหลดใต้ public/
 *      กันนามสกุลอันตราย และสุ่มชื่อไฟล์ก่อนบันทึก
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

use RuntimeException;

final class Upload
{
    private const DENIED = ['php', 'phtml', 'phar', 'cgi', 'exe', 'bat', 'cmd', 'sh', 'htaccess', 'html', 'htm', 'js', 'svg'];

    /**
     * @param list<string> $allowed
     * @return array{name: string, path: string, url: string, size: int, mime: string}
     */
    public static function store(
        string $field,
        string $directory = '',
        array $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'],
        ?int $maxBytes = null,
        ?string $root = null,
    ): array {
        $file = $_FILES[$field] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException("ไม่มีไฟล์ในฟิลด์ {$field}");
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException("อัปโหลด {$field} ไม่สำเร็จ");
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $isHttpUpload = is_uploaded_file($tmp);
        if ($tmp === '' || !is_file($tmp) || (!$isHttpUpload && PHP_SAPI !== 'cli')) {
            throw new RuntimeException("ไฟล์ {$field} ไม่ถูกต้อง");
        }

        $maxBytes ??= max(1024, ((int) ($_ENV['UPLOAD_MAX_KB'] ?? 2048)) * 1024);
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            throw new RuntimeException("ขนาดไฟล์ {$field} เกินกำหนด");
        }

        $original = basename((string) ($file['name'] ?? 'file'));
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowed = array_map(static fn(string $item): string => strtolower(trim($item)), $allowed);
        if ($ext === '' || in_array($ext, self::DENIED, true) || !in_array($ext, $allowed, true)) {
            throw new RuntimeException("นามสกุลไฟล์ {$field} ไม่ได้รับอนุญาต");
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        if ($mime === '' || str_contains($mime, 'php') || str_starts_with($mime, 'text/html')) {
            throw new RuntimeException("ชนิดไฟล์ {$field} ไม่ได้รับอนุญาต");
        }

        $folder = self::safeFolder($directory !== '' ? $directory : (string) ($_ENV['UPLOAD_DIR'] ?? 'uploads'));
        $root ??= dirname(__DIR__) . '/public';
        $dir = rtrim(str_replace('\\', '/', $root), '/') . '/' . $folder;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('สร้างโฟลเดอร์อัปโหลดไม่ได้');
        }

        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . '/' . $stored;
        $moved = $isHttpUpload ? move_uploaded_file($tmp, $dest) : copy($tmp, $dest);
        if (!$moved) {
            throw new RuntimeException("บันทึกไฟล์ {$field} ไม่สำเร็จ");
        }

        $relative = $folder . '/' . $stored;

        return [
            'name' => $original,
            'path' => $relative,
            'url' => self::url($relative),
            'size' => $size,
            'mime' => $mime,
        ];
    }

    public static function url(string $path): string
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

        return $base . '/' . $path;
    }

    private static function safeFolder(string $directory): string
    {
        $directory = trim(str_replace('\\', '/', $directory), '/');
        $parts = [];
        foreach (explode('/', $directory) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                continue;
            }
            if (preg_match('/^[A-Za-z0-9_-]+$/', $part) !== 1) {
                throw new RuntimeException('โฟลเดอร์อัปโหลดไม่ถูกต้อง');
            }
            $parts[] = $part;
        }

        return $parts === [] ? 'uploads' : implode('/', $parts);
    }
}
