<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\DbSetup
 *      เชื่อม / สร้างฐานข้อมูลด้วย PDO โดยไม่พึ่ง Database singleton
 *      ใช้กับ php deawx db:create และ install
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

use PDO;
use PDOException;

final class DbSetup
{
    /**
     * @return array{
     *     type: string,
     *     host: string,
     *     port: int,
     *     name: string,
     *     user: string,
     *     password: string,
     *     charset: string,
     *     collation: string
     * }
     */
    public static function configFromEnv(): array
    {
        $charset = (string) ($_ENV['DB_CHARSET'] ?? 'utf8mb4');
        $collationEnv = (string) ($_ENV['DB_COLLATION'] ?? '');

        return [
            'type' => strtolower((string) ($_ENV['DB_TYPE'] ?? 'mysql')),
            'host' => (string) ($_ENV['DB_HOST'] ?? '127.0.0.1'),
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'name' => (string) ($_ENV['DB_NAME'] ?? ''),
            'user' => (string) ($_ENV['DB_USER'] ?? 'root'),
            'password' => (string) ($_ENV['DB_PASSWORD'] ?? ''),
            'charset' => $charset,
            'collation' => self::collationForCharset($charset, $collationEnv !== '' ? $collationEnv : null),
        ];
    }

    /**
     * ค่า collation มาตรฐานตาม charset เช่น utf8mb4 → utf8mb4_general_ci
     */
    public static function defaultCollation(string $charset): string
    {
        $charset = preg_replace('/[^a-zA-Z0-9]/', '', $charset) ?: 'utf8mb4';

        return $charset . '_general_ci';
    }

    /**
     * ใช้ $preferred ถ้ายังขึ้นต้นด้วย charset_ ไม่เช่นนั้นคืน {charset}_general_ci
     */
    public static function collationForCharset(string $charset, ?string $preferred = null): string
    {
        $charset = preg_replace('/[^a-zA-Z0-9]/', '', $charset) ?: 'utf8mb4';
        $preferred = $preferred !== null
            ? (preg_replace('/[^a-zA-Z0-9_]/', '', $preferred) ?: '')
            : '';

        if ($preferred !== '' && str_starts_with($preferred, $charset . '_')) {
            return $preferred;
        }

        return self::defaultCollation($charset);
    }

    /**
     * @param array{
     *     type?: string,
     *     host?: string,
     *     port?: int|string,
     *     name?: string,
     *     user?: string,
     *     password?: string,
     *     charset?: string,
     *     collation?: string
     * } $overrides
     * @return array{
     *     type: string,
     *     host: string,
     *     port: int,
     *     name: string,
     *     user: string,
     *     password: string,
     *     charset: string,
     *     collation: string
     * }
     */
    public static function mergeConfig(array $overrides = []): array
    {
        $base = self::configFromEnv();
        foreach ($overrides as $key => $value) {
            if (!array_key_exists($key, $base) || $value === null) {
                continue;
            }
            $base[$key] = $key === 'port' ? (int) $value : (string) $value;
        }
        $base['type'] = strtolower($base['type']);
        $base['port'] = (int) $base['port'];
        $base['collation'] = self::collationForCharset($base['charset'], $base['collation']);

        return $base;
    }

    public static function assertMysqlFamily(string $type): void
    {
        if (!in_array($type, ['mysql', 'mariadb'], true)) {
            throw new \InvalidArgumentException('db:create / install รองรับเฉพาะ mysql และ mariadb');
        }
    }

    public static function assertDatabaseName(string $name): void
    {
        if ($name === '' || preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            throw new \InvalidArgumentException('ชื่อฐานข้อมูลต้องเป็นตัวอักษร ตัวเลข หรือ _ เท่านั้น');
        }
    }

    /**
     * @param array{
     *     type: string,
     *     host: string,
     *     port: int,
     *     name: string,
     *     user: string,
     *     password: string,
     *     charset: string,
     *     collation: string
     * } $config
     */
    public static function serverPdo(array $config): PDO
    {
        self::assertMysqlFamily($config['type']);

        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=%s',
            $config['host'],
            $config['port'],
            $config['charset'],
        );

