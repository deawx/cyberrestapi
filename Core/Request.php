<?php

/**
 * Core\Request - จัดการ HTTP Request อย่างปลอดภัยและสะดวก
 * รองรับ GET, POST, JSON body, Headers, Method detection
 * Sanitize input อัตโนมัติเพื่อป้องกัน XSS และ Injection
 *
 * @version 1.0.0 (Dec 23, 2025)
 * @author (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 * @license MIT https://cyberthai.net
 */

declare(strict_types=1);

namespace Core;

use RuntimeException;

final class Request {
    private array $get;
    private array $post;
    private array $json;
    private string $method;
    private string $uri;
    private array $headers;
    private string $ip;

    /**
     * Constructor - ดึงและ sanitize ข้อมูลทั้งหมด
     */
    public function __construct() {
        $this->method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri     = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $this->headers = $this->getAllHeaders();
        $this->ip      = $this->getClientIp();

        // Sanitize GET และ POST
        $this->get  = $this->sanitize($_GET);
        $this->post = $this->sanitize($_POST);

        // Parse JSON body (สำหรับ PUT/PATCH/DELETE หรือ application/json)
        $this->json = [];
        if (
            in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE']) &&
            stripos($this->headers['Content-Type'] ?? '', 'application/json') !== false
        ) {
            $input = file_get_contents('php://input');
            if ($input !== false && $input !== '') {
                $data = json_decode($input, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->json = $this->sanitize($data);
                }
            }
        }
    }

    /**
     * ดึง instance (Singleton หรือ new ตามชอบ - ที่นี่ใช้ new ได้เลย)
     */
    public static function create(): self {
        return new self();
    }

    /**
     * ดึง method (GET, POST, PUT, PATCH, DELETE)
     */
    public function method(): string {
        return $this->method;
    }

    /**
     * ดึง URI path (เช่น /api/users/1)
     */
    public function uri(): string {
        return $this->uri;
    }

    /**
     * ดึง parameter จาก GET, POST, JSON body (ลำดับ: JSON > POST > GET)
     */
    public function input(string $key, mixed $default = null): mixed {
        return $this->json[$key] ?? $this->post[$key] ?? $this->get[$key] ?? $default;
    }

    /**
     * ดึงทุก input (รวม JSON + POST + GET)
     */
    public function all(): array {
        return array_merge($this->get, $this->post, $this->json);
    }

    /**
     * ดึงเฉพาะ keys ที่ต้องการ
     */
    public function only(array $keys): array {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    /**
     * เช็คว่ามี key หรือไม่
     */
    public function has(string $key): bool {
        return array_key_exists($key, $this->all());
    }

    /**
     * Validate required fields
     */
    public function validate(array $rules): array {
        $errors = [];
        foreach ($rules as $field => $ruleSet) {
            $value = $this->input($field);
            if (in_array('required', $ruleSet) && (empty($value) && $value !== '0' && $value !== 0)) {
                $errors[$field] = "The {$field} field is required.";
                continue;
            }

            if ($value !== null && in_array('integer', $ruleSet) && !filter_var($value, FILTER_VALIDATE_INT)) {
                $errors[$field] = "The {$field} must be an integer.";
            }

            if ($value !== null && in_array('numeric', $ruleSet) && !is_numeric($value)) {
                $errors[$field] = "The {$field} must be numeric.";
            }

            if ($value !== null && in_array('email', $ruleSet) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "The {$field} must be a valid email.";
            }
        }

        if (!empty($errors)) {
            throw new RuntimeException(json_encode(['errors' => $errors]), 422);
        }

        return $this->only(array_keys($rules));
    }

    /**
     * ดึง header
     */
    public function header(string $key, string $default = ''): string {
        return $this->headers[$key] ?? $default;
    }

    /**
     * ดึง Authorization header (Bearer token)
     */
    public function bearerToken(): ?string {
        $header = $this->header('Authorization', '');
        if (preg_match('/Bearer\s+(\S+)/', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * ดึง IP จริง (รองรับ proxy)
     */
    public function ip(): string {
        return $this->ip;
    }

    /**
     * Sanitize input (recursive)
     */
    private function sanitize(mixed $data): mixed {
        if (is_array($data)) {
            return array_map([$this, 'sanitize'], $data);
        }
        if (is_string($data)) {
            return trim(htmlspecialchars($data, ENT_QUOTES, 'UTF-8'));
        }
        return $data;
    }

    /**
     * ดึง headers ทั้งหมด
     */
    private function getAllHeaders(): array {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$key] = $value;
            } elseif (in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $name))));
                $headers[$key] = $value;
            }
        }
        return $headers;
    }

    /**
     * ดึง IP จริง (ป้องกัน spoof)
     */
    private function getClientIp(): string {
        $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
