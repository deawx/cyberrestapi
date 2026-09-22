<?php

declare(strict_types=1);

use Core\Route;
use Core\Response;
use Core\View;
use Core\Database;
use Core\Middleware\JwtAuth;

Route::get('/', fn() => Response::json([
    'message' => 'Welcome to CyberApp API Core',
    'version' => $_ENV['APP_VERSION'] ?? '1.0.0',
    'time' => date('Y-m-d H:i:s'),
]));

Route::get('/testview', function () {
    View::render('index', [
        'appname' => 'CyberAPP Rest Api Core',
        'title' => 'CyberAPP',
        'description' => 'A Fast & Simple Rest Api Framework for PHP Developers',
        'author' => 'Deawx',
        'version' => '1.0.0',
        'license' => 'MIT',
    ], layout: 'layouts.app');
});

Route::get('/health', function () {
    $dbConnected = false;
    try {
        $dbConnected = Database::getInstance()->isConnected();
    } catch (\Throwable) {
        $dbConnected = false;
    }

    Response::json(
        [
            'status' => $dbConnected ? 'OK' : 'DEGRADED',
            'db_connected' => $dbConnected,
        ],
        $dbConnected ? 200 : 503,
        $dbConnected ? 'Success' : 'Database unavailable',
    );
});

Route::group('/api', [], function () {
    Route::post('/auth/login', 'AuthController@login');
    Route::post('/auth/refresh', 'AuthController@refresh');

    Route::group('', ['middleware' => JwtAuth::class], function () {
        Route::post('/auth/logout', 'AuthController@logout');
        Route::get('/profile', 'ProfileController@show');
        Route::put('/profile', 'ProfileController@update');
        Route::get('/profile/permissions', 'ProfileController@permissions');
    });

    Route::group('/admin', ['role' => 'admin'], function () {
        Route::get('/users', 'Admin\\UserController@index');
        Route::post('/users', 'Admin\\UserController@store');
        Route::post('/users/bulk', 'Admin\\UserController@storeBulk');
        Route::post('/users/bulk-delete', 'Admin\\UserController@destroyBulk');
        Route::get('/users/{id}', 'Admin\\UserController@show');
        Route::put('/users/{id}', 'Admin\\UserController@update');
        Route::delete('/users/{id}', 'Admin\\UserController@destroy');
        Route::put('/users/{id}/roles', 'Admin\\UserController@syncRoles');

        Route::get('/roles', 'Admin\\RoleController@index');
        Route::post('/roles', 'Admin\\RoleController@store');
        Route::get('/roles/{id}', 'Admin\\RoleController@show');
        Route::put('/roles/{id}', 'Admin\\RoleController@update');
        Route::delete('/roles/{id}', 'Admin\\RoleController@destroy');
        Route::put('/roles/{id}/permissions', 'Admin\\RoleController@syncPermissions');

        Route::get('/permissions', 'Admin\\PermissionController@index');
        Route::post('/permissions', 'Admin\\PermissionController@store');
        Route::get('/permissions/{id}', 'Admin\\PermissionController@show');
        Route::put('/permissions/{id}', 'Admin\\PermissionController@update');
        Route::delete('/permissions/{id}', 'Admin\\PermissionController@destroy');
    });
});
