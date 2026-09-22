<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Database
 *      เชื่อมต่อฐานข้อมูลด้วย Medoo (Singleton)
 *      รองรับ MySQL/MariaDB และ reconnect เมื่อต่อหลุด
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

use PDO;
use Medoo\Medoo;
use Dotenv\Dotenv;
use Predis\Client as RedisClient;

final class Database
{
    private static ?self $instance = null;
    private Medoo $connection;           // ไม่ readonly เพราะต้อง recreate เมื่อ reconnect
    private ?RedisClient $redis = null;
    private readonly bool $isDev;
    private array $dbConfig;             // เก็บ config สำหรับ recreate connection

    /**
     * Constructor ส่วนตัว - Load .env + เชื่อมต่อ DB ครั้งแรก
     */
    private function __construct()
    {
        // 1. Load .env
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->safeLoad();

        $this->isDev = ($_ENV['APP_ENV'] ?? 'prod') === 'dev';

        $required = ['DB_TYPE', 'DB_HOST', 'DB_NAME', 'DB_USER', 'APP_ENV'];
        foreach ($required as $key) {
            if (($_ENV[$key] ?? '') === '') {
                $this->fatalError("Missing .env variable: {$key} - กรุณาเช็คไฟล์ .env หรือ .env.example");
            }
        }

        $driver = strtolower((string) $_ENV['DB_TYPE']);
        $charset = preg_replace('/[^a-zA-Z0-9]/', '', (string) ($_ENV['DB_CHARSET'] ?? 'utf8mb4')) ?: 'utf8mb4';
        $collation = DbSetup::collationForCharset(
            $charset,
            isset($_ENV['DB_COLLATION']) ? (string) $_ENV['DB_COLLATION'] : null,
        );

        // PDO options ตามที่ Medoo รับผ่านคีย์ option — ดู https://medoo.in/api/new
        $options = [
            PDO::ATTR_CASE => PDO::CASE_NATURAL,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_TIMEOUT => 10,
        ];

        // คำสั่งหลังเชื่อมต่อ (Medoo จะรัน SET SQL_MODE=ANSI_QUOTES และ SET NAMES จาก charset/collation ให้อยู่แล้ว)
        $commands = [];
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $commands = [
                "SET time_zone = '+07:00'",
                "SET SQL_MODE = 'ANSI_QUOTES,ONLY_FULL_GROUP_BY,ERROR_FOR_DIVISION_BY_ZERO'",
            ];
        }

        $this->dbConfig = [
            'type' => $_ENV['DB_TYPE'],
            'host' => $_ENV['DB_HOST'],
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'database' => $_ENV['DB_NAME'],
            'username' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => $charset,
            'collation' => $collation,
            'prefix' => $_ENV['DB_PREFIX'] ?? '',
            'option' => $options,
            'error' => PDO::ERRMODE_EXCEPTION,
            'command' => $commands,
        ];

        // 4. สร้าง connection ครั้งแรก
        $this->recreateConnection();

        // 5. Log success
        $this->log('Database connected successfully: ' . $_ENV['DB_HOST'] . '/' . $_ENV['DB_NAME']);
    }

    /**
     * สร้างหรือ recreate Medoo connection ใหม่
     */
    private function recreateConnection(): void
    {
        try {
            $this->connection = new Medoo($this->dbConfig);
            $this->ping();
        } catch (\Exception $e) {
            $this->fatalError('Initial database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * ดึง Singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * คืนค่า Medoo connection พร้อม Lazy Auto-Reconnect
     * ถ้า connection ตาย → recreate อัตโนมัติก่อน return
     */
    public function getConnection(): Medoo
    {
        try {
            $this->ping();
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

    private function ping(): void
    {
        $stmt = $this->connection->query('SELECT 1');
        if ($stmt === null) {
            throw new \RuntimeException('Database ping failed');
        }
        $stmt->fetch();
    }

    /**
     * เช็คสถานะ connection (สำหรับ health check หรือ debug)
     */
    public function isConnected(): bool
    {
        try {
            $this->ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * บังคับ reconnect (ใช้ใน health check หรือ CLI command)
     */
    public static function reconnect(): void
    {
        if (self::$instance !== null) {
            self::$instance->log('Manual database reconnect triggered.');
            self::$instance->recreateConnection();
        }
    }

    /**
     * Redis Client (Predis ^2.2)
     */
    public function getRedis(): ?RedisClient
    {
        if ($this->redis === null && !empty($_ENV['REDIS_HOST'] ?? '')) {
            try {
                $this->redis = new RedisClient([
                    'scheme'   => 'tcp',
                    'host'     => $_ENV['REDIS_HOST'],
                    'port'     => (int) ($_ENV['REDIS_PORT'] ?? 6379),
                    'password' => $_ENV['REDIS_PASS'] ?? null,
                    'database' => (int) ($_ENV['REDIS_DB'] ?? 0),
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
    private function log(string $message): void
    {
        Log::write($message);
    }

    /**
     * Fatal error (Dev: throw, Prod: JSON 500)
     */
    private function fatalError(string $message): never
    {
        $this->log('FATAL ERROR: ' . $message);
        if ($this->isDev) {
            throw new \RuntimeException($message);
        }
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error'   => 'Internal Server Error',
            'message' => 'Database configuration error',
        ], JSON_UNESCAPED_UNICODE);
        exit(1);
    }

    private function __clone() {}

    public function __wakeup() {}
}
