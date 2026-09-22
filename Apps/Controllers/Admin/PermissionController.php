<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Permission;
use Core\Controller;
use Core\Request;

final class PermissionController extends Controller
{
    public function index(Request $request): never
    {
        $this->json(array_map(
            static fn(Permission $permission): array => $permission->toArray(),
            Permission::all(),
        ));
    }

    public function show(Request $request, string $id): never
    {
        $this->json(Permission::findOrFail((int) $id)->toArray());
    }

    public function store(Request $request): never
    {
        $data = $this->validate([
            'name' => 'required|string|max:96',
            'display_name' => 'required|string|max:120',
        ]);

        $name = strtolower(trim((string) $data['name']));
        if (!preg_match('/^[a-z0-9_.]+$/', $name)) {
            $this->error('ชื่อ permission ใช้ได้แค่ a-z 0-9 _ และ .', 422, ['name' => ['รูปแบบไม่ถูกต้อง']]);
        }
        if (Permission::where('name', $name) !== null) {
            $this->error('permission นี้มีอยู่แล้ว', 422, ['name' => ['ซ้ำ']]);
        }

        $permission = Permission::create([
            'name' => $name,
            'display_name' => (string) $data['display_name'],
        ]);
        if ($permission === null) {
            $this->error('สร้าง permission ไม่สำเร็จ', 500);
        }

        $this->json($permission->toArray(), 201, 'Permission created');
    }

    public function update(Request $request, string $id): never
    {
        $permission = Permission::findOrFail((int) $id);
        $data = $this->validate([
            'name' => 'string|max:96',
            'display_name' => 'string|max:120',
        ]);

        if (isset($data['name'])) {
            $name = strtolower(trim((string) $data['name']));
            if (!preg_match('/^[a-z0-9_.]+$/', $name)) {
                $this->error('ชื่อ permission ใช้ได้แค่ a-z 0-9 _ และ .', 422, ['name' => ['รูปแบบไม่ถูกต้อง']]);
            }
            $existing = Permission::where('name', $name);
            if ($existing !== null && (int) $existing->id !== (int) $permission->id) {
                $this->error('permission นี้มีอยู่แล้ว', 422, ['name' => ['ซ้ำ']]);
            }
            $permission->name = $name;
        }
        if (isset($data['display_name'])) {
            $permission->display_name = (string) $data['display_name'];
        }

        if (!$permission->save()) {
            $this->error('บันทึก permission ไม่สำเร็จ', 500);
        }

        $this->json($permission->toArray(), 200, 'Permission updated');
    }

    public function destroy(Request $request, string $id): never
    {
        $permission = Permission::findOrFail((int) $id);
        if (!$permission->delete()) {
            $this->error('ลบ permission ไม่สำเร็จ', 500);
        }

        $this->json(['deleted' => true], 200, 'Permission deleted');
    }
}
