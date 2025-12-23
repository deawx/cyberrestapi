<?php

declare(strict_types=1);

use Core\Route;
use Core\Response;
use Core\View;
// use Core\Route;

// Route::get('/', function () {
//     Response::json(['message' => 'Welcome to CyberApp API Core']);
// });




Route::get('/', fn() => Response::json([
    'message' => 'Welcome to CyberApp API Core',
    'version' => '1.0.0',
    'time' => date('Y-m-d H:i:s')
]));

Route::get('/testview', function () {
    View::render('index', [
        'appname' => 'CyberAPP Rest Api Core',
        'title' => 'CyberAPP',
        'description' => 'A Fast & Simple Rest Api Framework for PHP Developers',
        'author' => 'Deawx',
        'version' => '1.0.0',
        'license' => 'MIT'
    ], true);
});

Route::get('/health', fn() => Response::json(['status' => 'OK', 'db_connected' => true]));
