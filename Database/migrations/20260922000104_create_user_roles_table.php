<?php

declare(strict_types=1);

use Core\Blueprint;
use Core\Migration;
use Core\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_roles', static function (Blueprint $table): void {
            $table->comment('ตารางเชื่อม user กับ role (บทบาทของผู้ใช้)');
            $table->id()->comment('รหัสแถว');
            $table->foreignId('user_id', 'users')->comment('FK ไป users.id');
            $table->foreignId('role_id', 'roles')->comment('FK ไป roles.id');
            $table->uniqueIndex(['user_id', 'role_id']);
            $table->timestamp('created_at')->nullable()->comment('วันเวลาที่สร้าง');
            $table->timestamp('updated_at')->nullable()->comment('วันเวลาที่แก้ไขล่าสุด');
        });
    }

    public function down(): void
    {
        Schema::drop('user_roles');
    }
};
