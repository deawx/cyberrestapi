<p align="center">
  <a href="https://cyberthai.net" target="_blank" rel="noopener noreferrer">
    <img src="public/assets/images/cybervpn.png" alt="Cyberthai" width="220">
  </a>
</p>

<h1 align="center">CyberRestAPI</h1>

<p align="center">
  เฟรมเวิร์ก PHP 8.2–8.4 สำหรับสร้าง REST API<br>
  พัฒนาโดย
  <a href="https://cyberthai.net" target="_blank" rel="noopener noreferrer">Cyberthai</a>
  ใช้ Composer, Medoo และระบบ routing ที่กำหนด endpoint เอง
</p>

<p align="center">
  <a href="https://packagist.org/packages/deawx/cyberrestapi" target="_blank" rel="noopener noreferrer"><img src="https://img.shields.io/packagist/v/deawx/cyberrestapi.svg" alt="Packagist Version"></a>
  <a href="https://packagist.org/packages/deawx/cyberrestapi" target="_blank" rel="noopener noreferrer"><img src="https://img.shields.io/packagist/dt/deawx/cyberrestapi.svg" alt="Total Downloads"></a>
  <a href="https://github.com/deawx/cyberrestapi/wiki" target="_blank" rel="noopener noreferrer"><img src="https://img.shields.io/badge/docs-wiki-blue.svg" alt="GitHub Wiki"></a>
  <a href="https://github.com/deawx/cyberrestapi" target="_blank" rel="noopener noreferrer"><img src="https://img.shields.io/github/license/deawx/cyberrestapi.svg" alt="License"></a>
</p>

## คุณสมบัติ

- Routing คล้าย Laravel (`GET` / `POST` / `PUT` / `PATCH` / `DELETE`)
- JSON response มาตรฐานผ่าน `Core\Response`
- ฐานข้อมูล MySQL / MariaDB ผ่าน Medoo (`Core\Database`, `Core\Model`)
- Migration / Schema / `install` **รองรับเฉพาะ MySQL และ MariaDB** ในเวอร์ชันนี้
- CORS จาก `CORS_ORIGIN` ใน `.env`
- JWT (`firebase/php-jwt`) และเข้ารหัสข้อมูลด้วย `Core\Cipher`
- CLI `php deawx` สำหรับสร้าง controller, model, migration และ seeder
- Migration / seeder, validation, pagination และอัปโหลดไฟล์
- จัดรูปแบบโค้ดตาม <a href="https://www.php-fig.org/per/coding-style/" target="_blank" rel="noopener noreferrer">PER Coding Style 3.0</a>

## ความต้องการของระบบ

- PHP 8.2–8.4 พร้อมส่วนขยาย `curl`, `json`, `mbstring`, `openssl`, `pdo_mysql`
- Composer 2
- MySQL หรือ MariaDB (migration / `php deawx install` รองรับเฉพาะสองตัวนี้)
- Apache (เช่น XAMPP) หรือ PHP built-in server

## ติดตั้ง

แพ็กเกจบน Packagist คือ
<a href="https://packagist.org/packages/deawx/cyberrestapi" target="_blank" rel="noopener noreferrer"><code>deawx/cyberrestapi</code></a>
ซอร์สอยู่ที่
<a href="https://github.com/deawx/cyberrestapi" target="_blank" rel="noopener noreferrer">github.com/deawx/cyberrestapi</a>

สร้างโปรเจกต์ใหม่จาก Composer:

```bash
composer create-project deawx/cyberrestapi myapp
cd myapp
```

หรือติดตั้งเป็นแพ็กเกจในโปรเจกต์ที่มีอยู่แล้ว:

```bash
composer require deawx/cyberrestapi
```

`create-project` จะสร้าง `.env` แล้วใส่ `ENCRYPTION_KEY` กับ `JWT_SECRET` ให้  
ถ้าติดตั้งแบบคัดลอกโฟลเดอร์เอง:

