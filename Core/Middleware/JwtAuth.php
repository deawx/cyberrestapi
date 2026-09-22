<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Middleware\JwtAuth
 *      ตรวจ Authorization Bearer ผ่าน Core\Jwt
 *      แนบ payload เข้า Request แล้วเรียก $next
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core\Middleware;

use Core\Request;
use Core\Response;
use Core\Jwt;

final class JwtAuth
{
    public function handle(Request $request, callable $next): void
    {
        $token = $request->bearerToken();
        if ($token === null) {
            Response::error('Unauthorized', 401);
        }

        $payload = Jwt::verify($token);
        if ($payload === null) {
            Response::error('Unauthorized', 401);
        }

        $request->setJwt($payload);
        $next();
    }
}
