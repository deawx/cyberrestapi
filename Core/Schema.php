<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Schema
 *      สร้าง แก้ ลบ และตรวจตาราง
 *      hasTable, columns, tables, ปิด FK ชั่วคราวตอน drop
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

use Medoo\Medoo;
use PDO;

final class Schema
{
    public static function db(): Medoo
    {
        return Database::getInstance()->getConnection();
    }

    /**
     * สร้างตารางด้วย Medoo create()
     *
     * @param array<int|string, mixed>|callable(Blueprint): void $columns
     * @param array<string, mixed>|string $options
     */
    public static function create(string $table, array|callable $columns, array|string $options = []): void
    {
        Blueprint::ident($table);

        if (self::hasTable($table)) {
            throw new \RuntimeException("ตาราง {$table} มีอยู่แล้ว ไม่สร้างซ้ำ");
        }

        $tableComment = null;
        if (is_callable($columns)) {
            $blueprint = new Blueprint();
            $columns($blueprint);
            $definitions = $blueprint->toColumns();
            $tableComment = $blueprint->tableComment();
        } else {
            $definitions = $columns;
        }

        if ($options === []) {
            $options = [
                'ENGINE' => 'InnoDB',
                'DEFAULT CHARSET' => 'utf8mb4',
            ];
        }

        if (is_array($options) && $tableComment !== null && $tableComment !== '') {
            $options['COMMENT'] = "'" . str_replace("'", "''", $tableComment) . "'";
        }

        self::db()->create($table, $definitions, $options);
    }

    public static function drop(string $table): void
    {
        Blueprint::ident($table);
        if (!self::hasTable($table)) {
            return;
        }

        self::withoutForeignKeys(static function () use ($table): void {
            Schema::db()->drop($table);
        });
    }

    /**
     * @param callable(Blueprint): void $callback
     */
    public static function table(string $table, callable $callback): void
    {
        Blueprint::ident($table);
        if (!self::hasTable($table)) {
            throw new \RuntimeException("ตาราง {$table} ไม่มีอยู่");
        }

        $blueprint = new Blueprint();
        $callback($blueprint);

        foreach ($blueprint->addedColumns() as $column) {
            if (self::hasColumn($table, $column)) {
                throw new \RuntimeException("คอลัมน์ {$table}.{$column} มีอยู่แล้ว");
            }
        }

        foreach ($blueprint->toAlterStatements(self::physical($table)) as $sql) {
            self::pdo()->exec($sql);
        }
    }

    public static function hasTable(string $table): bool
    {
        Blueprint::ident($table);
        $stmt = self::pdo()->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        );
        $stmt->execute([self::physical($table)]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function hasColumn(string $table, string $column): bool
    {
        Blueprint::ident($table);
        Blueprint::ident($column);
        $stmt = self::pdo()->prepare(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
        );
        $stmt->execute([self::physical($table), $column]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @return list<array{name: string, type: string, nullable: bool, key: string, default: string|null, extra: string}>
     */
    public static function columns(string $table): array
    {
        if (!self::hasTable($table)) {
            throw new \RuntimeException("ตาราง {$table} ไม่มีอยู่");
        }

        $stmt = self::pdo()->prepare(
            'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA
             FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ?
             ORDER BY ORDINAL_POSITION ASC',
        );
        $stmt->execute([self::physical($table)]);

        $columns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $row = array_change_key_case($row, CASE_UPPER);
            $columns[] = [
                'name' => (string) $row['COLUMN_NAME'],
                'type' => (string) $row['COLUMN_TYPE'],
                'nullable' => strtoupper((string) $row['IS_NULLABLE']) === 'YES',
                'key' => (string) $row['COLUMN_KEY'],
                'default' => $row['COLUMN_DEFAULT'] === null ? null : (string) $row['COLUMN_DEFAULT'],
                'extra' => (string) $row['EXTRA'],
            ];
        }

        return $columns;
    }

    /**
     * @return list<string>
     */
    public static function tables(): array
    {
        $stmt = self::pdo()->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'",
        );

        return $stmt ? array_map(static fn(mixed $name): string => (string) $name, $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
    }

    public static function withoutForeignKeys(callable $callback): mixed
    {
        self::pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            return $callback();
        } finally {
            self::pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    public static function physical(string $table): string
    {
        return (string) ($_ENV['DB_PREFIX'] ?? '') . Blueprint::ident($table);
    }

    public static function quote(string $table): string
    {
        return '"' . self::physical($table) . '"';
    }

    public static function pdo(): PDO
    {
        return self::db()->pdo;
    }

    /**
     * @param callable(Blueprint): void $factory
     * @return array<int|string, mixed>
     */
    private static function columnsFromBlueprint(callable $factory): array
    {
        $blueprint = new Blueprint();
        $factory($blueprint);

        return $blueprint->toColumns();
    }
}
