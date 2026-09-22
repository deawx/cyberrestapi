<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Migrator
 *      รัน ย้อน และตรวจสถานะ migration
 *      บันทึกชุดที่รันแล้วในตาราง migrations
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

use Medoo\Medoo;

final class Migrator
{
    public static function path(): string
    {
        return dirname(__DIR__) . '/Database/migrations';
    }

    /**
     * @return list<string>
     */
    public static function migrate(bool $step = false, ?string $path = null): array
    {
        self::ensureTable();
        $ran = self::ran();
        $allow = self::pathFilter($path);
        $batch = $step ? 0 : self::nextBatch();
        $applied = [];

        foreach (self::files() as $file) {
            $name = basename($file);
            if (!self::allowed($name, $allow['names'])) {
                continue;
            }
            if (in_array($name, $ran, true)) {
                continue;
            }

            self::load($file)->up();
            $useBatch = $step ? self::nextBatch() : $batch;
            self::db()->insert('migrations', [
                'migration' => $name,
                'batch' => $useBatch,
            ]);
            $applied[] = $name;
        }

        return $applied;
    }

    /**
     * ย้อนแบบใกล้ Laravel
     * ไม่ระบุอะไร = ชุด batch ล่าสุด
     * $step / $batch = เหมือน artisan
     * $path = ไฟล์หรือชื่อเช่น create_users_table
     * ถ้ามีแค่ $path จะย้อนไฟล์ที่ตรงนั้น (แม้ไม่ได้อยู่ batch ล่าสุด)
     *
     * @return list<string>
     */
    public static function rollback(?int $step = null, ?int $batch = null, ?string $path = null): array
    {
        self::ensureTable();
        $filter = self::pathFilter($path);

        return self::rollbackNames(self::pickRollback(
            self::ranRows(),
            $step,
            $batch,
            $filter['names'],
            $filter['pathOnly'],
        ));
    }

    /**
     * ย้อนทุกไฟล์ที่รันแล้ว จากใหม่ไปเก่า
     *
     * @return list<string>
     */
    public static function reset(): array
    {
        self::ensureTable();
        $ran = self::ranRows();
        if ($ran === []) {
            return [];
        }

        return self::rollbackNames(self::pickRollback($ran, count($ran)));
    }

    /**
     * เลือกชื่อไฟล์ที่จะย้อน จากแถวในตาราง migrations
     *
     * @param list<array{migration: string, batch: int}> $ran
     * @param list<string>|null $allow
     * @return list<string>
     */
    public static function pickRollback(
        array $ran,
        ?int $step = null,
        ?int $batch = null,
        ?array $allow = null,
        bool $pathOnly = false,
    ): array {
        if ($pathOnly && $allow !== null && $step === null && $batch === null) {
            $ran = array_values(array_filter(
                $ran,
                static fn(array $row): bool => in_array($row['migration'], $allow, true),
            ));
            if ($ran === []) {
                return [];
            }

            return self::pickRollback($ran, count($ran));
        }

        if ($step !== null) {
            if ($step < 1) {
                throw new \InvalidArgumentException('ตัวเลือก --step ต้องมากกว่า 0');
            }

            usort($ran, static function (array $left, array $right): int {
                $byBatch = $right['batch'] <=> $left['batch'];

                return $byBatch !== 0 ? $byBatch : strcmp($right['migration'], $left['migration']);
            });

            $picked = array_map(
                static fn(array $row): string => $row['migration'],
                array_slice($ran, 0, $step),
            );

            return self::restrictAllow($picked, $allow);
        }

        if ($batch !== null) {
            if ($batch < 1) {
                throw new \InvalidArgumentException('ตัวเลือก --batch ต้องมากกว่า 0');
            }

            $filtered = array_values(array_filter(
                $ran,
                static fn(array $row): bool => $row['batch'] === $batch,
            ));
            usort(
                $filtered,
                static fn(array $left, array $right): int => strcmp($right['migration'], $left['migration']),
            );

            return self::restrictAllow(array_map(
                static fn(array $row): string => $row['migration'],
                $filtered,
            ), $allow);
        }

        $last = 0;
        foreach ($ran as $row) {
            $last = max($last, $row['batch']);
        }

        if ($last === 0) {
            return [];
        }

        return self::restrictAllow(self::pickRollback($ran, null, $last), $allow);
    }

    /**
     * @return list<array{migration: string, ran: bool, batch: int|null}>
     */
    public static function status(): array
    {
        self::ensureTable();
        $ran = [];
        foreach (self::db()->select('migrations', ['migration', 'batch']) ?: [] as $row) {
            $ran[(string) $row['migration']] = (int) $row['batch'];
        }

        $status = [];
        foreach (self::files() as $file) {
            $name = basename($file);
            $status[] = [
                'migration' => $name,
                'ran' => array_key_exists($name, $ran),
                'batch' => $ran[$name] ?? null,
            ];
        }

        return $status;
    }

    /**
     * ย้อนทั้งหมดหรือตาม --step แล้ว migrate ใหม่ (ไม่ดรอปตารางที่ไม่มีไฟล์)
     *
     * @return array{rolled: list<string>, migrated: list<string>}
     */
    public static function refresh(?int $step = null, ?string $path = null): array
    {
        $rolled = $step !== null || $path !== null
            ? self::rollback($step, null, $path)
            : self::reset();

        return [
            'rolled' => $rolled,
            'migrated' => self::migrate(false, $path),
        ];
    }

