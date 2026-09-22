<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Seeder
 *      คลาสพื้นฐานของไฟล์ seeder
 *      มี db(), call() และ truncate() ที่ปลอดภัยต่อ FK
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

use Medoo\Medoo;

abstract class Seeder
{
    abstract public function run(): void;

    protected function db(): Medoo
    {
        return Schema::db();
    }

    /**
     * @param class-string<Seeder> $class
     */
    protected function call(string $class): void
    {
        (new $class())->run();
    }

    protected function truncate(string ...$tables): void
    {
        Schema::withoutForeignKeys(static function () use ($tables): void {
            foreach ($tables as $table) {
                if (!Schema::hasTable($table)) {
                    continue;
                }

                Schema::pdo()->exec('TRUNCATE TABLE ' . Schema::quote($table));
            }
        });
    }
}
