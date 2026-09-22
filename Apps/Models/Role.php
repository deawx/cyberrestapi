<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;
use Core\Schema;

final class Role extends Model
{
    protected static string $table = 'roles';

    protected static array $fillable = [
        'name',
        'display_name',
    ];

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        $id = (int) ($this->attributes['id'] ?? 0);
        if ($id < 1) {
            return [];
        }

        $rows = Schema::db()->select('role_permissions', [
            '[>]permissions' => ['permission_id' => 'id'],
        ], [
            'permissions.name',
        ], [
            'role_permissions.role_id' => $id,
            'ORDER' => ['permissions.name' => 'ASC'],
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
     * @param list<string> $permissionNames
     */
    public function syncPermissions(array $permissionNames): void
    {
        $id = (int) ($this->attributes['id'] ?? 0);
        if ($id < 1) {
            return;
        }

        $db = Schema::db();
        $db->delete('role_permissions', ['role_id' => $id]);

        $permissionNames = array_values(array_unique(array_filter(
            $permissionNames,
            static fn(mixed $name): bool => is_string($name) && $name !== '',
        )));
        if ($permissionNames === []) {
            return;
        }

        $permissions = $db->select('permissions', ['id', 'name'], ['name' => $permissionNames]) ?: [];
        $now = date('Y-m-d H:i:s');
        $rows = [];
        foreach ($permissions as $permission) {
            $rows[] = [
                'role_id' => $id,
                'permission_id' => (int) $permission['id'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rows !== []) {
            $db->insert('role_permissions', $rows);
        }
    }

    /**
     * @return array{id: mixed, name: mixed, display_name: mixed, permissions: list<string>, created_at: mixed, updated_at: mixed}
     */
    public function withPermissions(): array
    {
        return [
            'id' => $this->attributes['id'] ?? null,
            'name' => $this->attributes['name'] ?? null,
            'display_name' => $this->attributes['display_name'] ?? null,
            'permissions' => $this->permissionNames(),
            'created_at' => $this->attributes['created_at'] ?? null,
            'updated_at' => $this->attributes['updated_at'] ?? null,
        ];
    }
}
