# Model และฐานข้อมูล

```bash
php deawx make:model User
```

```php
namespace App\Models;

use Core\Model;

final class User extends Model
{
    protected static string $table = 'users';
    protected static array $fillable = ['name', 'email', 'password'];
    protected static bool $timestamps = true;
}
```

ถ้า `$fillable` ว่าง ระบบจะไม่รับ mass assign ใด ๆ  
ฟิลด์ใน `$guarded` ถูกกันเสมอ

## เมธอดหลัก

```php
User::create(['name' => 'Deawx', 'email' => 'a@example.com']);
$user = User::find(1);
$user = User::findOrFail(1);          // ไม่เจอแล้วตอบ 404
$user = User::where('email', '=', 'a@example.com');
$users = User::all();
$users = User::getWhere(['status' => 'active']);

$user->name = 'ใหม่';
$user->save();
$user->delete();
$user->toArray();
```

`where` / `getWhere` รับเฉพาะชื่อคอลัมน์ `[A-Za-z_][A-Za-z0-9_]*` ไม่รับ operator จากผู้ใช้ตรง ๆ

ถ้า `$timestamps = true` ระบบใส่ `created_at` / `updated_at` ตอนบันทึก

## ตัดหน้า

```php
User::paginate($page, $perPage, ['status' => 'active']);
```

ดูหน้า [[Pagination]]

## ต่อฐานข้อมูลเอง (Medoo)

```php
$db = \Core\Database::getInstance()->getConnection(); // Medoo
$db->select('users', '*', ['status' => 'active']);
```

วิธีติดตั้ง/คอนฟิก/เอนจินตามเอกสารต้นทาง Medoo: [[Medoo]]  
ต้นทาง: [https://medoo.in/api/new](https://medoo.in/api/new) · เอกสารเต็ม: [https://medoo.in/doc](https://medoo.in/doc)

`Database::getInstance()->isConnected()` ใช้ใน `/health`  
`Database::reconnect()` บังคับต่อใหม่
