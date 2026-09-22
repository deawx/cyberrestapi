# Migration และ Schema

## ข้อกำหนดเอนจิน

ตอนนี้ **`Schema` / `Blueprint` / `php deawx migrate` / `install` / `db:create` รองรับเฉพาะ MySQL และ MariaDB**

| ใช้ได้ | ยังไม่รองรับในชั้น migration |
| --- | --- |
| `DB_TYPE=mysql` หรือ `mariadb` | PostgreSQL, SQLite, MSSQL, Oracle, Sybase ฯลฯ |

Medoo อาจต่อเอนจินอื่นเพื่อ `select` / `insert` ได้ ถ้าสร้างตารางเองนอกระบบ migration ของเรา — ดู [[Medoo]] และต้นทาง [medoo.in/api/new](https://medoo.in/api/new)  
อย่าใช้ `php deawx install` หรือไฟล์ใน `Database/migrations/` กับเอนจินนอก MySQL/MariaDB

ไฟล์อยู่ที่ `Database/migrations/` ชื่อแบบ `YYYYMMDDHHMMSS_create_users_table.php`

```bash
php deawx make:migration create_users_table
php deawx migrate
php deawx migrate --step
php deawx migrate --path=create_users_table
php deawx migrate:status
php deawx migrate:rollback
php deawx migrate:rollback --step=5
php deawx migrate:rollback --batch=3
php deawx migrate:rollback --path=users
php deawx migrate:reset
php deawx migrate:refresh
php deawx migrate:refresh --path=users
php deawx migrate:fresh
php deawx db:tables
php deawx db:columns users
```

ตารางที่รันแล้วถูกบันทึกใน `migrations`

ย้อนกลับใกล้ Laravel แต่ไม่ครบทุกตัวเลือกของเขา (เช่น `--pretend`, `--isolated`)

- ไม่ใส่ตัวเลือก = ย้อนทั้ง batch ล่าสุด (ไฟล์ที่รันใน `migrate` ครั้งเดียวกัน)
- `migrate --step` = แต่ละไฟล์ได้คนละ batch แล้ว `rollback` ครั้งถัดไปย้อนไฟล์เดียว
- `--step=N` = ย้อน N ไฟล์จากใหม่ไปเก่า ข้าม batch ได้
- `--batch=N` = ย้อนเฉพาะชุดนั้น
- `--path=users` หรือ `--path=create_users_table` = ย้อนเฉพาะไฟล์นั้น แม้ไม่ได้อยู่ batch ล่าสุด
- `--path` เป็นโฟลเดอร์ = กรองไฟล์ในโฟลเดอร์นั้น แล้วใช้กฎ batch แบบ Laravel
- `migrate:reset` = ย้อนทั้งหมด
- `migrate:refresh` = reset แล้ว migrate ใหม่ (ไม่ดรอปตารางอื่น)
- `migrate:fresh` = ดรอปทุกตารางแล้ว migrate ใหม่

ย้อนทีละตารางให้แยกไฟล์หนึ่งตารางต่อหนึ่ง migration แล้วใช้ `--path` หรือรันด้วย `migrate --step`

## ไฟล์ migration

```php
<?php

declare(strict_types=1);

use Core\Blueprint;
use Core\Migration;
use Core\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('email', 191)->unique();
            $table->string('password', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('users');
    }
};
```

`up()` รันตอน migrate  
`down()` รันตอน rollback

## Blueprint

### ประเภทคอลัมน์

| เมธอด | SQL |
| --- | --- |
| `id()` / `id('user_id')` | PK `BIGINT` auto increment |
| `column('geo', 'POINT')` | type อิสระ (อักขระปลอดภัยเท่านั้น) |
| `string` / `char` | `VARCHAR` / `CHAR` |
| `text` / `tinyText` / `mediumText` / `longText` | ข้อความยาว |
| `json` | `JSON` |
| `integer` / `tinyInteger` / `smallInteger` / `mediumInteger` / `bigInteger` | จำนวนเต็ม |
| `unsignedInteger` / `unsignedTinyInteger` / … / `unsignedBigInteger` | จำนวนเต็มไม่ติดลบ |
| `boolean` | `TINYINT(1)` |
| `decimal` / `float` / `double` | ทศนิยม |
| `date` / `dateTime` / `time` / `year` / `timestamp` | วันเวลา |
| `timestamps()` | `created_at` + `updated_at` |
| `softDeletes()` | `deleted_at` ว่างได้ |
| `binary` / `varbinary` / `blob` / `tinyBlob` / `mediumBlob` / `longBlob` | ไบนารี |
| `enum('status', ['a','b'])` / `set(...)` | `ENUM` / `SET` |
| `uuid` / `ulid` | `CHAR(36)` / `CHAR(26)` |
| `ipAddress` / `macAddress` | `VARCHAR(45)` / `VARCHAR(17)` |
| `rememberToken()` | `remember_token` ว่างได้ |

### ตัวปรับและดัชนี

| เมธอด | ความหมาย |
| --- | --- |
| `nullable()` | ว่างได้ |
| `unique()` | unique ที่คอลัมน์นั้น |
| `unsigned()` | เติม `UNSIGNED` ให้คอลัมน์ตัวเลขล่าสุด |
| `default('member')` | ค่าเริ่มต้น |
| `index()` / `index('status')` / `index(['a','b'])` | index |
| `uniqueIndex('email')` | unique index |
| `foreignId('user_id', 'users')` | `BIGINT` + FK ไป `users.id` |
| `foreign('user_id', 'users', 'id', 'CASCADE')` | FK เอง |
| `comment('ข้อความ')` | comment คอลัมน์ / หลัง `id()` = PK / ไม่มีคอลัมน์ค้าง = ตาราง |
| `dropColumn('bio')` | ใช้กับ `Schema::table` |

ตัวอย่าง

```php
Schema::create('users', static function (Blueprint $table): void {
    $table->comment('บัญชีผู้ใช้');
    $table->id()->comment('รหัสผู้ใช้');
    $table->string('email', 191)->unique()->comment('อีเมลล็อกอิน');
    $table->enum('status', ['active', 'banned'])->default('active');
    $table->json('meta')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

`ON DELETE` ที่รับได้: `CASCADE`, `SET NULL`, `RESTRICT`, `NO ACTION`

## Schema

| เมธอด | ความหมาย |
| --- | --- |
| `Schema::create($table, $fn)` | สร้างตาราง ถ้ามีอยู่แล้วจะโยน error |
| `Schema::drop($table)` | ลบตาราง (ปิด FK ชั่วคราว) |
| `Schema::table($table, $fn)` | เพิ่มคอลัมน์ / index / FK หรือลบคอลัมน์ |
| `Schema::hasTable($table)` | มีตารางหรือไม่ |
| `Schema::hasColumn($table, $col)` | มีคอลัมน์หรือไม่ |
| `Schema::columns($table)` | รายชื่อฟิลด์จากฐานจริง |
| `Schema::tables()` | รายชื่อตารางทั้งหมด |
| `Schema::db()` | อินสแตนซ์ Medoo |

`Schema::create` **ไม่เทียบ** คอลัมน์ใน Blueprint กับตารางจริง เช็คแค่มีตารางหรือยัง

แก้ตารางที่มีอยู่แล้ว

```php
Schema::table('users', static function (Blueprint $table): void {
    $table->string('phone', 20)->nullable();
    $table->index('phone');
});
```

## ใช้ Medoo ใน migration

ทุก migration มี `$this->db()` เป็น Medoo

```php
public function up(): void
{
    $this->db()->create('notes', [
        'id' => ['INT', 'NOT NULL', 'AUTO_INCREMENT', 'PRIMARY KEY'],
        'title' => ['VARCHAR(191)', 'NOT NULL'],
    ]);
}
```

วิธีที่แนะนำคือ Blueprint เพราะอ่านง่ายกว่า

โฟลเดอร์ `Database/migrations/` ว่างไว้ให้สร้างตารางเองด้วย `php deawx make:migration`
