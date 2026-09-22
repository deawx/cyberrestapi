# Medoo

CyberRestAPI ใช้ [Medoo](https://medoo.in/) เป็นชั้น query  
หน้านี้สรุปวิธีเริ่มต้นจากเอกสารต้นทาง: [Getting Started (medoo.in/api/new)](https://medoo.in/api/new)

เอกสารครบชุด: [https://medoo.in/doc](https://medoo.in/doc)

## ความสัมพันธ์กับโปรเจกต์นี้

| ชั้น | สิ่งที่ใช้ |
| --- | --- |
| Query (`select` / `insert` / `update` / `delete`) | Medoo ผ่าน `Core\Database` |
| Migration / `Schema` / `Blueprint` / `install` | **เฉพาะ MySQL และ MariaDB** — ดู [[Migration]] |

โปรเจกต์นี้ตั้งค่า Medoo จาก `.env` ให้แล้ว ไม่ต้อง `new Medoo([...])` เองในโค้ดแอปทั่วไป

```php
$db = \Core\Database::getInstance()->getConnection(); // ได้ Medoo\Medoo
$db->select('users', '*', ['is_active' => 1]);
```

หรือใช้ [[Models]] ที่ห่อ Medoo ไว้

## ความต้องการ (จากต้นทาง Medoo)

- PHP 7.3+ พร้อม PDO (โปรเจกต์นี้ใช้ PHP 8.2–8.4)
- มี SQL database และติดตั้ง PDO driver ให้ตรงเอนจิน

| ฐานข้อมูล | PDO extension |
| --- | --- |
| MySQL, MariaDB | `pdo_mysql` |
| MSSQL | `pdo_sqlsrv` / `pdo_dblib` |
| Oracle | `pdo_oci` |
| SQLite | `pdo_sqlite` |
| PostgreSQL | `pdo_pgsql` |
| Sybase | `pdo_dblib` |

ที่มา: [Medoo Getting Started](https://medoo.in/api/new)

ในโปรเจกต์นี้ค่าเริ่มต้นและ migration ใช้ **MySQL / MariaDB** (`pdo_mysql`)  
ถ้าต่อเอนจินอื่นด้วย Medoo เอง ให้สร้างตารางนอกระบบ `php deawx migrate`

## ค่าคอนฟิกที่ Medoo รับ (ต้นทาง)

ตัวอย่างจากเอกสารต้นทาง:

```php
use Medoo\Medoo;

$database = new Medoo([
    // Required
    'type' => 'mysql',
    'host' => 'localhost',
    'database' => 'name',
    'username' => 'your_username',
    'password' => 'your_password',

    // Optional
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_general_ci',
    'port' => 3306,
    'prefix' => 'PREFIX_',
    'logging' => true,
    'error' => PDO::ERRMODE_EXCEPTION,
    'option' => [
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
    ],
    'command' => [
        'SET SQL_MODE=ANSI_QUOTES',
    ],
]);
```

แมปกับ `.env` ของเรา:

| Medoo | `.env` |
| --- | --- |
| `type` | `DB_TYPE` (`mysql` / `mariadb`) |
| `host` | `DB_HOST` |
| `port` | `DB_PORT` |
| `database` | `DB_NAME` |
| `username` / `password` | `DB_USER` / `DB_PASSWORD` |
| `charset` | `DB_CHARSET` (ค่าเริ่มต้น `utf8mb4`) |
| `collation` | `DB_COLLATION` (ค่าเริ่มต้นคู่ charset เช่น `utf8mb4_general_ci`) |
| `prefix` | `DB_PREFIX` |
| `error` | โปรเจกต์นี้ใช้ `PDO::ERRMODE_EXCEPTION` |
| `option` / `command` | ตั้งใน `Core\Database` (PDO attrs + `time_zone` / `SQL_MODE`) |

`Core\Database` ส่ง `charset` + `collation` ให้ Medoo โดยตรง ตาม [medoo.in/api/new](https://medoo.in/api/new) — Medoo จะรัน `SET NAMES … COLLATE …` เอง ไม่ต้องใส่ `MYSQL_ATTR_INIT_COMMAND` ซ้ำ

รายละเอียดคีย์ดู [[Configuration]]

## เอนจินอื่น (จากต้นทาง Medoo)

Medoo รองรับหลายเอนจิน — สรุปตาม [medoo.in/api/new](https://medoo.in/api/new):

### MariaDB

ใช้เหมือน MySQL — ใส่ `'type' => 'mariadb'` (Medoo แมปเป็นพฤติกรรม mysql)

### SQLite

```php
// ไฟล์
new Medoo(['type' => 'sqlite', 'database' => 'my/database/path/database.db']);

// memory
new Medoo(['type' => 'sqlite', 'database' => ':memory:']);
```

### PostgreSQL / MSSQL / Oracle / Sybase

ตั้ง `type` ตามเอนจิน และเปิด PDO driver ให้ครบ  
ดูตัวอย่างเต็มในหน้าต้นทาง

**ใน CyberRestAPI:** ชั้น migration ยังไม่รองรับเอนจินเหล่านี้  
ถ้าต้องการใช้ ให้สร้าง schema เอง แล้วเรียก Medoo ผ่านการตั้ง `DB_TYPE` / DSN ที่เข้ากับ Medoo (อาจต้องปรับ `Core\Database` ให้รับคีย์ของเอนจินนั้น)

## Custom DSN / PDO พร้อมใช้ (ต้นทาง)

Medoo รองรับ DSN เองหรือส่ง PDO ที่ต่อไว้แล้ว:

```php
// DSN กำหนดเอง
new Medoo([
    'dsn' => [
        'driver' => 'mydb',
        'server' => '12.23.34.45',
        'port' => '8886',
    ],
    'type' => 'mysql',
    'username' => 'your_username',
    'password' => 'your_password',
]);

// จาก PDO ที่มีอยู่
$pdo = new PDO('mysql:dbname=test;host=127.0.0.1', 'user', 'password');
new Medoo([
    'pdo' => $pdo,
    'type' => 'mysql',
]);
```

ที่มา: [Medoo Getting Started](https://medoo.in/api/new)

## Query พื้นฐาน

```php
$db = \Core\Database::getInstance()->getConnection();

// select
$db->select('users', ['id', 'email'], ['is_active' => 1]);

// get แถวเดียว
$db->get('users', '*', ['id' => 1]);

// insert
$db->insert('users', ['name' => 'Deawx', 'email' => 'a@example.com']);
$id = $db->id();

// update / delete
$db->update('users', ['name' => 'ใหม่'], ['id' => $id]);
$db->delete('users', ['id' => $id]);
```

ไวยากรณ์ WHERE / join / raw ดูที่เอกสาร Medoo:

- [where](https://medoo.in/api/where)
- [select](https://medoo.in/api/select)
- [insert](https://medoo.in/api/insert)
- [update](https://medoo.in/api/update)
- [delete](https://medoo.in/api/delete)
- [create](https://medoo.in/api/create) — Medoo มี `create`/`drop` ของตัวเอง แต่ในโปรเจกต์นี้แนะนำใช้ [[Migration]] (`Schema` / `Blueprint`) สำหรับ MySQL/MariaDB

## Debug (ต้นทาง)

```php
$db->info();          // รวม dsn
$db->last();          // SQL ล่าสุด
$db->log();           // ถ้าเปิด logging
```

รายละเอียด: [info](https://medoo.in/api/info) · [last](https://medoo.in/api/last) · [log](https://medoo.in/api/log)

## อ้างอิงต้นทาง

- Getting Started: [https://medoo.in/api/new](https://medoo.in/api/new)
- Documentation index: [https://medoo.in/doc](https://medoo.in/doc)
- Packagist: `catfan/medoo`
