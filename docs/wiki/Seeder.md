# Seeder

ไฟล์อยู่ที่ `Database/Seeders/`

```bash
php deawx make:seeder UserSeeder
php deawx db:seed
php deawx db:seed --class=UserSeeder
php deawx migrate:fresh --seed
php deawx install -y
```

`install -y` จะ migrate แล้วรัน `DatabaseSeeder` ให้โดยไม่ถาม (ดู [[CLI]] · [[Auth Starter]])

## เขียน seeder

```php
namespace Database\Seeders;

use Core\Seeder;
use Faker\Factory;

final class UserSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Factory::create('th_TH');

        $this->db()->insert('users', [
            'name' => $faker->name(),
            'email' => $faker->unique()->safeEmail(),
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
```

`$this->db()` คือ Medoo ใช้ `insert`, `update`, `delete` ได้ตามเอกสาร Medoo

## DatabaseSeeder

```php
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->truncate('posts', 'users');
        $this->call(UserSeeder::class);
        $this->call(ThreadPostSeeder::class);
    }
}
```

| เมธอด | ความหมาย |
| --- | --- |
| `$this->call(UserSeeder::class)` | รัน seeder อื่น |
| `$this->truncate('users', 'posts')` | ล้างตาราง (ปิด FK ชั่วคราว) |
| `$this->db()` | Medoo |

`truncate` ข้ามตารางที่ยังไม่มี

โฟลเดอร์ `Database/Seeders/` มี starter RBAC แล้ว (`DatabaseSeeder` → `RbacSeeder`)  
ดูบัญชีทดลองและ API ที่ [[Auth Starter]]

ถ้าใช้ Faker เพิ่มเอง ให้ติดตั้งใน `require-dev` แล้วเลือก locale ตามต้องการ เช่น `th_TH`
