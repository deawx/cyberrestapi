<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Controller
 *      คลาสพื้นฐานของทุก Controller ใน REST API
 *      มี helper สำหรับ JSON, validate, paginate, view
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

use Core\Request;
use Core\Response;
use Core\Session;
use Core\Security;

abstract class Controller
{
    protected Request $request;

    public function __construct(?Request $request = null)
    {
        $this->request = $request ?? Request::create();
    }

    /**
     * @param array<string, string|list<string>> $rules
     * @return array<string, mixed>
     */
    protected function validate(array $rules): array
    {
        return $this->request->validate($rules);
    }

    /**
     * @param class-string<Model> $model
     * @param array<string, mixed> $where
     */
    protected function paginate(string $model, array $where = []): never
    {
        [$page, $perPage] = Paginator::fromRequest($this->request);
        $result = $model::paginate($page, $perPage, $where);
        Response::paginate($result['data'], $result['total'], $page, $perPage);
    }

    /**
     * ส่ง JSON response
     */
    protected function json(mixed $data, int $status = 200, string $message = 'Success'): never
    {
        Response::json($data, $status, $message);
    }

    /**
     * ส่ง error response
     */
    protected function error(string $message, int $status = 400, array $errors = []): never
    {
        Response::error($message, $status, $errors);
    }

    /**
     * เปลี่ยนเส้นทาง (redirect)
     */
    protected function redirect(string $url, int $status = 302): never
    {
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
     *
     * @param array<string, mixed> $data
     */
    protected function view(
        string $view,
        array $data = [],
        bool $withJs = false,
        ?string $layout = null,
    ): never {
        View::render($view, $data, $withJs, $layout);
    }

    /**
     * ตรวจสอบ CSRF Token
     */
    protected function validateCsrf(): bool
    {
        Session::start();
        $token = $this->request->input('csrf_token');
        if (!$token || !Security::verifyCsrfToken((string) $token)) {
            $this->error('CSRF Token ไม่ถูกต้องหรือหมดอายุ', 403);
            return false;
        }
        return true;
    }

    /**
     * ตรวจสอบว่าเป็น AJAX request หรือไม่
     */
    protected function isAjax(): bool
    {
        return strtolower($this->request->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    /**
     * Abort request ด้วย error code
     */
    protected function abort(int $status, string $message = ''): never
    {
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
