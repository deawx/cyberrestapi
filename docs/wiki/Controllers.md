# Controller, Request, Response

## สร้าง controller

```bash
php deawx make:controller User
```

```php
namespace App\Controllers;

use Core\Controller;
use Core\Request;

final class UserController extends Controller
{
    public function index(Request $request): never
    {
        $this->json(['message' => 'UserController']);
    }
}
```

จากนั้นผูกใน `Routes/web.php` เป็น `UserController@index`

## Helper ใน Controller

| เมธอด | ความหมาย |
| --- | --- |
| `$this->json($data, $status, $message)` | JSON สำเร็จ |
| `$this->error($message, $status, $errors)` | JSON error |
| `$this->validate($rules)` | ตรวจ input ถ้าไม่ผ่านออก `422` |
| `$this->paginate(User::class, $where)` | ตัดหน้าแล้วตอบ JSON |
| `$this->abort(404)` | หยุดด้วยสถานะนั้น |
| `$this->view('index', $data)` | เรนเดอร์ HTML |
| `$this->redirect($url)` | เปลี่ยนหน้า |
| `$this->validateCsrf()` | ตรวจ CSRF (หน้าเว็บที่มี session) |

`$this->request` คือ `Core\Request` ของคำขอปัจจุบัน

## Request

```php
$request->method();
$request->uri();
$request->input('email');
$request->all();
$request->only(['email', 'name']);
$request->has('email');
$request->header('Accept');
$request->bearerToken();
$request->ip();
$request->file('avatar');
$request->rawBody();
```

ลำดับค่าของ `input()` คือ JSON body แล้ว POST แล้ว GET

## Response

รูปแบบสำเร็จ

```json
{
  "status": "success",
  "message": "Success",
  "data": {},
  "timestamp": "2026-09-20 04:00:00"
}
```

รูปแบบ error

```json
{
  "status": "error",
  "message": "Validation failed",
  "errors": {},
  "timestamp": "2026-09-20 04:00:00"
}
```

```php
Response::json($data, 200, 'Success');
Response::error('Not found', 404);
Response::paginate($items, $total, $page, $perPage);
```

เมื่อ `APP_DEBUG=true` หรือ `APP_ENV=dev` JSON จะจัดบรรทัด และ error 500 อาจมี trace
