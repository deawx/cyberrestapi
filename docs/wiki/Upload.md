# อัปโหลดไฟล์

ไฟล์ถูกเก็บใต้ `public/` ค่าเริ่มต้นคือ `public/uploads/`  
เข้าถึงผ่าน URL `/uploads/ชื่อไฟล์`

## ใช้ใน controller

```php
use Core\Upload;

public function store(): never
{
    $file = Upload::store('avatar', 'avatars', ['jpg', 'jpeg', 'png', 'webp']);

    $this->json($file, 201);
}
```

ผลที่คืนมา

```json
{
  "name": "photo.jpg",
  "path": "avatars/a1b2c3....jpg",
  "url": "/cyberrestapi/avatars/a1b2c3....jpg",
  "size": 18432,
  "mime": "image/jpeg"
}
```

ชื่อไฟล์ที่เก็บเป็นค่าสุ่ม ไม่ใช้ชื่อเดิมของผู้ใช้

## พารามิเตอร์

```php
Upload::store(
    string $field,                 // ชื่อฟิลด์ใน multipart
    string $directory = '',        // โฟลเดอร์ย่อย เช่น avatars
    array $allowed = [...],        // นามสกุลที่อนุญาต
    ?int $maxBytes = null,         // ถ้าไม่ใส่ใช้ UPLOAD_MAX_KB
    ?string $root = null,          // ค่าเริ่มต้น public/
);
```

โฟลเดอร์ย่อยรับเฉพาะ `A-Za-z0-9_-` และห้าม `..`

## นามสกุลที่ถูกกันเสมอ

`php`, `phtml`, `phar`, `cgi`, `exe`, `bat`, `cmd`, `sh`, `htaccess`, `html`, `htm`, `js`, `svg`

ตรวจทั้งนามสกุลและ MIME (`finfo`)  
ถ้า MIME เป็น PHP หรือ HTML จะถูกปฏิเสธ

ค่าเริ่มต้นที่อนุญาต: `jpg`, `jpeg`, `png`, `gif`, `webp`, `pdf`

ใช้คู่กับกฎ `file`, `image`, `mimes`, `max` ใน [[Validation]] ได้

ไฟล์ใน `public/uploads/` ไม่เข้า git
