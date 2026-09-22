# ความปลอดภัย

## JWT

JWT อยู่ที่ `Core\Jwt` (แยกจาก `Security`)

**มือถือ / native app และ Pure API ไม่ใช้ cookie สำหรับ JWT**  
ส่ง `Authorization: Bearer <access_token>` เท่านั้น  
อย่าเก็บโทเคนในที่ที่ XSS แตะได้ (เช่น `localStorage` / `sessionStorage` ในหน้าเว็บ)  
บนมือถือเก็บใน secure storage ของแพลตฟอร์ม (Keychain / Keystore)  
อย่าใส่ access/refresh token ลง `document.cookie` แบบไม่มี HttpOnly

เว็บแอดมิน / SPA ที่ใช้ PHP Session ให้พึ่ง cookie ของ `Core\Session` (HttpOnly + Secure + SameSite) ไม่ต้องพึ่ง JWT ใน JS

### ออกโทเคนตอน login

```php
$tokens = \Core\Jwt::issue([
    'sub' => (string) $user->id,
    'username' => $user->username,
    'role' => $user->role, // เช่น admin, moderator, member
]);
// access_token, refresh_token, token_type, expires_in
```

หรือออกเฉพาะ access

```php
$token = \Core\Jwt::create([
    'sub' => (string) $user->id,
    'role' => $user->role,
], expiry: 900);
```

ค่าใน `.env`

| คีย์ | ความหมาย | ค่าเริ่มต้น |
| --- | --- | --- |
| `JWT_SECRET` | ความลับเซ็นโทเคน (≥ 32 ตัวอักษร) | — |
| `JWT_ACCESS_TTL` | อายุ access เป็นวินาที | `900` (15 นาที) |
| `JWT_REFRESH_TTL` | อายุ refresh เป็นวินาที | `604800` (7 วัน) |

สร้าง secret ด้วย `php deawx key:generate`

### Refresh

```php
$tokens = \Core\Jwt::refresh($refreshToken);
if ($tokens === null) {
    // refresh หมดอายุ ถูก revoke หรือใช้ซ้ำแล้ว
}
```

ทุกครั้งที่ refresh สำเร็จ ระบบ**หมุนทั้งคู่**  
access เก่าถูก blacklist ทันที และ refresh เก่าถูกลบ ใช้ต่อไม่ได้

### Revoke / logout

```php
\Core\Jwt::revoke($accessToken); // ฆ่า access + refresh ที่ผูก access_jti เดียวกัน (ใช้ตอน logout)
\Core\Jwt::revokeRefresh($refreshToken); // ฆ่าจากฝั่ง refresh (ถ้ามีแค่ refresh)
```

API `POST /api/auth/logout` ส่งแค่ Bearer — ไม่ต้องส่ง `refresh_token` ใน body

เก็บสถานะที่ `Storage/cache/jwt/` (refresh + revoke) ไม่ต้องมีตารางในฐานข้อมูล

### ตรวจในเส้นทาง

```php
use Core\Middleware\JwtAuth;
use Core\Route;

Route::group('/api', ['middleware' => JwtAuth::class], function () {
    Route::get('/profile', 'UserController@profile');

    Route::group('/admin', ['role' => 'admin'], function () {
        Route::get('/users', 'Admin\\UserController@index');
    });
});
```

ไคลเอนต์ส่ง `Authorization: Bearer <access_token>`

`Core\Request::bearerToken()` อ่าน header จาก `getallheaders` / `$_SERVER` / `apache_request_headers` — ไม่ต้องตั้งส่งต่อใน `.htaccess`
ถ้าไม่ผ่านระบบตอบ `401`  
ถ้า role ไม่ตรงตอบ `403`  
โทเคนที่ถูก revoke แล้วก็ผ่านไม่ได้

หลังผ่าน `JwtAuth` payload ถูกแนบเข้า `Request` แล้ว

```php
$request->userId();
$request->role();
$request->jwt();
$request->hasRole('admin', 'moderator');
```

ตรวจเอง

```php
$payload = \Core\Jwt::verify($accessToken);
```

ดูรายละเอียด group สิทธิ์ที่ [[Routing]]

## Cookie (HttpOnly / Secure / SameSite)

ทำงานอัตโนมัติ ไม่ต้องตั้งใน `.env`

| พฤติกรรม | ค่าอัตโนมัติ |
| --- | --- |
| HttpOnly | เปิดเสมอ |
| SameSite | `Strict` |
| Secure | เปิดเมื่อ `APP_ENV=prod` (dev ปิดเพื่อใช้ HTTP บน XAMPP ได้) |

