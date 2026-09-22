<?php

declare(strict_types=1);

use Core\Blueprint;
use Core\Migration;
use Core\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roles', static function (Blueprint $table): void {
            $table->comment('บทบาทในระบบ RBAC เช่น admin, user');
            $table->id()->comment('รหัสบทบาท');
            $table->string('name', 64)->unique()->comment('ชื่อบทบาทแบบ slug (ใช้ในโค้ด/JWT)');
            $table->string('display_name', 120)->comment('ชื่อแสดงผล');
            $table->timestamp('created_at')->nullable()->comment('วันเวลาที่สร้าง');
            $table->timestamp('updated_at')->nullable()->comment('วันเวลาที่แก้ไขล่าสุด');
        });
    }

    public function down(): void
    {
        Schema::drop('roles');
    }
};
