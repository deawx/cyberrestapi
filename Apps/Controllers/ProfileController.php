<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use Core\Controller;
use Core\Request;
use Core\Security;

final class ProfileController extends Controller
{
    public function show(Request $request): never
    {
        $user = $this->currentUser($request);
        $this->requirePermission($user, 'profile.view');
        $this->json([
            ...$user->publicArray(),
            'roles' => $user->roleNames(),
        ]);
    }

    public function update(Request $request): never
    {
        $user = $this->currentUser($request);
        $this->requirePermission($user, 'profile.update');
        $data = $this->validate([
            'name' => 'string|max:120',
            'username' => 'string|min:3|max:60|regex:^[A-Za-z0-9._-]+$',
            'email' => 'email|max:191',
            'password' => 'string|min:6|max:255',
            'current_password' => 'string|min:6|max:255',
        ]);

        if (isset($data['username'])) {
            $existing = User::where('username', (string) $data['username']);
            if ($existing !== null && (int) $existing->id !== (int) $user->id) {
                $this->error('ชื่อผู้ใช้นี้ถูกใช้แล้ว', 422, ['username' => ['ชื่อผู้ใช้นี้ถูกใช้แล้ว']]);
            }
            $user->username = (string) $data['username'];
        }
        if (isset($data['email'])) {
            $existing = User::where('email', (string) $data['email']);
            if ($existing !== null && (int) $existing->id !== (int) $user->id) {
                $this->error('อีเมลนี้ถูกใช้แล้ว', 422, ['email' => ['อีเมลนี้ถูกใช้แล้ว']]);
            }
            $user->email = (string) $data['email'];
        }
        if (isset($data['name'])) {
            $user->name = (string) $data['name'];
        }

        if (isset($data['password']) && $data['password'] !== '') {
            $current = (string) ($data['current_password'] ?? '');
            if ($current === '') {
                $this->error('ต้องใส่ current_password เพื่อเปลี่ยนรหัสผ่าน', 422, [
                    'current_password' => ['จำเป็นเมื่อเปลี่ยนรหัสผ่าน'],
                ]);
            }
            if (!Security::verifyPassword($current, (string) ($user->password ?? ''))) {
                $this->error('รหัสผ่านปัจจุบันไม่ถูกต้อง', 422, [
                    'current_password' => ['รหัสผ่านปัจจุบันไม่ถูกต้อง'],
                ]);
            }
            $user->password = Security::hashPassword((string) $data['password']);
        }

        if (!$user->save()) {
            $this->error('บันทึกโปรไฟล์ไม่สำเร็จ', 500);
        }

        $this->json([
            ...$user->publicArray(),
            'roles' => $user->roleNames(),
        ], 200, 'Profile updated');
    }

    public function permissions(Request $request): never
    {
        $user = $this->currentUser($request);
        $this->requirePermission($user, 'self.permissions.view');
        $this->json([
            'roles' => $user->roleNames(),
            'permissions' => $user->permissionNames(),
        ]);
    }

    private function requirePermission(User $user, string $permission): void
    {
        if (!$user->hasPermission($permission)) {
            $this->error('Forbidden', 403);
        }
    }

    private function currentUser(Request $request): User
    {
        $id = (int) ($request->userId() ?? 0);
        $user = $id > 0 ? User::find($id) : null;
        if ($user === null || (int) ($user->is_active ?? 0) !== 1) {
            $this->error('Unauthorized', 401);
        }

        return $user;
    }
}