`Core\Cookie` เป็นตัวกลางตั้ง/ลบ cookie — `Session` ใช้ตัวนี้ตอน start/destroy ด้วย

```php
\Core\Cookie::set('notice', 'ok', ['expires' => time() + 3600]);
\Core\Cookie::forget('notice');
```

ถ้า `SameSite=None` (override) ระบบบังคับ `Secure=true`  
CORS **ไม่**กำหนด HttpOnly/Secure/SameSite — คนละชั้นกับ cookie flags

## รหัสผ่าน

```php
$hash = \Core\Security::hashPassword('secret');
\Core\Security::verifyPassword('secret', $hash);
```

ใช้ bcrypt cost 12

## เข้ารหัส (Cipher)

```php
$cipher = \Core\Cipher::fromEnv();
$hidden = $cipher->encrypt('ข้อมูลลับ');
$plain = $cipher->decrypt($hidden);
```

`ENCRYPTION_KEY` รับทั้ง hex 64 ตัว และ base64 ของ 32 ไบต์  
สร้างลง `.env` ด้วย `php deawx key:generate` หรือในโค้ดด้วย `\Core\Cipher::generateKey()`

อัลกอริทึมเริ่มต้นคือ AES-256-GCM

## CORS

`CORS_ORIGIN=*` อนุญาตทุก origin  
ตั้ง origin เฉพาะได้ใน `.env` หรือในโค้ด

```php
\Core\Cors::origins(['https://app.example.com']);
\Core\Cors::credentials(true);
```

## CSRF (หน้า HTML)

`View::render` ใส่ `$csrf_token` ให้อัตโนมัติ  
ในฟอร์มใส่ hidden field ชื่อ `csrf_token` แล้วเรียก `$this->validateCsrf()`

REST API ที่ใช้ JWT ไม่ต้องใช้ CSRF

## Session

```php
\Core\Session::start();
\Core\Session::set('user_id', 1);
\Core\Session::get('user_id');
\Core\Session::flash('notice', 'บันทึกแล้ว');
\Core\Session::destroy();
```

คีย์ถูกเติมคำนำหน้าจาก `SESSION_PREFIX`  
cookie ของ session ได้ HttpOnly + Secure + SameSite จาก `Core\Cookie`

## Header, CSP และ sanitize

ทำงานอัตโนมัติจาก `APP_ENV` ไม่ต้องตั้งใน `.env`

ทุก request ได้ `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`

| สภาพ | พฤติกรรมอัตโนมัติ |
| --- | --- |
| `APP_ENV=dev` | ไม่ส่ง CSP / HSTS (สะดวกพัฒนา) |
| `APP_ENV` อื่น เช่น `staging` | ส่ง CSP |
| `APP_ENV=prod` | ส่ง CSP + HSTS |

ปรับ directive ในโค้ดได้ถ้าต้องการ (ไม่บังคับ)

```php
\Core\Security::setCspDirective('script-src', "'self'");
\Core\Security::setCspDirective('connect-src', "'self' https://api.example.com");
```

```php
\Core\Security::sanitize($input, 'email');
\Core\Security::sanitizeArray($data);
```

View escape ด้วย `htmlspecialchars` — อย่า `echo` ข้อมูลดิบในเทมเพลต

## IP และ proxy

ค่าเริ่มต้นใช้แค่ `REMOTE_ADDR` (ปลอดภัยบน XAMPP / ไม่มี proxy)  
เปิด `TRUST_PROXIES=true` **เฉพาะ**เมื่ออยู่หลัง reverse proxy ที่เชื่อถือได้

## Override ขั้นสูง (ไม่บังคับ)

คีย์ด้านล่าง**ไม่ต้องใส่**ใน `.env` หลัง create-project  
ใส่เฉพาะเมื่อต้องการบังคับต่างจากค่าอัตโนมัติ

| คีย์ | เมื่อไหร่ถึงใส่ |
| --- | --- |
| `COOKIE_SECURE` | บังคับ `true`/`false` แทนการตาม `APP_ENV` |
| `COOKIE_SAMESITE` | เปลี่ยนจาก `Strict` เป็น `Lax` / `None` |
| `CSP_ENABLED` | บังคับเปิด CSP ตอน `dev` หรือปิดตอน staging |
| `CSP_REPORT_ONLY` | ทดลอง CSP แบบ report-only ก่อน enforce |
| `HSTS_ENABLED` | บังคับ HSTS นอก `prod` หรือปิดชั่วคราว |
| `TRUST_PROXIES` | อยู่หลัง reverse proxy ที่เชื่อถือได้ |
