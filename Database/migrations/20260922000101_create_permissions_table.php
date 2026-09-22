<?php

declare(strict_types=1);

use Core\Blueprint;
use Core\Migration;
use Core\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('permissions', static function (Blueprint $table): void {
            $table->comment('สิทธิ์การทำงาน เช่น users.view, roles.create');
            $table->id()->comment('รหัสสิทธิ์');
            $table->string('name', 96)->unique()->comment('ชื่อสิทธิ์แบบ slug (ใช้ในโค้ด)');
            $table->string('display_name', 120)->comment('ชื่อแสดงผล');
            $table->timestamp('created_at')->nullable()->comment('วันเวลาที่สร้าง');
            $table->timestamp('updated_at')->nullable()->comment('วันเวลาที่แก้ไขล่าสุด');
        });
    }

    public function down(): void
    {
        Schema::drop('permissions');
    }
};
