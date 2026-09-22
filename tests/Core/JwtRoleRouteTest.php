<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Middleware\JwtAuth;
use Core\Middleware\RequireRole;
use Core\Request;
use Core\Route;
use PHPUnit\Framework\TestCase;

final class JwtRoleRouteTest extends TestCase
{
    protected function tearDown(): void
    {
        Route::clear();
        parent::tearDown();
    }

    public function testRequestStoresJwtAndRole(): void
    {
        $request = new Request();
        $request->setJwt([
            'sub' => '42',
            'role' => 'admin',
        ]);

        $this->assertSame('42', $request->userId());
        $this->assertSame('admin', $request->role());
        $this->assertTrue($request->hasRole('admin'));
        $this->assertTrue($request->hasRole('admin', 'moderator'));
        $this->assertFalse($request->hasRole('member'));
    }

    public function testRoleOptionAttachesJwtAndRequireRoleMiddleware(): void
    {
        Route::group('/api', ['middleware' => JwtAuth::class], function (): void {
            Route::group('/admin', ['role' => 'admin'], function (): void {
                Route::get('/users', 'UserController@index');
            });
        });

        $routes = Route::getRoutes();
        $this->assertCount(1, $routes);

        $route = $routes[0];
        $this->assertSame('/api/admin/users', $route->getPath());
        $this->assertSame(['admin'], $route->getRequiredRoles());
        $this->assertSame(
            [JwtAuth::class, RequireRole::class],
            $route->getMiddleware(),
        );
    }

    public function testRolesOptionAcceptsMultipleRoles(): void
    {
        Route::get('/mod/queue', 'ModController@queue', [
            'roles' => ['admin', 'moderator'],
        ]);

        $route = Route::getRoutes()[0];
        $this->assertSame(['admin', 'moderator'], $route->getRequiredRoles());
        $this->assertContains(JwtAuth::class, $route->getMiddleware());
        $this->assertContains(RequireRole::class, $route->getMiddleware());
    }
}
