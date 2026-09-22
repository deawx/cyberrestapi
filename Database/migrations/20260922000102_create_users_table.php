<?php

declare(strict_types=1);

use Core\Blueprint;
use Core\Migration;
use Core\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', static function (Blueprint $table): void {
            $table->comment('บัญชีผู้ใช้สำหรับล็อกอิน API');
            $table->id()->comment('รหัสผู้ใช้');
            $table->string('name', 120)->comment('ชื่อที่แสดง');
            $table->string('username', 60)->unique()->comment('ชื่อผู้ใช้สำหรับล็อกอิน');
            $table->string('email', 191)->unique()->comment('อีเมลติดต่อ (ไม่ใช้ล็อกอิน)');
            $table->string('password', 255)->comment('รหัสผ่านที่ hash แล้ว');
            $table->boolean('is_active')->default(1)->comment('1=ใช้งานได้ 0=ระงับ');
            $table->timestamp('created_at')->nullable()->comment('วันเวลาที่สร้าง');
            $table->timestamp('updated_at')->nullable()->comment('วันเวลาที่แก้ไขล่าสุด');
        });
    }

    public function down(): void
    {
        Schema::drop('users');
    }
};
