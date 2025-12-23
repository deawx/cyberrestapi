<?php

/**
 * Core\Database - จัดการการเชื่อมต่อฐานข้อมูลด้วย Medoo (Singleton + Lazy Auto-Reconnect)
 * รองรับ MySQL/MariaDB/PostgreSQL/SQLite + Redis Cache (Predis ^2.2)
 * Auto-Reconnect อัตโนมัติเมื่อ connection ตาย (Lazy: เช็คเฉพาะตอน query ล้มเหลว)
 * เข้ากันได้กับ PHP 8.2 || 8.4 (ตาม composer.json)
 *
 * @version 1.1.0 (Dec 23, 2025) - เพิ่ม Lazy Auto-Reconnect
 * @author (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 * @license MIT https://cyberthai.net
 */

declare(strict_types=1);

namespace Core;

use PDO;
use Medoo\Medoo;
use Dotenv\Dotenv;
use Predis\Client as RedisClient;
use ReflectionProperty;

/**
 * คลาสหลักสำหรับจัดการฐานข้อมูลและ Redis cache
 */
final class Database {
    private static ?self $instance = null;
    private Medoo $connection;           // ไม่ readonly เพราะต้อง recreate เมื่อ reconnect
    private ?RedisClient $redis = null;
    private readonly bool $isDev;
    private array $dbConfig;             // เก็บ config สำหรับ recreate connection

    /**
     * Constructor ส่วนตัว - Load .env + เชื่อมต่อ DB ครั้งแรก
     */
    private function __construct() {
        // 1. Load .env
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->safeLoad();

        // 2. ตรวจสอบ env vars ที่จำเป็น
        $required = ['DB_TYPE', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'APP_ENV'];
        foreach ($required as $key) {
            if (empty($_ENV[$key] ?? '')) {
                $this->fatalError("Missing .env variable: {$key} - กรุณาเช็คไฟล์ .env หรือ .env.example");
            }
        }

        $this->isDev = ($_ENV['APP_ENV'] ?? 'prod') === 'dev';

        // 3. เก็บ config สำหรับใช้ reconnect ในอนาคต
        $this->dbConfig = [
            'type'     => $_ENV['DB_TYPE'],
            'host'     => $_ENV['DB_HOST'],
            'port'     => (int)($_ENV['DB_PORT'] ?? 3306),
            'database' => $_ENV['DB_NAME'],
            'username' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASSWORD'],
            'charset'  => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            'prefix'   => $_ENV['DB_PREFIX'] ?? '',
            'option'   => [
                PDO::ATTR_CASE               => PDO::CASE_NATURAL,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => true,  // พยายาม reuse connection
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
            ],
            'error'    => $this->isDev ? PDO::ERRMODE_WARNING : PDO::ERRMODE_SILENT,
            'command'  => [
                'SET SQL_MODE=ANSI_QUOTES,ONLY_FULL_GROUP_BY,ERROR_FOR_DIVISION_BY_ZERO',
                'SET time_zone = \'+07:00\'',
            ],
            'timeout'  => 10,
        ];

        // 4. สร้าง connection ครั้งแรก
        $this->recreateConnection();

        // 5. Log success
        $this->log('Database connected successfully: ' . $_ENV['DB_HOST'] . '/' . $_ENV['DB_NAME']);
    }

    /**
     * สร้างหรือ recreate Medoo connection ใหม่
     */
    private function recreateConnection(): void {
        try {
            $this->connection = new Medoo($this->dbConfig);

            // Test connection ด้วย query เบา ๆ
            $this->connection->query('SELECT 1')->fetch();
        } catch (\Exception $e) {
            $this->fatalError('Initial database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * ดึง Singleton instance
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * คืนค่า Medoo connection พร้อม Lazy Auto-Reconnect
     * ถ้า connection ตาย → recreate อัตโนมัติก่อน return
     */
    public function getConnection(): Medoo {
        try {
            // ทดสอบ connection ด้วย query เบา ๆ (เร็วมาก)
            $this->connection->query('SELECT 1')->fetch();
        } catch (\Exception $e) {
            // Connection ตายหรือมีปัญหา → reconnect
            $this->log('Database connection lost! Reconnecting... (' . $e->getMessage() . ')');
            $this->recreateConnection();

            if ($this->isDev) {
                $this->log('Database reconnected successfully in development mode.');
            }
        }

        return $this->connection;
    }

    /**
     * เช็คสถานะ connection (สำหรับ health check หรือ debug)
     */
    public function isConnected(): bool {
        try {
            $this->connection->query('SELECT 1')->fetch();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * บังคับ reconnect (ใช้ใน health check หรือ CLI command)
     */
    public static function reconnect(): void {
        if (self::$instance !== null) {
            self::$instance->log('Manual database reconnect triggered.');
            self::$instance->recreateConnection();
        }
    }

    /**
     * Redis Client (Predis ^2.2)
     */
    public function getRedis(): ?RedisClient {
        if ($this->redis === null && !empty($_ENV['REDIS_HOST'] ?? '')) {
            try {
                $this->redis = new RedisClient([
                    'scheme'   => 'tcp',
                    'host'     => $_ENV['REDIS_HOST'],
                    'port'     => (int)($_ENV['REDIS_PORT'] ?? 6379),
                    'password' => $_ENV['REDIS_PASS'] ?? null,
                    'database' => (int)($_ENV['REDIS_DB'] ?? 0),
                    'timeout'  => 5.0,
                ]);
                $this->redis->ping();
                $this->log('Redis connected: ' . $_ENV['REDIS_HOST']);
            } catch (\Exception $e) {
                $this->log('Redis connection failed: ' . $e->getMessage());
                $this->redis = null;
            }
        }
        return $this->redis;
    }

    /**
     * Log ข้อความไป storage/logs/
     */
    private function log(string $message): void {
        $logDir = __DIR__ . '/../storage/logs/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . 'app_' . date('Y-m-d') . '.log';
        $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        error_log($entry, 3, $logFile);
    }

    /**
     * Fatal error (Dev: throw, Prod: JSON 500)
     */
    private function fatalError(string $message): never {
        $this->log('FATAL ERROR: ' . $message);
        if ($this->isDev) {
            throw new \RuntimeException($message);
        }
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error'   => 'Internal Server Error',
            'message' => 'Database configuration error'
        ], JSON_UNESCAPED_UNICODE);
        exit(1);
    }

    private function __clone() {
    }
    public function __wakeup() {
    }
}
