# Auth starter (RBAC)

ชุดตัวอย่างใน `Apps/` + `Database/` สำหรับหลัง `create-project`  
**ไม่ใช่ Core** — ลบหรือแทนที่ได้ถ้าแอปไม่ต้องการ  
ต้องใช้ **MySQL หรือ MariaDB** เพราะพึ่ง migration ของเฟรมเวิร์ก (ดู [[Migration]])

## ติดตั้ง

```bash
composer create-project deawx/cyberrestapi myapp
cd myapp
php deawx install -y
```

| คำสั่ง | ความหมาย |
| --- | --- |
| `php deawx install` | ถาม `DB_*` แล้ว migrate (ถาม seed) |
| `php deawx install -y` | ไม่ถาม — อ่าน `.env` แล้ว migrate + seed |
| `php deawx install --seed` | โหมดถาม แต่บังคับ seed |

`-y` ไม่ลบตารางที่มีอยู่ (ไม่ `migrate:fresh`)  
โหมดถาม: ถ้าฐานมีตารางอยู่แล้ว กด `n` ที่คำถาม fresh จะ**หยุดทันที**

## บัญชีทดลอง

| username | รหัสผ่าน | role |
| --- | --- | --- |
| `admin` | `password` | `admin` |
| `user` | `password` | `user` |

อีเมลใน seed เป็น `admin@example.com` / `user@example.com` (ติดต่ออย่างเดียว ไม่ใช้ล็อกอิน)  
เปลี่ยนรหัสผ่านก่อนขึ้นของจริง

## ตาราง

| ตาราง | ความหมาย |
| --- | --- |
| `users` | บัญชีผู้ใช้ (`name`, `username`, `email`, `password`, `is_active`, timestamps) |
| `roles` | บทบาท เช่น admin, user |
| `permissions` | สิทธิ์ เช่น `users.view` |
| `role_permissions` | เชื่อม role ↔ permission |
| `user_roles` | เชื่อม user ↔ role |

คอลัมน์มี MySQL `COMMENT` ใน migration แล้ว (ดูใน phpMyAdmin ได้)

## สิทธิ์โปรไฟล์ตัวเอง

| permission | ใช้กับ | role ที่ได้จาก seed |
| --- | --- | --- |
| `profile.view` | `GET /api/profile` | `admin`, `user` |
| `profile.update` | `PUT /api/profile` | `admin`, `user` |
| `self.permissions.view` | `GET /api/profile/permissions` | `admin`, `user` |

ถ้าฐานเก่ายังไม่มี `profile.update` ให้ `php deawx migrate:fresh --seed` หรือสร้าง permission แล้วผูก role ผ่านแอดมิน

## วิธีเรียก API

แทน `{BASE}` ด้วย URL โปรเจกต์ เช่น

- `http://127.0.0.1:8000` (`composer serve`)
- `http://localhost/cyberrestapi` (XAMPP)
- `https://www.laskcms.local/cyberrestapi` (vhost)

Header ที่ใช้บ่อย:

| Header | เมื่อไหร่ |
| --- | --- |
| `Content-Type: application/json` | ทุก request ที่มี body JSON |
| `Authorization: Bearer <access_token>` | เส้นทางที่ต้องล็อกอิน / admin |

ถ้า login/refresh ได้ แต่ `/api/profile` ได้ `401` — ตรวจว่า URL เป็น `{BASE}/api/profile` (มี `/api`) และอัปเดต `access_token` ใน Postman หลัง refresh  
การอ่าน Bearer ทำใน `Core\Request` ไม่พึ่ง `.htaccess`

ล็อกอินด้วย **`username` + `password`** (ไม่ใช่ email)

## API ทั้งหมด

### สาธารณะ (ไม่ต้อง token)

| Method | Path | ความหมาย / วิธีใช้ |
| --- | --- | --- |
| `GET` | `/` | JSON ต้อนรับ |
| `GET` | `/health` | สถานะ API + DB → `OK` / `DEGRADED` |
| `GET` | `/testview` | หน้า HTML ตัวอย่าง |
| `POST` | `/api/auth/login` | body: `username`, `password` → `access_token`, `refresh_token`, `token_type`, `expires_in` |
| `POST` | `/api/auth/refresh` | body: `refresh_token` → โทเคนคู่ใหม่ |

#### Login