```bash
copy .env.example .env
php deawx key:generate
```

แก้ `.env` ให้ `DB_*` ตรงกับ MySQL หรือรันตัวช่วยติดตั้งฐานข้อมูล:

```bash
php deawx install          # ถามค่า DB แล้ว migrate (ถาม seed)
php deawx install -y       # ไม่ถาม — อ่าน .env แล้ว migrate + seed
```

`-y` / `--yes` = ไม่ถามคำถาม อ่าน `DB_*` จาก `.env` สร้างฐานถ้ายังไม่มี แล้ว migrate + seed (ไม่ลบตารางที่มีอยู่)  
เอกสารเพิ่มเติม: [Getting Started](docs/wiki/Getting-Started.md) · [CLI](docs/wiki/CLI.md) · [Auth Starter](docs/wiki/Auth-Starter.md)

อย่า commit ไฟล์ `.env` — เก็บเฉพาะ `.env.example` ใน git

## รันโปรเจกต์

### Apache (XAMPP)

วางโฟลเดอร์โปรเจกต์ใน `htdocs` ของ XAMPP แล้วเปิดตามชื่อโฟลเดอร์ เช่น `myapp`

- <a href="http://localhost/myapp/" target="_blank" rel="noopener noreferrer">http://localhost/myapp/</a>
- <a href="http://localhost/myapp/health" target="_blank" rel="noopener noreferrer">http://localhost/myapp/health</a>
- <a href="http://localhost/myapp/testview" target="_blank" rel="noopener noreferrer">http://localhost/myapp/testview</a>

### PHP built-in server

```bash
composer serve
```

- <a href="http://127.0.0.1:8000" target="_blank" rel="noopener noreferrer">http://127.0.0.1:8000</a>
- <a href="http://127.0.0.1:8000/health" target="_blank" rel="noopener noreferrer">http://127.0.0.1:8000/health</a>
- <a href="http://127.0.0.1:8000/testview" target="_blank" rel="noopener noreferrer">http://127.0.0.1:8000/testview</a>

| Path | คำอธิบาย |
| --- | --- |
| `/` | JSON ต้อนรับ |
| `/health` | สถานะ API และฐานข้อมูล |
| `/testview` | หน้า View ตัวอย่าง |

## เพิ่ม API ของตัวเอง

สร้างคลาสด้วย CLI แล้วผูกเส้นทางใน `Routes/web.php`

```bash
php deawx make:controller User
php deawx make:model User
php deawx make:migration create_users_table
php deawx migrate
php deawx migrate --step
php deawx migrate:status
php deawx migrate:rollback --path=users
php deawx migrate:refresh --seed
php deawx migrate:fresh --seed
php deawx db:tables
php deawx db:columns users
php deawx make:seeder UserSeeder
php deawx db:seed
```

ไฟล์จะอยู่ที่ `Apps/Controllers/`, `Apps/Models/`, `Database/migrations/` และ `Database/Seeders/`

สร้างตารางด้วย Blueprint (ข้างในเรียก Medoo `create` / `drop` ให้) แก้ตารางเดิมใช้ `Schema::table()`

```php
Schema::create('users', static function (Blueprint $table): void {
    $table->id();
    $table->string('email', 191)->unique();
    $table->string('name');
    $table->timestamps();
});
```

Seeder หรือ query พิเศษใช้ `$this->db()` ของ Medoo ได้ เช่น `insert`, `update`, `select`

ตัวอย่างใน controller

```php
$validated = $this->validate([
    'email' => 'required|email|max:191',
    'name' => 'required|min:2',
]);

$this->paginate(App\Models\User::class);

$avatar = Core\Upload::store('avatar', 'uploads/avatars');
```

ตัวอย่างเส้นทางใน `Routes/web.php`

```php
use Core\Route;

Route::get('/users', 'UserController@index');
```

