<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Role;
use Core\Controller;
use Core\Request;

final class RoleController extends Controller
{
    public function index(Request $request): never
    {
        $roles = array_map(
            static fn(Role $role): array => $role->withPermissions(),
            Role::all(),
        );
        $this->json($roles);
    }

    public function show(Request $request, string $id): never
    {
        $role = Role::findOrFail((int) $id);
        $this->json($role->withPermissions());
    }

    public function store(Request $request): never
    {
        $data = $this->validate([
            'name' => 'required|string|max:64',
            'display_name' => 'required|string|max:120',
            'permissions' => 'array',
        ]);

        $name = strtolower(trim((string) $data['name']));
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            $this->error('ชื่อ role ใช้ได้แค่ a-z 0-9 และ _', 422, ['name' => ['รูปแบบไม่ถูกต้อง']]);
        }
        if (Role::where('name', $name) !== null) {
            $this->error('role นี้มีอยู่แล้ว', 422, ['name' => ['ซ้ำ']]);
        }

        $role = Role::create([
            'name' => $name,
            'display_name' => (string) $data['display_name'],
        ]);
        if ($role === null) {
            $this->error('สร้าง role ไม่สำเร็จ', 500);
        }

        if (isset($data['permissions']) && is_array($data['permissions'])) {
            $role->syncPermissions(array_map('strval', $data['permissions']));
        }

        $this->json($role->withPermissions(), 201, 'Role created');
    }

    public function update(Request $request, string $id): never
    {
        $role = Role::findOrFail((int) $id);
        $data = $this->validate([
            'name' => 'string|max:64',
            'display_name' => 'string|max:120',
        ]);

        if (isset($data['name'])) {
            $name = strtolower(trim((string) $data['name']));
            if (!preg_match('/^[a-z0-9_]+$/', $name)) {
                $this->error('ชื่อ role ใช้ได้แค่ a-z 0-9 และ _', 422, ['name' => ['รูปแบบไม่ถูกต้อง']]);
            }
            $existing = Role::where('name', $name);
            if ($existing !== null && (int) $existing->id !== (int) $role->id) {
                $this->error('role นี้มีอยู่แล้ว', 422, ['name' => ['ซ้ำ']]);
            }
            $role->name = $name;
        }
        if (isset($data['display_name'])) {
            $role->display_name = (string) $data['display_name'];
        }

        if (!$role->save()) {
            $this->error('บันทึก role ไม่สำเร็จ', 500);
        }

        $this->json($role->withPermissions(), 200, 'Role updated');
    }

    public function destroy(Request $request, string $id): never
    {
        $role = Role::findOrFail((int) $id);
        if (in_array((string) $role->name, ['admin', 'user'], true)) {
            $this->error('ลบ role เริ่มต้นไม่ได้', 422);
        }

        if (!$role->delete()) {
            $this->error('ลบ role ไม่สำเร็จ', 500);
        }

        $this->json(['deleted' => true], 200, 'Role deleted');
    }

    public function syncPermissions(Request $request, string $id): never
    {
        $role = Role::findOrFail((int) $id);
        $data = $this->validate([
            'permissions' => 'required|array',
        ]);

        $permissions = is_array($data['permissions']) ? array_map('strval', $data['permissions']) : [];
        $role->syncPermissions($permissions);

        $this->json($role->withPermissions(), 200, 'Permissions assigned');
    }
}
