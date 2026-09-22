# Routing

กำหนดเส้นทางใน `Routes/web.php`

## HTTP method

```php
use Core\Route;
use Core\Response;

Route::get('/users', 'UserController@index');
Route::post('/users', 'UserController@store');
Route::put('/users/{id}', 'UserController@update');
Route::patch('/users/{id}', 'UserController@patch');
Route::delete('/users/{id}', 'UserController@destroy');
Route::options('/users', fn () => Response::json([]));
Route::any('/ping', fn () => Response::json(['pong' => true]));
```

ค่า controller เป็นสตริง `ชื่อคลาส@เมธอด` ระบบเติม namespace `App\Controllers\` ให้อัตโนมัติ  
ใช้ Closure ได้เช่นกัน

`{id}` ถูกส่งเป็นอาร์กิวเมนต์ถัดจาก `Request`

```php
public function update(Request $request, string $id): never
{
    $this->json(['id' => $id]);
}
```

## Group, JWT และสิทธิ์

```php
use Core\Middleware\JwtAuth;
use Core\Route;

Route::group('/api', ['middleware' => JwtAuth::class], function () {
    Route::get('/profile', 'ProfileController@show');

    Route::group('/admin', ['role' => 'admin'], function () {
        Route::get('/users', 'Admin\\UserController@index');
        Route::delete('/users/{id}', 'Admin\\UserController@destroy');
    });

    Route::group('/mod', ['roles' => ['admin', 'moderator']], function () {
        Route::get('/reports', 'Mod\\ReportController@index');
    });
});
```

หรือใส่ทีละเส้นทาง

```php
Route::get('/profile', 'ProfileController@show', ['middleware' => JwtAuth::class]);
Route::get('/admin/stats', 'Admin\\StatsController@index', ['role' => 'admin']);
```

พฤติกรรม

| options | ความหมาย |
| --- | --- |
| `middleware => JwtAuth::class` | ต้องมี Bearer JWT ที่ถูกต้อง |
| `role => 'admin'` | ต้องล็อกอินและมี `role=admin` ใน JWT |
| `roles => ['admin', 'moderator']` | มี role ใด role หนึ่งในรายการ |

ถ้าใส่ `role` / `roles` ระบบจะแนบ `JwtAuth` และ `RequireRole` ให้อัตโนมัติ  
ไม่ผ่านตัวตน → `401`  
ไม่ผ่านสิทธิ์ → `403`

ใน controller อ่านจาก request ชุดเดียวกับ middleware

```php
$request->userId();
$request->role();
$request->jwt();
$this->request->hasRole('admin');
```

`hasRole()` ตรวจทั้ง claim `role` และรายการ `roles` ใน JWT (starter ใส่ทั้งคู่ตอน login)

Middleware ต้องมีเมธอด `handle(Request $request, callable $next): void`

## เส้นทางที่มีมาให้

| Path | ความหมาย |
| --- | --- |
| `GET /` | JSON ต้อนรับ |
| `GET /health` | ต่อฐานข้อมูลได้หรือไม่ |
| `GET /testview` | หน้า View ตัวอย่าง |
| `/api/auth/*`, `/api/profile`, `/api/admin/*` | Auth starter (RBAC) — รายการครบ + ตัวอย่าง curl ดู [[Auth Starter]] |
