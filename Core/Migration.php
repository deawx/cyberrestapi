<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Migration
 *      คลาสพื้นฐานของไฟล์ migration
 *      กำหนด up() / down() และเรียก Medoo ผ่าน db()
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

use Medoo\Medoo;

abstract class Migration
{
    abstract public function up(): void;

    abstract public function down(): void;

    protected function db(): Medoo
    {
        return Schema::db();
    }
}
