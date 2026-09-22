<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\User;
use Core\Controller;
use Core\Paginator;
use Core\Request;
use Core\Response;
use Core\Schema;
use Core\Security;
use Core\Validator;

final class UserController extends Controller
{
    private const BULK_MAX = 50;

    public function index(Request $request): never
    {
        [$page, $perPage] = Paginator::fromRequest($request);
        $q = trim((string) $request->input('q', ''));
        $result = User::paginateList($page, $perPage, $q);

        $data = array_map(static function (array $row): array {
            $user = User::fromAttributes($row);

            return [
                ...$user->publicArray(),
                'roles' => $user->roleNames(),
            ];
        }, $result['data']);

        Response::paginate($data, $result['total'], $result['page'], $result['per_page']);
    }

    public function show(Request $request, string $id): never
    {
        $user = User::findOrFail((int) $id);
        $this->json([
            ...$user->publicArray(),
            'roles' => $user->roleNames(),
            'permissions' => $user->permissionNames(),
        ]);
    }

    public function store(Request $request): never
    {
        $data = $this->validate([
            'name' => 'required|string|max:120',
            'username' => 'required|string|min:3|max:60|regex:^[A-Za-z0-9._-]+$',
            'email' => 'required|email|max:191',
            'password' => 'required|string|min:6|max:255',
            'is_active' => 'boolean',
            'roles' => 'array',
        ]);

        $user = $this->createUser($data);
        $this->json([
            ...$user->publicArray(),
            'roles' => $user->roleNames(),
        ], 201, 'User created');
    }

    public function storeBulk(Request $request): never
    {
        $payload = $this->validate([
            'users' => 'required|array',
        ]);

        $items = $payload['users'] ?? [];
        if (!is_array($items) || $items === []) {
            $this->error('users ต้องเป็น array และไม่ว่าง', 422, ['users' => ['ต้องมีอย่างน้อย 1 รายการ']]);
        }
        if (count($items) > self::BULK_MAX) {
            $this->error('สร้างได้สูงสุด ' . self::BULK_MAX . ' คนต่อครั้ง', 422, [
                'users' => ['สูงสุด ' . self::BULK_MAX . ' รายการ'],
            ]);
        }

        $prepared = [];
        $errors = [];
        $usernames = [];
        $emails = [];

        foreach (array_values($items) as $index => $item) {
            if (!is_array($item)) {
                $errors[(string) $index] = ['ต้องเป็น object'];
                continue;
            }

            $validator = Validator::make($item, [
                'name' => 'required|string|max:120',
                'username' => 'required|string|min:3|max:60|regex:^[A-Za-z0-9._-]+$',
                'email' => 'required|email|max:191',
                'password' => 'required|string|min:6|max:255',
                'is_active' => 'boolean',
                'roles' => 'array',
            ]);
            if ($validator->fails()) {
                $errors[(string) $index] = $validator->errors();
                continue;
            }

            $row = $validator->validated();
            $username = strtolower((string) $row['username']);
            $email = strtolower((string) $row['email']);

            if (isset($usernames[$username])) {
                $errors[(string) $index] = ['username' => ['ชื่อผู้ใช้ซ้ำในรายการ']];
                continue;
            }
            if (isset($emails[$email])) {
                $errors[(string) $index] = ['email' => ['อีเมลซ้ำในรายการ']];
                continue;
            }
            $usernames[$username] = true;
            $emails[$email] = true;

            if (User::where('username', (string) $row['username']) !== null) {
                $errors[(string) $index] = ['username' => ['ชื่อผู้ใช้นี้ถูกใช้แล้ว']];
                continue;
            }
            if (User::where('email', (string) $row['email']) !== null) {
                $errors[(string) $index] = ['email' => ['อีเมลนี้ถูกใช้แล้ว']];
                continue;
            }

            $prepared[] = $row;
        }

        if ($errors !== []) {
            $this->error('ข้อมูลผู้ใช้ไม่ถูกต้อง', 422, $errors);
        }

        $created = [];
        try {
            Schema::db()->action(function () use ($prepared, &$created): void {
                foreach ($prepared as $row) {
                    $created[] = $this->createUser($row);
                }
            });
        } catch (\Throwable) {
            $this->error('สร้างผู้ใช้แบบหลายคนไม่สำเร็จ', 500);
        }

        $this->json(array_map(
            static fn(User $user): array => [
                ...$user->publicArray(),
                'roles' => $user->roleNames(),
            ],
            $created,
        ), 201, 'Users created');
    }

