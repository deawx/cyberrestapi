<?php

/**
 * Core\Controller - คลาสพื้นฐานสำหรับทุก Controller ใน REST API
 * ให้การเข้าถึง Request และ helper methods ที่ปลอดภัย
 * ไม่รวม authentication logic (ย้ายไป Middleware แทน)
 *
 * @version 1.0.1 (December 23, 2025) - ลบ authUser/requireAuth (Pure API Core)
 * @author (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 * @license MIT https://cyberthai.net
 */

declare(strict_types=1);

namespace Core;

use Core\Request;
use Core\Response;
use Core\Session;
use Core\Security;

abstract class Controller {
    protected Request $request;

    public function __construct() {
        // เริ่ม session ถ้าต้องการใช้ (เช่น CSRF หรือ flash)
        Session::start();

        // สร้าง request
        $this->request = Request::create();
    }

    /**
     * ส่ง JSON response
     */
    protected function json(mixed $data, int $status = 200, string $message = 'Success'): never {
        Response::json($data, $status, $message);
    }

    /**
     * ส่ง error response
     */
    protected function error(string $message, int $status = 400, array $errors = []): never {
        Response::error($message, $status, $errors);
    }

    /**
     * เปลี่ยนเส้นทาง (redirect)
     */
    protected function redirect(string $url, int $status = 302): never {
        $url = Security::sanitize($url, 'url');
        if ($url === null) {
            $this->error('URL สำหรับ redirect ไม่ถูกต้อง', 400);
        }
        http_response_code($status);
        header("Location: {$url}");
        exit;
    }

    /**
     * แสดง View (เผื่อ hybrid API + Web)
     */
    protected function view(string $view, array $data = []): void {
        $view = preg_replace('/[^a-zA-Z0-9_\/-]/', '', $view);
        if (empty($view)) {
            $this->error('ชื่อ View ไม่ถูกต้อง', 500);
        }

        $data = Security::sanitizeArray($data);
        $data['csrf_token'] = Security::generateCsrfToken();

        $viewPath = __DIR__ . '/../views/' . $view . '.php';
        $fullPath = realpath($viewPath);

        if ($fullPath === false || strpos($fullPath, realpath(__DIR__ . '/../views/')) !== 0) {
            $this->error("ไม่พบไฟล์ View: {$view}", 404);
        }

        extract($data);
        require $fullPath;
    }

    /**
     * ตรวจสอบ CSRF Token
     */
    protected function validateCsrf(): bool {
        $token = $this->request->input('csrf_token');
        if (!$token || !Security::verifyCsrfToken((string)$token)) {
            $this->error('CSRF Token ไม่ถูกต้องหรือหมดอายุ', 403);
            return false;
        }
        return true;
    }

    /**
     * ตรวจสอบว่าเป็น AJAX request หรือไม่
     */
    protected function isAjax(): bool {
        return strtolower($this->request->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    /**
     * Abort request ด้วย error code
     */
    protected function abort(int $status, string $message = ''): never {
        $defaultMessages = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            419 => 'CSRF Token Mismatch',
            422 => 'Validation Error',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
        ];

        Response::error($message ?: $defaultMessages[$status] ?? 'Error', $status);
    }
}
