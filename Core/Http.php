<?php

/**
 * Core\Http - HTTP Client ด้วย cURL สำหรับส่ง request ไปยัง API อื่น
 * รองรับ GET, POST, PUT, PATCH, DELETE, JSON body, custom headers, bearer token
 * ตามมาตรฐาน Modern PHP HTTP Client ปี 2026 (lightweight & secure)
 *
 * @version 1.0.0 (December 23, 2025)
 * @author (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 * @license MIT https://cyberthai.net
 */

declare(strict_types=1);

namespace Core;

final class Http {
    /** ค่าเริ่มต้นของ cURL options */
    private static array $defaultOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_USERAGENT      => 'CyberApp API Client/1.0 (+https://cyberthai.net)',
    ];

    /** Timeout เริ่มต้น (วินาที) */
    private static int $timeout = 30;

    /**
     * ตั้งค่า timeout ทั่วไป
     */
    public static function timeout(int $seconds): void {
        self::$timeout = max(1, $seconds);
    }

    /**
     * ส่ง GET request
     */
    public static function get(string $url, array $headers = []): array {
        return self::request('GET', $url, null, $headers);
    }

    /**
     * ส่ง POST request
     */
    public static function post(string $url, mixed $data = null, array $headers = []): array {
        return self::request('POST', $url, $data, $headers);
    }

    /**
     * ส่ง PUT request
     */
    public static function put(string $url, mixed $data = null, array $headers = []): array {
        return self::request('PUT', $url, $data, $headers);
    }

    /**
     * ส่ง PATCH request
     */
    public static function patch(string $url, mixed $data = null, array $headers = []): array {
        return self::request('PATCH', $url, $data, $headers);
    }

    /**
     * ส่ง DELETE request
     */
    public static function delete(string $url, mixed $data = null, array $headers = []): array {
        return self::request('DELETE', $url, $data, $headers);
    }

    /**
     * ส่ง request พร้อม bearer token
     */
    public static function withToken(string $token, string $method, string $url, mixed $data = null, array $headers = []): array {
        $headers['Authorization'] = 'Bearer ' . trim($token);
        return self::request(strtoupper($method), $url, $data, $headers);
    }

    /**
     * ส่ง request หลัก
     */
    private static function request(string $method, string $url, mixed $data = null, array $headers = []): array {
        if (!extension_loaded('curl')) {
            return self::error('cURL extension ไม่ได้ติดตั้งหรือเปิดใช้งาน');
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return self::error('URL ไม่ถูกต้อง: ' . $url);
        }

        $ch = curl_init($url);
        if (!$ch) {
            return self::error('ไม่สามารถเริ่มต้น cURL session ได้');
        }

        $options = self::$defaultOptions;
        $options[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
        $options[CURLOPT_TIMEOUT] = self::$timeout;

        // จัดการ headers
        $finalHeaders = ['Accept: application/json'];

        if ($data !== null) {
            if (is_array($data) || is_object($data)) {
                $finalHeaders[] = 'Content-Type: application/json';
                $postData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } else {
                $finalHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
                $postData = is_string($data) ? $data : http_build_query($data);
            }
            $options[CURLOPT_POSTFIELDS] = $postData;
        }

        // รวม headers จากผู้ใช้
        foreach ($headers as $key => $value) {
            $finalHeaders[] = $key . ': ' . $value;
        }

        $options[CURLOPT_HTTPHEADER] = $finalHeaders;

        curl_setopt_array($ch, $options);

        $rawResponse = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $info = curl_getinfo($ch);

        curl_close($ch);

        if ($rawResponse === false) {
            return self::error("cURL Error ({$errno}): {$error}", $errno);
        }

        $headerSize = $info['header_size'] ?? 0;
        $rawHeaders = substr($rawResponse, 0, $headerSize);
        $body = substr($rawResponse, $headerSize);

        $headers = self::parseHeaders($rawHeaders);
        $status = $info['http_code'] ?? 0;

        $json = null;
        $contentType = $headers['Content-Type'] ?? '';
        if (str_contains($contentType, 'application/json') && !empty($body)) {
            $json = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $json = null;
            }
        }

        return [
            'status'  => $status,
            'headers' => $headers,
            'body'    => $body,
            'json'    => $json,
            'info'    => $info,
        ];
    }

    /**
     * แปลง raw headers → array
     */
    private static function parseHeaders(string $rawHeaders): array {
        $headers = [];
        $lines = explode("\r\n", trim($rawHeaders));

        // ข้าม HTTP status line แรก (เช่น HTTP/1.1 200 OK)
        foreach ($lines as $line) {
            if (str_contains($line, 'HTTP/')) {
                continue;
            }
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        return $headers;
    }

    /**
     * สร้าง error response
     */
    private static function error(string $message, int $code = 0): array {
        return [
            'status'  => $code ?: 500,
            'headers' => [],
            'body'    => '',
            'json'    => null,
            'error'   => $message,
        ];
    }
}
