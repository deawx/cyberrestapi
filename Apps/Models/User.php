<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;
use Core\Schema;

final class User extends Model
{
    protected static string $table = 'users';

    protected static array $fillable = [
        'name',
        'username',
        'email',
        'password',
        'is_active',
    ];

    /**
     * สร้าง instance จากแถวในฐาน (ไม่ผ่าน fillable — ใช้ตอน map หลัง paginate)
     *
     * @param array<string, mixed> $attributes
     */
    public static function fromAttributes(array $attributes): self
    {
        $model = new self();
        $model->attributes = $attributes;
        $model->dirty = [];

        return $model;
    }

    /**
     * รายการผู้ใช้แบบตัดหน้า + ค้นหา name / username / email
     *
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public static function paginateList(int $page = 1, int $perPage = 20, string $q = ''): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $q = trim($q);
        if (mb_strlen($q) > 100) {
            $q = mb_substr($q, 0, 100);
        }

        $where = [];
        if ($q !== '') {
            $where['OR'] = [
                'name[~]' => $q,
                'username[~]' => $q,
                'email[~]' => $q,
            ];
        }

        $db = Schema::db();
        $total = $where === []
            ? (int) $db->count(self::$table)
            : (int) $db->count(self::$table, $where);

        $rows = $db->select(self::$table, '*', array_merge($where, [
            'LIMIT' => [$offset, $perPage],
            'ORDER' => ['id' => 'DESC'],
        ])) ?: [];

        return [
            'data' => array_map(
                static fn(array $row): array => self::fromAttributes($row)->toArray(),
                $rows,
            ),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * @return array{
     *     id: int|string|null,
     *     name: mixed,
     *     username: mixed,
     *     email: mixed,
     *     is_active: mixed,
     *     created_at: mixed,
     *     updated_at: mixed
     * }
     */
    public function publicArray(): array
    {
        return [
            'id' => $this->attributes['id'] ?? null,
            'name' => $this->attributes['name'] ?? null,
            'username' => $this->attributes['username'] ?? null,
            'email' => $this->attributes['email'] ?? null,
            'is_active' => (int) ($this->attributes['is_active'] ?? 0) === 1,
            'created_at' => $this->attributes['created_at'] ?? null,
            'updated_at' => $this->attributes['updated_at'] ?? null,
        ];
    }

    /**
     * @return list<string>
     */
    public function roleNames(): array
    {
        $id = (int) ($this->attributes['id'] ?? 0);
        if ($id < 1) {
            return [];
        }

        $rows = Schema::db()->select('user_roles', [
            '[>]roles' => ['role_id' => 'id'],
        ], [
            'roles.name',
        ], [
            'user_roles.user_id' => $id,
            'ORDER' => ['roles.name' => 'ASC'],
        ]);

        $names = [];
        foreach ($rows ?: [] as $row) {
            $name = is_array($row) ? ($row['name'] ?? null) : $row;
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        $id = (int) ($this->attributes['id'] ?? 0);
        if ($id < 1) {
            return [];
        }

        $rows = Schema::db()->select('user_roles', [
            '[>]role_permissions' => ['role_id' => 'role_id'],
            '[>]permissions' => ['role_permissions.permission_id' => 'id'],
        ], [
            'permissions.name',
        ], [
            'user_roles.user_id' => $id,
        ]);

        $names = [];
        foreach ($rows ?: [] as $row) {
            $name = is_array($row) ? ($row['name'] ?? null) : $row;
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        sort($names);

        return array_values(array_unique($names));
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissionNames(), true);
    }

    public function primaryRole(): ?string
    {
        $roles = $this->roleNames();
        if (in_array('admin', $roles, true)) {
            return 'admin';
        }

        return $roles[0] ?? null;
    }

    /**
     * @return array{sub: string, username: string, role: string, roles: list<string>}
     */
    public function jwtClaims(): array
    {
        $roles = $this->roleNames();
        $role = $this->primaryRole() ?? 'user';

        return [
            'sub' => (string) ($this->attributes['id'] ?? ''),
            'username' => (string) ($this->attributes['username'] ?? ''),
            'role' => $role,
            'roles' => $roles,
        ];
    }

    /**
     * @param list<string> $roleNames
     */
    public function syncRoles(array $roleNames): void
    {
        $id = (int) ($this->attributes['id'] ?? 0);
        if ($id < 1) {
            return;
        }

        $db = Schema::db();
        $db->delete('user_roles', ['user_id' => $id]);

        $roleNames = array_values(array_unique(array_filter(
            $roleNames,
            static fn(mixed $name): bool => is_string($name) && $name !== '',
        )));
        if ($roleNames === []) {
            return;
        }

        $roles = $db->select('roles', ['id', 'name'], ['name' => $roleNames]) ?: [];
        $now = date('Y-m-d H:i:s');
        $rows = [];
        foreach ($roles as $role) {
            $rows[] = [
                'user_id' => $id,
                'role_id' => (int) $role['id'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rows !== []) {
            $db->insert('user_roles', $rows);
        }
    }
}
