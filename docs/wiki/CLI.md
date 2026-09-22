# CLI (`php deawx`)

รันจากรากโปรเจกต์

```bash
php deawx
php deawx list
```

ก่อนทุกคำสั่งจะแสดงโลโก้ CyberRestAPI (ยกเว้น `-q` / `--quiet`) — เคลียร์จอเฉพาะตอน `list` / `help` / `--version`

## คีย์ความปลอดภัย

| คำสั่ง | ความหมาย |
| --- | --- |
| `php deawx key:generate` | สร้าง `ENCRYPTION_KEY` และ `JWT_SECRET` ลง `.env` ถ้ายังว่าง |
| `php deawx key:generate --force` | สุ่มคีย์ใหม่ทับของเดิม |
| `php deawx key:generate --show` | แสดงคีย์ที่สุ่ม โดยไม่เขียนไฟล์ |

ถ้ายังไม่มี `.env` คำสั่งนี้จะคัดลอกจาก `.env.example` ให้ก่อน  
หลัง `composer create-project` ระบบรัน `key:generate` ให้อัตโนมัติ

## ติดตั้งฐานข้อมูล

| คำสั่ง | ความหมาย |
| --- | --- |
| `php deawx install` | ถาม/อ่าน `DB_*` ทดสอบเชื่อมต่อ สร้างฐานถ้ายังไม่มี แล้ว migrate |
| `php deawx install -y` | ไม่ถาม — อ่าน `.env` แล้ว migrate + seed (ไม่ลบตาราง) |
| `php deawx install --seed` | เหมือน `install` แต่บังคับ seed |
| `php deawx install -n` | Symfony: ไม่ถาม (อ่าน `.env`) แต่**ไม่** seed เอง ถ้าต้องการ seed ใส่ `--seed` หรือใช้ `-y` |
| `php deawx db:create` | สร้างฐานตาม `DB_NAME` ถ้ายังไม่มี |

`install` / `migrate` / `db:create` ใช้ได้กับ **MySQL และ MariaDB เท่านั้น** (ดู [[Migration]])

ตอนถาม `DB_PASSWORD` ระบบ**ไม่โชว์รหัสผ่าน**บนจอ (ซ่อนการพิมพ์) — ถ้ามีค่าใน `.env` แล้ว กด Enter เพื่อใช้ค่าเดิม

`install` โฟกัส DB (คีย์สร้างตอน create-project แล้ว)
ถ้าต่อ DB ไม่ได้จะ**ไม่ migrate** — โหมดโต้ตอบให้แก้ค่าแล้วลองใหม่  
ถ้าฐานมีตารางแล้วจะถาม `migrate:fresh` (ค่าเริ่มต้น **no**) — กด `n` แล้ว**หยุดทันที** ไม่ migrate  
โหมด `-y` / `-n` ไม่ถาม fresh — รัน `migrate` ปกติเท่านั้น ไม่ลบตาราง

## สร้างไฟล์

| คำสั่ง | ผลลัพธ์ |
| --- | --- |
| `php deawx make:controller User` | `Apps/Controllers/UserController.php` |
| `php deawx make:controller User --model` | สร้าง controller พร้อม model |
| `php deawx make:model User` | `Apps/Models/User.php` |
| `php deawx make:model User --controller` | สร้าง model พร้อม controller |
| `php deawx make:migration create_users_table` | ไฟล์ใน `Database/migrations/` |
| `php deawx make:seeder UserSeeder` | `Database/Seeders/UserSeeder.php` |

ใช้ `--force` หรือ `-f` ถ้าต้องการทับไฟล์เดิม

ชื่อคลาสถูกแปลงเป็น PascalCase อัตโนมัติ อักขระพิเศษถูกตัดทิ้ง

## ฐานข้อมูล

| คำสั่ง | ความหมาย |
| --- | --- |
| `php deawx migrate` | รันไฟล์ที่ยังไม่เคยรัน (ชุดเดียวกันเป็น batch เดียว) |
| `php deawx migrate --step` | รันทีละไฟล์ คนละ batch (ย้อนทีละตารางง่าย) |
| `php deawx migrate --path=create_users_table` | รันเฉพาะไฟล์ที่ตรงชื่อ |
| `php deawx migrate:rollback` | ย้อนชุด batch ล่าสุด |
| `php deawx migrate:rollback --step=5` | ย้อน 5 ไฟล์ล่าสุด ข้าม batch ได้ |
| `php deawx migrate:rollback --batch=3` | ย้อนเฉพาะ batch ที่ระบุ |
| `php deawx migrate:rollback --path=users` | ย้อนเฉพาะไฟล์ตาราง users |
| `php deawx migrate:reset` | ย้อนทุกไฟล์ที่รันแล้ว |
| `php deawx migrate:refresh` | reset แล้ว migrate ใหม่ |
| `php deawx migrate:refresh --step=2` | ย้อน 2 ไฟล์ล่าสุด แล้ว migrate ใหม่ |
| `php deawx migrate:refresh --path=users` | ย้อนแล้วรันใหม่เฉพาะไฟล์นั้น |
| `php deawx migrate:refresh --seed` | refresh แล้วตามด้วย seeder |
| `php deawx migrate:status` | ดูว่ารันแล้วหรือยัง |
| `php deawx migrate:fresh` | ลบตารางทั้งหมด แล้ว migrate ใหม่ |
| `php deawx migrate:fresh --seed` | fresh แล้วตามด้วย seeder |
| `php deawx db:seed` | รัน `DatabaseSeeder` |
| `php deawx db:seed --class=UserSeeder` | รัน seeder ที่ระบุ |
| `php deawx db:create` | สร้างฐานข้อมูลตาม `DB_NAME` |
| `php deawx db:tables` | รายชื่อตารางและจำนวนแถว |
| `php deawx db:columns users` | รายชื่อฟิลด์ของตาราง |

`migrate:fresh` ดรอปทุกตารางในฐาน ใช้ตอนพัฒนา อย่าใช้กับฐานที่มีข้อมูลจริง

`migrate:refresh` ย้อนเฉพาะไฟล์ที่เฟรมเวิร์กรันไว้ แล้วสร้างตาม migration ใหม่ ไม่ลบตารางที่ไม่มีไฟล์ใน `Database/migrations/`

ถ้าใส่ทั้ง `--step` และ `--batch` จะใช้ `--step` ก่อน (เหมือน Laravel)

`--path` รับชื่อไฟล์เต็ม, ชื่อสั้นอย่าง `create_users_table`, ชื่อตารางอย่าง `users` หรือโฟลเดอร์ `Database/migrations`  
ถ้าใส่ `--path` อย่างเดียว (ไม่ใส่ `--step` / `--batch`) จะย้อนไฟล์ที่ตรงนั้นแม้ไม่ได้อยู่ batch ล่าสุด

## Composer

| คำสั่ง | ความหมาย |
| --- | --- |
| `composer serve` | PHP built-in server ที่พอร์ต 8000 |
| `composer test` | PHPUnit |
| `composer cs-fix` | จัดโค้ด PER-CS 3.0 |
| `composer cs-check` | ตรวจสไตล์โดยไม่แก้ไฟล์ |
