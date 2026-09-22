<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use Core\Controller;
use Core\Jwt;
use Core\Request;
use Core\Security;

final class AuthController extends Controller
{
    public function login(Request $request): never
    {
        $data = $this->validate([
            'username' => 'required|string|min:3|max:60|regex:^[A-Za-z0-9._-]+$',
            'password' => 'required|string|min:6|max:255',
        ]);

        $user = User::where('username', (string) $data['username']);
        if (
            $user === null
            || (int) ($user->is_active ?? 0) !== 1
            || !Security::verifyPassword((string) $data['password'], (string) ($user->password ?? ''))
        ) {
            $this->error('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', 401);
        }

        $this->json(Jwt::issue($user->jwtClaims()), 200, 'Login success');
    }

    public function refresh(Request $request): never
    {
        $data = $this->validate([
            'refresh_token' => 'required|string|min:20',
        ]);

        $tokens = Jwt::refresh((string) $data['refresh_token']);
        if ($tokens === null) {
            $this->error('Refresh token ไม่ถูกต้องหรือหมดอายุ', 401);
        }

        $this->json($tokens, 200, 'Token refreshed');
    }

    public function logout(Request $request): never
    {
        $access = $request->bearerToken();
        if ($access === null) {
            $this->error('Unauthorized', 401);
        }

        // revoke access แล้วลบ refresh ที่ผูก jti เดียวกันอัตโนมัติ — ไม่ต้องส่ง refresh_token
        if (!Jwt::revoke($access)) {
            $this->error('Unauthorized', 401);
        }

        $this->json(['revoked' => true], 200, 'Logged out');
    }
}