    public function update(Request $request, string $id): never
    {
        $user = User::findOrFail((int) $id);
        $data = $this->validate([
            'name' => 'string|max:120',
            'username' => 'string|min:3|max:60|regex:^[A-Za-z0-9._-]+$',
            'email' => 'email|max:191',
            'password' => 'string|min:6|max:255',
            'is_active' => 'boolean',
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
            $user->password = Security::hashPassword((string) $data['password']);
        }
        if (array_key_exists('is_active', $data)) {
            $user->is_active = (bool) $data['is_active'] ? 1 : 0;
        }

        if (!$user->save()) {
            $this->error('บันทึกผู้ใช้ไม่สำเร็จ', 500);
        }

        $this->json([
            ...$user->publicArray(),
            'roles' => $user->roleNames(),
        ], 200, 'User updated');
    }

    public function destroy(Request $request, string $id): never
    {
        $user = User::findOrFail((int) $id);
        if ((string) $user->id === (string) $request->userId()) {
            $this->error('ลบบัญชีตัวเองไม่ได้', 422);
        }

        if (!$user->delete()) {
            $this->error('ลบผู้ใช้ไม่สำเร็จ', 500);
        }

        $this->json(['deleted' => true], 200, 'User deleted');
    }

    public function destroyBulk(Request $request): never
    {
        $payload = $this->validate([
            'ids' => 'required|array',
        ]);

        $rawIds = $payload['ids'] ?? [];
        if (!is_array($rawIds) || $rawIds === []) {
            $this->error('ids ต้องเป็น array และไม่ว่าง', 422, ['ids' => ['ต้องมีอย่างน้อย 1 รายการ']]);
        }
        if (count($rawIds) > self::BULK_MAX) {
            $this->error('ลบได้สูงสุด ' . self::BULK_MAX . ' คนต่อครั้ง', 422, [
                'ids' => ['สูงสุด ' . self::BULK_MAX . ' รายการ'],
            ]);
        }

        $ids = [];
        foreach ($rawIds as $id) {
            $intId = (int) $id;
            if ($intId > 0) {
                $ids[$intId] = $intId;
            }
        }
        $ids = array_values($ids);
        if ($ids === []) {
            $this->error('ids ไม่ถูกต้อง', 422, ['ids' => ['ต้องเป็นตัวเลข']]);
        }

        $selfId = (int) ($request->userId() ?? 0);
        $deleted = [];
        $skipped = [];

        foreach ($ids as $id) {
            if ($id === $selfId) {
                $skipped[] = ['id' => $id, 'reason' => 'ลบบัญชีตัวเองไม่ได้'];
                continue;
            }
            $user = User::find($id);
            if ($user === null) {
                $skipped[] = ['id' => $id, 'reason' => 'ไม่พบผู้ใช้'];
                continue;
            }
            if (!$user->delete()) {
                $skipped[] = ['id' => $id, 'reason' => 'ลบไม่สำเร็จ'];
                continue;
            }
            $deleted[] = $id;
        }

        $this->json([
            'deleted_ids' => $deleted,
            'skipped' => $skipped,
            'deleted_count' => count($deleted),
        ], 200, 'Users deleted');
    }

    public function syncRoles(Request $request, string $id): never
    {
        $user = User::findOrFail((int) $id);
        $data = $this->validate([
            'roles' => 'required|array',
        ]);

        $roles = is_array($data['roles']) ? array_map('strval', $data['roles']) : [];
        $user->syncRoles($roles);

        $this->json([
            ...$user->publicArray(),
            'roles' => $user->roleNames(),
        ], 200, 'Roles assigned');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createUser(array $data): User
    {
        if (User::where('username', (string) $data['username']) !== null) {
            $this->error('ชื่อผู้ใช้นี้ถูกใช้แล้ว', 422, ['username' => ['ชื่อผู้ใช้นี้ถูกใช้แล้ว']]);
        }
        if (User::where('email', (string) $data['email']) !== null) {
            $this->error('อีเมลนี้ถูกใช้แล้ว', 422, ['email' => ['อีเมลนี้ถูกใช้แล้ว']]);
        }

        $user = User::create([
            'name' => (string) $data['name'],
            'username' => (string) $data['username'],
            'email' => (string) $data['email'],
            'password' => Security::hashPassword((string) $data['password']),
            'is_active' => array_key_exists('is_active', $data) ? ((bool) $data['is_active'] ? 1 : 0) : 1,
        ]);
        if ($user === null) {
            $this->error('สร้างผู้ใช้ไม่สำเร็จ', 500);
        }

        $roles = $data['roles'] ?? ['user'];
        if (!is_array($roles) || $roles === []) {
            $roles = ['user'];
        }
        $user->syncRoles(array_map('strval', $roles));

        return $user;
    }
}
