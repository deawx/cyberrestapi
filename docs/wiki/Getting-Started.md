# เริ่มต้นใช้งาน

## ความต้องการของระบบ

- PHP 8.2–8.4 พร้อม `curl`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `fileinfo`
- Composer 2
- MySQL หรือ MariaDB (ชั้น migration / `install` รองรับเฉพาะสองตัวนี้)
- Apache (XAMPP) หรือ PHP built-in server

## ติดตั้งจาก Packagist

```bash
composer create-project deawx/cyberrestapi myapp
cd myapp
```

`create-project` จะคัดลอก `.env` จากตัวอย่าง แล้วรัน `php deawx key:generate` ให้อัตโนมัติ  
ถ้ายังไม่มี `.env` หรือคีย์ว่าง ให้รันเอง

```bash
copy .env.example .env
php deawx key:generate
```

แก้ `DB_*` ใน `.env` หรือรันตัวช่วย

```bash
php deawx install       # ถามค่า DB แล้ว migrate (ถาม seed, default แนะนำ yes)
php deawx install -y    # ไม่ถาม — อ่าน .env แล้ว migrate + seed
php deawx db:create     # สร้างเฉพาะฐาน ไม่ migrate
```

`install` จะทดสอบเชื่อมต่อ และสร้างฐานถ้ายังไม่มี  
`-y` / `--yes` ใช้ตอน CI หรือเมื่อ `.env` ตั้งครบแล้ว (ไม่ลบตารางที่มีอยู่)  
ถ้า `install` เจอตารางในฐานแล้วจะถาม fresh — กด `n` แล้วหยุด (ไม่ migrate)

อย่า commit ไฟล์ `.env`

## รันด้วย Apache (XAMPP)

วางโฟลเดอร์ใน `htdocs` แล้วเปิดตามชื่อโฟลเดอร์ เช่น `http://localhost/myapp/`

| Path | ความหมาย |
| --- | --- |
| `/` | JSON ต้อนรับ |
| `/health` | สถานะ API และฐานข้อมูล |
| `/testview` | หน้า View ตัวอย่างแบบ layout (`layouts.app` + body `index`) |

ไฟล์สาธารณะ

- `/assets/...` → `public/assets/`
- `/uploads/...` → `public/uploads/`

## รันด้วย PHP built-in server

```bash
composer serve
```

เปิด `http://127.0.0.1:8000`

`APP_URL` เป็นค่าอ้างอิงของแอป ไม่ได้ผูกพอร์ตเซิร์ฟเวอร์

## ทดสอบและจัดโค้ด

```bash
composer test
composer cs-fix
composer cs-check
```

โค้ด PHP ทั้งโปรเจกต์ใช้ PER Coding Style 3.0

## ลำดับงานที่แนะนำ

1. ตรวจว่า `.env` มี `ENCRYPTION_KEY` และ `JWT_SECRET` แล้ว (`php deawx key:generate`)
2. `php deawx install` (ตั้ง DB + สร้างฐาน + migrate + seed RBAC)
3. ทดลอง `POST /api/auth/login` ด้วย `admin` / `password` (ดู [[Auth Starter]])
4. สร้าง controller / model เพิ่มตามงาน
5. เขียน migration เพิ่มแล้ว `php deawx migrate`
6. ผูกเส้นทางใน `Routes/web.php`