## สคริปต์ Composer

| คำสั่ง | หน้าที่ |
| --- | --- |
| `composer serve` | เปิด PHP built-in server ที่พอร์ต 8000 |
| `composer test` | รัน PHPUnit |
| `composer cs-fix` | จัดโค้ดตาม PER Coding Style 3.0 |
| `composer cs-check` | ตรวจสไตล์โดยไม่แก้ไฟล์ |

## เอกสารประกอบ

คู่มือเต็มอยู่ที่
<a href="https://github.com/deawx/cyberrestapi/wiki" target="_blank" rel="noopener noreferrer">GitHub Wiki</a>
สำเนาสำหรับคัดลอกขึ้น wiki อยู่ใน `docs/wiki/`

- <a href="https://github.com/deawx/cyberrestapi/wiki" target="_blank" rel="noopener noreferrer">Home</a>
- <a href="https://github.com/deawx/cyberrestapi/wiki/Getting-Started" target="_blank" rel="noopener noreferrer">เริ่มต้นใช้งาน</a>
- <a href="https://github.com/deawx/cyberrestapi/wiki/Configuration" target="_blank" rel="noopener noreferrer">ค่าคอนฟิก</a>
- <a href="https://github.com/deawx/cyberrestapi/wiki/CLI" target="_blank" rel="noopener noreferrer">CLI</a>
- <a href="https://github.com/deawx/cyberrestapi/wiki/Routing" target="_blank" rel="noopener noreferrer">Routing</a>
- <a href="https://github.com/deawx/cyberrestapi/wiki/Controllers" target="_blank" rel="noopener noreferrer">Controllers</a>
- <a href="https://github.com/deawx/cyberrestapi/wiki/Validation" target="_blank" rel="noopener noreferrer">Validation</a>
- <a href="https://github.com/deawx/cyberrestapi/wiki/Models" target="_blank" rel="noopener noreferrer">Models</a>
- <a href="https://github.com/deawx/cyberrestapi/wiki/Pagination" target="_blank" rel="noopener noreferrer">Pagination</a>
- <a href="https://github.com/deawx/cyberrestapi/wiki/Upload" target="_blank" rel="noopener noreferrer">Upload</a>
- <a href="https://github.com/deawx/cyberrestapi/wiki/Migration" target="_blank" rel="noopener noreferrer">Migration</a>
- <a href="https://github.com/deawx/cyberrestapi/wiki/Seeder" target="_blank" rel="noopener noreferrer">Seeder</a>
- <a href="https://github.com/deawx/cyberrestapi/wiki/Security" target="_blank" rel="noopener noreferrer">Security</a>
- <a href="https://github.com/deawx/cyberrestapi/wiki/Views" target="_blank" rel="noopener noreferrer">Views</a>
- <a href="https://github.com/deawx/cyberrestapi/wiki/Logging" target="_blank" rel="noopener noreferrer">Logging</a>
- <a href="https://github.com/deawx/cyberrestapi/wiki/Testing" target="_blank" rel="noopener noreferrer">Testing</a>

## สัญญาอนุญาต

เผยแพร่ภายใต้สัญญา
<a href="LICENSE" target="_blank" rel="noopener noreferrer">MIT License</a>

## ผู้พัฒนา

CyberRestAPI พัฒนาโดย **deawx** (Tirapong Chaiyakun)

- **เว็บไซต์**: <a href="https://cyberthai.net" target="_blank" rel="noopener noreferrer">cyberthai.net</a>
- **Email**: <a href="mailto:msdos43@gmail.com" target="_blank" rel="noopener noreferrer">msdos43@gmail.com</a>
- **Facebook**: <a href="https://www.facebook.com/groups/1546709029145796" target="_blank" rel="noopener noreferrer">@Cyberthai</a>
- **Line**: <a href="https://line.me/ti/p/~deawx" target="_blank" rel="noopener noreferrer">deawx</a>
