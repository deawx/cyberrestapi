# ค่าคอนฟิก (.env)

คัดลอกจาก `.env.example` แล้วแก้เฉพาะเครื่องตัวเอง

`ENCRYPTION_KEY` และ `JWT_SECRET` ต้องมีค่า อย่าปล่อยว่าง  
สร้างด้วย `php deawx key:generate` (ทับของเดิมใช้ `--force`)

## แอป

| คีย์ | ความหมาย |
| --- | --- |
| `APP_NAME` | ชื่อแอป |
| `APP_VERSION` | เวอร์ชันที่โชว์ใน CLI และ `/` |
| `APP_ENV` | `dev` หรือ `prod` |
| `APP_DEBUG` | `true` แล้ว error จะโชว์รายละเอียด |
| `APP_URL` | URL อ้างอิง เช่น `http://localhost/cyberrestapi` |
| `CORS_ORIGIN` | origin ที่อนุญาต ค่า `*` คือทุกที่ |
| `VIEW_PATH` | โฟลเดอร์วิว ค่าเริ่มต้น `Views` |
| `RATE_LIMIT` | จำนวนครั้งสูงสุดต่อหน้าต่างเวลา `0` คือปิด |
| `RATE_LIMIT_WINDOW` | หน้าต่างนับเป็นวินาที ค่าเริ่มต้น `60` |
| `LOG_RETENTION_DAYS` | เก็บล็อกกี่วัน ค่าเริ่มต้น `14` |
| `UPLOAD_MAX_KB` | ขนาดไฟล์สูงสุดเป็น KB |
| `UPLOAD_DIR` | โฟลเดอร์ย่อยใต้ `public/` ค่าเริ่มต้น `uploads` |

## ฐานข้อมูล

| คีย์ | ความหมาย |
| --- | --- |
| `DB_TYPE` | `mysql` หรือ `mariadb` เท่านั้น (ชั้น migration / install) |
| `DB_HOST` | โฮสต์ |
| `DB_PORT` | พอร์ต ค่าเริ่มต้น `3306` |
| `DB_NAME` | ชื่อฐานข้อมูล |
| `DB_USER` / `DB_PASSWORD` | บัญชี MySQL / MariaDB |
| `DB_CHARSET` | ค่าเริ่มต้น `utf8mb4` |
| `DB_COLLATION` | ต้องคู่กับ charset เช่น `utf8mb4` → `utf8mb4_general_ci`, `utf8` → `utf8_general_ci` — ตอน `install` ถ้า charset เปลี่ยน ระบบแนะนำ collation ให้ตรงอัตโนมัติ |
| `DB_PREFIX` | คำนำหน้าตาราง ถ้ามี |

`Schema` / `Blueprint` / `migrate` / `install` **ยังไม่รองรับ** PostgreSQL, SQLite หรือเอนจินอื่น — ดู [[Migration]]  
ถ้าใช้เอนจินอื่น ให้สร้างตารางเอง แล้วใช้ Medoo ตามที่ driver รองรับ — ดู [[Medoo]] และ [medoo.in/api/new](https://medoo.in/api/new)

ถ้าใส่ `REDIS_HOST` ระบบจะพยายามต่อ Redis ผ่าน Predis

หลัง create-project แนะนำ:

```bash
php deawx install       # ถาม DB แล้ว migrate
php deawx install -y    # ไม่ถาม — อ่าน .env แล้ว migrate + seed
```

รายละเอียดดู [[CLI]] และ [[Auth Starter]]

## ความปลอดภัย

ตั้งแค่คีย์ที่จำเป็น — cookie / CSP / HSTS / proxy ทำงานอัตโนมัติจาก `APP_ENV` ไม่ต้องใส่ใน `.env`

| คีย์ | ความหมาย |
| --- | --- |
| `ENCRYPTION_KEY` | คีย์ AES ของ `Core\Cipher` (hex หรือ base64) |
| `JWT_SECRET` | ความลับเซ็น JWT (≥ 32 ตัวอักษร) |
| `JWT_ACCESS_TTL` | อายุ access token เป็นวินาที (ค่าเริ่มต้น 900) ไม่ใส่ก็ได้ |
| `JWT_REFRESH_TTL` | อายุ refresh token เป็นวินาที (ค่าเริ่มต้น 604800) ไม่ใส่ก็ได้ |
| `SESSION_PREFIX` | คำนำหน้าคีย์ session |

ค่า `APP_ENV=dev` หรือ `prod` พอให้ความปลอดภัยเว็บปรับเอง (Secure cookie, CSP, HSTS)  
override ขั้นสูงและแนวทาง JWT มือถือดูที่ [[Security]]