```bash
curl -X POST {BASE}/api/auth/login \
  -H "Content-Type: application/json" \
  -d "{\"username\":\"admin\",\"password\":\"password\"}"
```

#### Refresh

```bash
curl -X POST {BASE}/api/auth/refresh \
  -H "Content-Type: application/json" \
  -d "{\"refresh_token\":\"<refresh_token>\"}"
```

### ต้องมี Bearer JWT

| Method | Path | ความหมาย / วิธีใช้ |
| --- | --- | --- |
| `POST` | `/api/auth/logout` | ยกเลิก access + refresh ที่ผูกกัน (ส่งแค่ Bearer) |
| `GET` | `/api/profile` | โปรไฟล์ตัวเอง + roles (ต้องมี `profile.view`) |
| `PUT` | `/api/profile` | แก้โปรไฟล์ตัวเอง (ต้องมี `profile.update`) |
| `GET` | `/api/profile/permissions` | roles + permissions ของตัวเอง (ต้องมี `self.permissions.view`) |

#### Logout

ส่งแค่ Bearer `access_token` — ระบบยกเลิกทั้ง access และ refresh ที่ผูกกับโทเคนนั้นอัตโนมัติ (ไม่ต้องส่ง `refresh_token` ใน body)

```bash
curl -X POST {BASE}/api/auth/logout \
  -H "Authorization: Bearer <access_token>"
```

#### Me

```bash
curl {BASE}/api/profile \
  -H "Authorization: Bearer <access_token>"

curl -X PUT {BASE}/api/profile \
  -H "Authorization: Bearer <access_token>" \
  -H "Content-Type: application/json" \
  -d "{\"name\":\"ชื่อใหม่\",\"email\":\"new@example.com\"}"

# เปลี่ยนรหัสผ่านต้องส่ง current_password ด้วย
curl -X PUT {BASE}/api/profile \
  -H "Authorization: Bearer <access_token>" \
  -H "Content-Type: application/json" \
  -d "{\"password\":\"newpass12\",\"current_password\":\"password\"}"

curl {BASE}/api/profile/permissions \
  -H "Authorization: Bearer <access_token>"
```

### แอดมินเท่านั้น (`role=admin` + Bearer)

ทุกเส้นทางด้านล่างต้องมี `Authorization: Bearer <access_token>` ของบัญชีที่มี role `admin`

#### Users

| Method | Path | Body / หมายเหตุ |
| --- | --- | --- |
| `GET` | `/api/admin/users` | รายการตัดหน้า + ค้นหา — ดูด้านล่าง |
| `POST` | `/api/admin/users` | สร้าง 1 คน |
| `POST` | `/api/admin/users/bulk` | สร้างหลายคน — body `{ "users": [ {...}, ... ] }` สูงสุด 50 |
| `POST` | `/api/admin/users/bulk-delete` | ลบหลายคน — body `{ "ids": [1,2,3] }` สูงสุด 50 |
| `GET` | `/api/admin/users/{id}` | รายละเอียด + roles + permissions |
| `PUT` | `/api/admin/users/{id}` | แก้บางฟิลด์: `name`, `username`, `email`, `password`, `is_active` |
| `DELETE` | `/api/admin/users/{id}` | ลบ 1 คน (ลบบัญชีตัวเองไม่ได้) |
| `PUT` | `/api/admin/users/{id}/roles` | `{ "roles": ["user"] }` |

Query ของรายการผู้ใช้:

| พารามิเตอร์ | ค่าเริ่มต้น | ความหมาย |
| --- | --- | --- |
| `page` | `1` | หน้า |
| `per_page` | `20` (สูงสุด `100`) | จำนวนต่อหน้า |
| `q` | *(ว่าง)* | ค้น `name` / `username` / `email` แบบมีข้อความบางส่วน |

```bash
curl "{BASE}/api/admin/users?page=1&per_page=20&q=admin" \
  -H "Authorization: Bearer <access_token>"
```

สร้างผู้ใช้:

```bash
curl -X POST {BASE}/api/admin/users \
  -H "Authorization: Bearer <access_token>" \
  -H "Content-Type: application/json" \
  -d "{\"name\":\"Somchai\",\"username\":\"somchai\",\"email\":\"somchai@example.com\",\"password\":\"secret12\",\"roles\":[\"user\"]}"
```

สร้างหลายคน:

