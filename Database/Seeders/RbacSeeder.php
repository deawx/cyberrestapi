<?php

declare(strict_types=1);

namespace Database\Seeders;

use Core\Security;
use Core\Seeder;

final class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->db()->insert('roles', [
            ['name' => 'admin', 'display_name' => 'Administrator', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'user', 'display_name' => 'User', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $permissions = [
            ['name' => 'profile.view', 'display_name' => 'ดูโปรไฟล์ตัวเอง'],
            ['name' => 'profile.update', 'display_name' => 'แก้ไขโปรไฟล์ตัวเอง'],
            ['name' => 'self.permissions.view', 'display_name' => 'ดูสิทธิ์ตัวเอง'],
            ['name' => 'users.view', 'display_name' => 'ดูรายชื่อผู้ใช้'],
            ['name' => 'users.create', 'display_name' => 'เพิ่มผู้ใช้'],
            ['name' => 'users.update', 'display_name' => 'แก้ไขผู้ใช้'],
            ['name' => 'users.delete', 'display_name' => 'ลบผู้ใช้'],
            ['name' => 'roles.view', 'display_name' => 'ดูบทบาท'],
            ['name' => 'roles.create', 'display_name' => 'เพิ่มบทบาท'],
            ['name' => 'roles.update', 'display_name' => 'แก้ไขบทบาท'],
            ['name' => 'roles.delete', 'display_name' => 'ลบบทบาท'],
            ['name' => 'permissions.view', 'display_name' => 'ดูสิทธิ์'],
            ['name' => 'permissions.create', 'display_name' => 'เพิ่มสิทธิ์'],
            ['name' => 'permissions.update', 'display_name' => 'แก้ไขสิทธิ์'],
            ['name' => 'permissions.delete', 'display_name' => 'ลบสิทธิ์'],
        ];

        foreach ($permissions as &$row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }
        unset($row);
        $this->db()->insert('permissions', $permissions);

        $adminRoleId = (int) $this->db()->get('roles', 'id', ['name' => 'admin']);
        $userRoleId = (int) $this->db()->get('roles', 'id', ['name' => 'user']);
        $allPermissionIds = $this->db()->select('permissions', 'id') ?: [];
        $memberPermissionIds = $this->db()->select('permissions', 'id', [
            'name' => ['profile.view', 'profile.update', 'self.permissions.view'],
        ]) ?: [];

        $rolePermissions = [];
        foreach ($allPermissionIds as $permissionId) {
            $rolePermissions[] = [
                'role_id' => $adminRoleId,
                'permission_id' => (int) $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach ($memberPermissionIds as $permissionId) {
            $rolePermissions[] = [
                'role_id' => $userRoleId,
                'permission_id' => (int) $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rolePermissions !== []) {
            $this->db()->insert('role_permissions', $rolePermissions);
        }

        $password = Security::hashPassword('password');

        $this->db()->insert('users', [
            [
                'name' => 'Admin',
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => $password,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'User',
                'username' => 'user',
                'email' => 'user@example.com',
                'password' => $password,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $adminUserId = (int) $this->db()->get('users', 'id', ['username' => 'admin']);
        $memberUserId = (int) $this->db()->get('users', 'id', ['username' => 'user']);

        $this->db()->insert('user_roles', [
            [
                'user_id' => $adminUserId,
                'role_id' => $adminRoleId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $memberUserId,
                'role_id' => $userRoleId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
