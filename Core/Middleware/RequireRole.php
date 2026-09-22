<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Middleware\RequireRole
 *      ตรวจ role จาก JWT บน Request
 *      ใช้คู่กับ options role / roles ของ Route
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core\Middleware;

use Core\Request;
use Core\Response;

final class RequireRole
{
    public function handle(Request $request, callable $next): void
    {
        if ($request->jwt() === null) {
            Response::error('Unauthorized', 401);
        }

        $roles = $request->getAttribute('required_roles', []);
        if (!is_array($roles) || $roles === []) {
            Response::error('Forbidden', 403);
        }

        $allowed = [];
        foreach ($roles as $role) {
            if (is_string($role) && $role !== '') {
                $allowed[] = $role;
            }
        }

        if ($allowed === [] || !$request->hasRole(...$allowed)) {
            Response::error('Forbidden', 403);
        }

        $next();
    }
}