    /**
     * @return list<string>
     */
    public static function fresh(): array
    {
        Schema::withoutForeignKeys(static function (): void {
            foreach (Schema::tables() as $table) {
                Schema::pdo()->exec('DROP TABLE IF EXISTS "' . Blueprint::ident($table) . '"');
            }
        });

        return self::migrate();
    }

    /**
     * @param list<string> $names
     * @param list<string>|null $allow
     * @return list<string>
     */
    public static function restrictAllow(array $names, ?array $allow): array
    {
        if ($allow === null) {
            return $names;
        }

        return array_values(array_filter(
            $names,
            static fn(string $name): bool => in_array($name, $allow, true),
        ));
    }

    /**
     * จับชื่อไฟล์จาก --path แบบใกล้ Laravel
     *
     * @param list<string> $available
     * @return list<string>
     */
    public static function matchPathNames(array $available, string $path): array
    {
        $needle = basename(str_replace('\\', '/', trim($path)));
        $needle = preg_replace('/\.php$/i', '', $needle) ?? $needle;
        if ($needle === '') {
            return [];
        }

        $matched = [];
        foreach ($available as $name) {
            $base = preg_replace('/\.php$/i', '', $name) ?? $name;
            if ($name === $needle || $name === $needle . '.php' || $base === $needle) {
                $matched[] = $name;
                continue;
            }

            if (str_ends_with($base, '_' . $needle) || str_ends_with($base, '_' . $needle . '_table')) {
                $matched[] = $name;
            }
        }

        return $matched;
    }

    /**
     * @return array{names: list<string>|null, pathOnly: bool}
     */
    public static function pathFilter(?string $path): array
    {
        if ($path === null || trim($path) === '') {
            return ['names' => null, 'pathOnly' => false];
        }

        $raw = str_replace('\\', '/', trim($path));
        $tries = [$raw, self::path() . '/' . basename($raw)];
        $cwd = getcwd();
        if (is_string($cwd) && $cwd !== '') {
            $tries[] = $cwd . '/' . $raw;
        }

        foreach ($tries as $try) {
            if (is_file($try)) {
                return ['names' => [basename($try)], 'pathOnly' => true];
            }
            if (is_dir($try)) {
                $files = glob(rtrim($try, '/\\') . '/*.php') ?: [];
                sort($files, SORT_STRING);
                $names = array_values(array_map(
                    static fn(string $file): string => basename($file),
                    $files,
                ));

                return ['names' => $names, 'pathOnly' => false];
            }
        }

        $matched = self::matchPathNames(
            array_map(static fn(string $file): string => basename($file), self::files()),
            $raw,
        );
        if ($matched === []) {
            throw new \InvalidArgumentException('ไม่พบไฟล์ migration ที่ตรงกับ --path=' . $raw);
        }

        return ['names' => $matched, 'pathOnly' => true];
    }

    /**
     * @param list<string>|null $allow
     */
    private static function allowed(string $name, ?array $allow): bool
    {
        return $allow === null || in_array($name, $allow, true);
    }

    private static function db(): Medoo
    {
        return Schema::db();
    }

    private static function load(string $file): Migration
    {
        $migration = require $file;
        if (!$migration instanceof Migration) {
            throw new \RuntimeException('Migration ต้อง return instance ของ Core\\Migration: ' . basename($file));
        }

        return $migration;
    }

    /**
     * @return list<string>
     */
    private static function files(): array
    {
        $files = glob(self::path() . '/*.php') ?: [];
        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * @param list<string> $names
     * @return list<string>
     */
    private static function rollbackNames(array $names): array
    {
        $rolled = [];

        Schema::withoutForeignKeys(static function () use ($names, &$rolled): void {
            foreach ($names as $name) {
                $file = self::path() . '/' . $name;
                if (is_file($file)) {
                    self::load($file)->down();
                }

                self::db()->delete('migrations', ['migration' => $name]);
                $rolled[] = $name;
            }
        });

        return $rolled;
    }

    /**
     * @return list<string>
     */
    private static function ran(): array
    {
        return array_map(
            static fn(array $row): string => $row['migration'],
            self::ranRows(),
        );
    }

    /**
     * @return list<array{migration: string, batch: int}>
     */
    private static function ranRows(): array
    {
        $rows = self::db()->select('migrations', ['migration', 'batch'], [
            'ORDER' => ['id' => 'ASC'],
        ]) ?: [];
        $normalized = [];

        foreach ($rows as $row) {
            $normalized[] = [
                'migration' => (string) $row['migration'],
                'batch' => (int) $row['batch'],
            ];
        }

        return $normalized;
    }

    private static function nextBatch(): int
    {
        return self::lastBatch() + 1;
    }

    private static function lastBatch(): int
    {
        return (int) self::db()->max('migrations', 'batch');
    }

    private static function ensureTable(): void
    {
        if (Schema::hasTable('migrations')) {
            return;
        }

        Schema::create('migrations', [
            '@id',
            'migration' => ['VARCHAR(255)', 'NOT NULL'],
            'batch' => ['INT', 'NOT NULL'],
        ]);
    }
}