        return new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * @param array{
     *     type: string,
     *     host: string,
     *     port: int,
     *     name: string,
     *     user: string,
     *     password: string,
     *     charset: string,
     *     collation: string
     * } $config
     */
    public static function databasePdo(array $config): PDO
    {
        self::assertMysqlFamily($config['type']);
        self::assertDatabaseName($config['name']);

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset'],
        );

        return new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * @param array{
     *     type: string,
     *     host: string,
     *     port: int,
     *     name: string,
     *     user: string,
     *     password: string,
     *     charset: string,
     *     collation: string
     * } $config
     */
    public static function databaseExists(array $config): bool
    {
        self::assertDatabaseName($config['name']);
        $pdo = self::serverPdo($config);
        $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
        $stmt->execute([$config['name']]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param array{
     *     type: string,
     *     host: string,
     *     port: int,
     *     name: string,
     *     user: string,
     *     password: string,
     *     charset: string,
     *     collation: string
     * } $config
     */
    public static function createDatabase(array $config): void
    {
        self::assertDatabaseName($config['name']);
        $charset = preg_replace('/[^a-zA-Z0-9]/', '', $config['charset']) ?: 'utf8mb4';
        $collation = self::collationForCharset($charset, $config['collation']);
        $name = '`' . str_replace('`', '``', $config['name']) . '`';

        $pdo = self::serverPdo($config);
        $pdo->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS %s CHARACTER SET %s COLLATE %s',
            $name,
            $charset,
            $collation,
        ));
    }

    /**
     * ทดสอบต่อเซิร์ฟเวอร์ + ฐาน (สร้างฐานถ้ายังไม่มีเมื่อ $createMissing = true)
     *
     * @param array{
     *     type: string,
     *     host: string,
     *     port: int,
     *     name: string,
     *     user: string,
     *     password: string,
     *     charset: string,
     *     collation: string
     * } $config
     * @return array{ok: bool, created: bool, message: string}
     */
    public static function ensureReachable(array $config, bool $createMissing = true): array
    {
        try {
            self::assertMysqlFamily($config['type']);
            self::assertDatabaseName($config['name']);
            self::serverPdo($config)->query('SELECT 1');
        } catch (PDOException|\InvalidArgumentException $e) {
            return ['ok' => false, 'created' => false, 'message' => $e->getMessage()];
        }

        $created = false;
        if (!self::databaseExists($config)) {
            if (!$createMissing) {
                return ['ok' => false, 'created' => false, 'message' => "ยังไม่มีฐานข้อมูล {$config['name']}"];
            }
            try {
                self::createDatabase($config);
                $created = true;
            } catch (PDOException $e) {
                return ['ok' => false, 'created' => false, 'message' => $e->getMessage()];
            }
        }

        try {
            self::databasePdo($config)->query('SELECT 1');
        } catch (PDOException $e) {
            return ['ok' => false, 'created' => $created, 'message' => $e->getMessage()];
        }

        return ['ok' => true, 'created' => $created, 'message' => 'connected'];
    }

    /**
     * @param array{
     *     type: string,
     *     host: string,
     *     port: int,
     *     name: string,
     *     user: string,
     *     password: string,
     *     charset: string,
     *     collation: string
     * } $config
     * @return list<string>
     */
    public static function tables(array $config): array
    {
        $pdo = self::databasePdo($config);
        $stmt = $pdo->query('SHOW TABLES');
        if ($stmt === false) {
            return [];
        }

        $tables = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            if (isset($row[0]) && is_string($row[0])) {
                $tables[] = $row[0];
            }
        }

        return $tables;
    }

    /**
     * ซิงก์ค่า config เข้า $_ENV / putenv หลังเขียน .env
     *
     * @param array{
     *     type: string,
     *     host: string,
     *     port: int,
     *     name: string,
     *     user: string,
     *     password: string,
     *     charset: string,
     *     collation: string
     * } $config
     */
    public static function applyEnv(array $config): void
    {
        $map = [
            'DB_TYPE' => $config['type'],
            'DB_HOST' => $config['host'],
            'DB_PORT' => (string) $config['port'],
            'DB_NAME' => $config['name'],
            'DB_USER' => $config['user'],
            'DB_PASSWORD' => $config['password'],
            'DB_CHARSET' => $config['charset'],
            'DB_COLLATION' => $config['collation'],
        ];

        foreach ($map as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    public static function formatEnvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/[\s#"\'\\\\]/', $value) === 1) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        }

        return $value;
    }
}
