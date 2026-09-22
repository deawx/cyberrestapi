# CyberRestAPI

เฟรมเวิร์ก PHP 8.2–8.4 สำหรับสร้าง REST API พัฒนาโดย [Cyberthai](https://cyberthai.net)

แพ็กเกจ: [`deawx/cyberrestapi`](https://packagist.org/packages/deawx/cyberrestapi)  
ซอร์ส: [github.com/deawx/cyberrestapi](https://github.com/deawx/cyberrestapi)

## สารบัญ

1. [[Getting Started]] — ติดตั้งและรันโปรเจกต์
2. [[Configuration]] — ค่าใน `.env`
3. [[CLI]] — คำสั่ง `php deawx`
4. [[Routing]] — กำหนด endpoint
5. [[Controllers]] — Controller, Request, Response
6. [[Validation]] — ตรวจข้อมูลเข้า
7. [[Models]] — Model และฐานข้อมูล
8. [[Medoo]] — วิธีใช้ Medoo จากเอกสารต้นทาง
9. [[Pagination]] — ตัดหน้า
10. [[Upload]] — อัปโหลดไฟล์
11. [[Migration]] — สร้าง / แก้ / ลบตาราง (MySQL / MariaDB)
12. [[Seeder]] — ใส่ข้อมูลตัวอย่าง
13. [[Security]] — Jwt, Cookie, CSP, CORS, Cipher, CSRF
14. [[Auth Starter]] — RBAC ตัวอย่างใน Apps + **รายการ API ทั้งหมดพร้อมวิธีใช้**
15. [[Views]] — หน้า HTML และ HTTP client
16. [[Logging]] — ล็อกและ rate limit
17. [[Testing]] — PHPUnit และสไตล์โค้ด

## โครงสร้างโฟลเดอร์

| โฟลเดอร์ | หน้าที่ |
| --- | --- |
| `Core/` | เฟรมเวิร์ก |
| `Apps/Controllers/` | Controller ของแอป |
| `Apps/Models/` | Model ของแอป |
| `Routes/web.php` | เส้นทาง API |
| `Database/migrations/` | ไฟล์สร้างตาราง |
| `Database/Seeders/` | ไฟล์ใส่ข้อมูล |
| `Views/` | เทมเพลต HTML |
| `public/assets/` | CSS / JS / รูป |
| `public/uploads/` | ไฟล์ที่อัปโหลด |
| `Storage/logs/` | ล็อก |
| `Storage/cache/rate/` | ไฟล์ rate limit |

## วิธีใส่ขึ้น GitHub Wiki

คัดลอกไฟล์ใน `docs/wiki/` ไปเป็นหน้าใน wiki ของรีโป (ชื่อไฟล์ = ชื่อหน้า) หน้าแรกใช้ `Home.md`