```bash
curl -X POST {BASE}/api/admin/users/bulk \
  -H "Authorization: Bearer <access_token>" \
  -H "Content-Type: application/json" \
  -d "{\"users\":[{\"name\":\"A\",\"username\":\"user_a\",\"email\":\"a@example.com\",\"password\":\"secret12\",\"roles\":[\"user\"]},{\"name\":\"B\",\"username\":\"user_b\",\"email\":\"b@example.com\",\"password\":\"secret12\"}]}"
```

ลบหลายคน (ข้าม id ของตัวเองอัตโนมัติ):

```bash
curl -X POST {BASE}/api/admin/users/bulk-delete \
  -H "Authorization: Bearer <access_token>" \
  -H "Content-Type: application/json" \
  -d "{\"ids\":[2,3,4]}"
```

| ฟิลด์ | บังคับตอนสร้าง | หมายเหตุ |
| --- | --- | --- |
| `name` | ใช่ | ชื่อที่แสดง max 120 |
| `username` | ใช่ | ล็อกอิน — `A-Za-z0-9._-` ความยาว 3–60 |
| `email` | ใช่ | ติดต่อ ไม่ใช้ล็อกอิน |
| `password` | ใช่ | min 6 |
| `is_active` | ไม่ | boolean ค่าเริ่มต้น true |
| `roles` | ไม่ | array ของชื่อ role ค่าเริ่มต้น `["user"]` |

#### Roles

| Method | Path | Body / หมายเหตุ |
| --- | --- | --- |
| `GET` | `/api/admin/roles` | รายการ + permissions |
| `POST` | `/api/admin/roles` | `name`, `display_name` (+ `permissions` ได้) |
| `GET` | `/api/admin/roles/{id}` | รายละเอียด |
| `PUT` | `/api/admin/roles/{id}` | `name`, `display_name` |
| `DELETE` | `/api/admin/roles/{id}` | ลบ `admin` / `user` ไม่ได้ |
| `PUT` | `/api/admin/roles/{id}/permissions` | `{ "permissions": ["users.view", "users.create"] }` |

`name` ของ role ใช้ได้แค่ `a-z` `0-9` และ `_`

```bash
curl -X POST {BASE}/api/admin/roles \
  -H "Authorization: Bearer <access_token>" \
  -H "Content-Type: application/json" \
  -d "{\"name\":\"editor\",\"display_name\":\"Editor\",\"permissions\":[\"users.view\"]}"
```

#### Permissions

| Method | Path | Body / หมายเหตุ |
| --- | --- | --- |
| `GET` | `/api/admin/permissions` | รายการ |
| `POST` | `/api/admin/permissions` | `name`, `display_name` |
| `GET` | `/api/admin/permissions/{id}` | รายละเอียด |
| `PUT` | `/api/admin/permissions/{id}` | `name`, `display_name` |
| `DELETE` | `/api/admin/permissions/{id}` | ลบ |

`name` ของ permission ใช้ได้แค่ `a-z` `0-9` `_` และ `.` เช่น `users.view`

```bash
curl -X POST {BASE}/api/admin/permissions \
  -H "Authorization: Bearer <access_token>" \
  -H "Content-Type: application/json" \
  -d "{\"name\":\"reports.view\",\"display_name\":\"ดูรายงาน\"}"
```

## ลำดับทดสอบแนะนำ

1. `GET {BASE}/health` → `db_connected: true`
2. `POST {BASE}/api/auth/login` → เก็บ `access_token`, `refresh_token`
3. `GET {BASE}/api/profile` + Bearer
4. `GET {BASE}/api/admin/users` + Bearer (บัญชี admin)
5. `POST {BASE}/api/auth/logout` + Bearer เท่านั้น

## ไฟล์ที่เกี่ยวข้อง

| โฟลเดอร์ | เนื้อหา |
| --- | --- |
| `Database/migrations/` | สร้างตาราง RBAC |
| `Database/Seeders/` | `RbacSeeder` + `DatabaseSeeder` |
| `Apps/Models/` | `User`, `Role`, `Permission` |
| `Apps/Controllers/` | `AuthController`, `ProfileController`, `Admin\*` |
| `Routes/web.php` | กลุ่ม `/api/...` และ `/`, `/health` |

JWT ใส่ `username`, `role` (หลัก) และ `roles` (รายการ) — `RequireRole` / `$request->hasRole()` ตรวจได้ทั้งสองแบบ  
ดูเพิ่ม [[Security]] · [[Routing]]
