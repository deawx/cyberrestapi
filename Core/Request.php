<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Request
 *      อ่าน HTTP request อย่างปลอดภัย
 *      รองรับ JSON, GET, POST, header, ไฟล์, IP
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

final class Request
{
    private static ?string $rawBody = null;

    private array $get;
    private array $post;
    private array $json;
    private string $method;
    private string $uri;
    private array $headers;
    private string $ip;
    private string $rawBodyContent;

    /** @var array<string, mixed> */
    private array $attributes = [];

    /**
     * Constructor - ดึงและ sanitize ข้อมูลทั้งหมด
     */
    public function __construct()
    {
        $this->method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri           = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $this->uri     = is_string($uri) && $uri !== '' ? $uri : '/';
        $this->headers = $this->getAllHeaders();
        $this->ip      = $this->getClientIp();

        // Sanitize GET และ POST
        $get = $this->sanitize($_GET);
        $post = $this->sanitize($_POST);
        $this->get  = is_array($get) ? $get : [];
        $this->post = is_array($post) ? $post : [];

        $this->rawBodyContent = self::readRawBody();

        // Parse JSON body (สำหรับ PUT/PATCH/DELETE หรือ application/json)
        $this->json = [];
        if (
            in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'])
            && stripos($this->headers['Content-Type'] ?? '', 'application/json') !== false
        ) {
            if ($this->rawBodyContent !== '') {
                $data = json_decode($this->rawBodyContent, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $sanitized = $this->sanitize($data);
                    $this->json = is_array($sanitized) ? $sanitized : [];
                }
            }
        }
    }

    /**
     * เนื้อหาดิบของ request (ห้าม stringify JSON ใหม่ถ้าต้องตรวจลายเซ็น)
     */
    public function rawBody(): string
    {
        return $this->rawBodyContent;
    }

    /**
     * ดึง instance (Singleton หรือ new ตามชอบ - ที่นี่ใช้ new ได้เลย)
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * ดึง method (GET, POST, PUT, PATCH, DELETE)
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * ดึง URI path (เช่น /api/users/1)
     */
    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * ดึง parameter จาก GET, POST, JSON body (ลำดับ: JSON > POST > GET)
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->json[$key] ?? $this->post[$key] ?? $this->get[$key] ?? $default;
    }

    /**
     * ดึงทุก input (รวม JSON + POST + GET)
     */
    public function all(): array
    {
        return array_merge($this->get, $this->post, $this->json);
    }

    /**
     * ดึงเฉพาะ keys ที่ต้องการ
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    /**
     * เช็คว่ามี key หรือไม่
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /**
     * @param array<string, string|list<string>> $rules
     * @return array<string, mixed>
     */
    public function validate(array $rules): array
    {
        $validator = Validator::make($this->all(), $rules, $this->files());
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        return $validator->validated();
    }

    /**
     * @return array<string, mixed>
     */
    public function files(): array
    {
        return is_array($_FILES) ? $_FILES : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function file(string $key): ?array
    {
        $file = $_FILES[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    /**
     * ดึง header
     */
    public function header(string $key, string $default = ''): string
    {
        foreach ($this->headers as $name => $value) {
            if (strcasecmp((string) $name, $key) === 0) {
                return (string) $value;
            }
        }
        return $default;
    }

    /**
     * ดึง Authorization header (Bearer token)
     * อ่านจาก Core (getallheaders / $_SERVER) — ไม่พึ่งส่งต่อใน .htaccess
     */
    public function bearerToken(): ?string
    {
        $header = $this->authorizationHeader();
        if (preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * ค่า Authorization จากหลายแหล่งที่ PHP มักได้บน Apache
     */
    private function authorizationHeader(): string
    {
        $fromHeaders = $this->header('Authorization', '');
        if ($fromHeaders !== '') {
            return $fromHeaders;
        }

        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
            $value = $_SERVER[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        if (function_exists('apache_request_headers')) {
            $apache = apache_request_headers();
            if (is_array($apache)) {
                foreach ($apache as $name => $value) {
                    if (is_string($name) && strcasecmp($name, 'Authorization') === 0 && is_string($value)) {
                        return $value;
                    }
                }
            }
        }

        return '';
    }

    /**
     * ดึง IP จริง (รองรับ proxy)
     */
    public function ip(): string
    {
        return $this->ip;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : $default;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setJwt(array $payload): void
    {
        $this->attributes['jwt'] = $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function jwt(): ?array
    {
        $payload = $this->attributes['jwt'] ?? null;

        return is_array($payload) ? $payload : null;
    }

    public function userId(): ?string
    {
        $sub = $this->jwt()['sub'] ?? null;

        return $sub !== null && $sub !== '' ? (string) $sub : null;
    }

    public function role(): ?string
    {
        $role = $this->jwt()['role'] ?? null;

        return is_string($role) && $role !== '' ? $role : null;
    }

    public function hasRole(string ...$roles): bool
    {
        if ($roles === []) {
            return false;
        }

        $jwt = $this->jwt();
        if ($jwt === null) {
            return false;
        }

        $have = [];
        if (is_string($jwt['role'] ?? null) && $jwt['role'] !== '') {
            $have[] = $jwt['role'];
        }
        if (isset($jwt['roles']) && is_array($jwt['roles'])) {
            foreach ($jwt['roles'] as $role) {
                if (is_string($role) && $role !== '') {
                    $have[] = $role;
                }
            }
        }

        foreach ($roles as $want) {
            if (in_array($want, $have, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * อ่าน php://input ครั้งเดียวต่อ request (Controller สร้าง Request อีกตัวได้)
     */
    private static function readRawBody(): string
    {
        if (self::$rawBody === null) {
            self::$rawBody = file_get_contents('php://input') ?: '';
        }
        return self::$rawBody;
    }

    /**
     * Sanitize input (recursive)
     */
    private function sanitize(mixed $data): mixed
    {
        if (is_array($data)) {
            return array_map([$this, 'sanitize'], $data);
        }
        if (is_string($data)) {
            return trim($data);
        }
        return $data;
    }

    /**
     * ดึง headers ทั้งหมด
     */
    private function getAllHeaders(): array
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            $raw = getallheaders();
            if (is_array($raw)) {
                foreach ($raw as $name => $value) {
                    if (is_string($name) && (is_string($value) || is_numeric($value))) {
                        $headers[$name] = (string) $value;
                    }
                }
            }
        }

        foreach ($_SERVER as $name => $value) {
            if (!is_string($value)) {
                continue;
            }
            if (str_starts_with($name, 'HTTP_')) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$key] ??= $value;
            } elseif (in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $name))));
                $headers[$key] ??= $value;
            } elseif ($name === 'REDIRECT_HTTP_AUTHORIZATION') {
                $headers['Authorization'] ??= $value;
            }
        }

        return $headers;
    }

    /**
     * ดึง IP ของ client
     * ค่าเริ่มต้นใช้แค่ REMOTE_ADDR — header X-Forwarded-For ปลอมได้ง่าย
     * เปิด TRUST_PROXIES=true เมื่ออยู่หลัง reverse proxy ที่เชื่อถือได้เท่านั้น
     */
    private function getClientIp(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $remote = is_string($remote) && $remote !== '' ? $remote : '0.0.0.0';

        if (($_ENV['TRUST_PROXIES'] ?? '') !== 'true') {
            return $remote;
        }

        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $key) {
            if (empty($_SERVER[$key]) || !is_string($_SERVER[$key])) {
                continue;
            }
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        return $remote;
    }
}
