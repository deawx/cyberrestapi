# Validation

ตรวจข้อมูลก่อนทำงานต่อ ถ้าไม่ผ่านระบบตอบ `422` ทันที

```php
$validated = $this->validate([
    'email' => 'required|email|max:191',
    'password' => 'required|min:8|confirmed',
    'role' => 'in:admin,user',
]);
```

กฎเขียนเป็นสตริงคั่นด้วย `|` หรือเป็นอาร์เรย์ก็ได้

ตรวจเองโดยไม่ออกจาก request

```php
$validator = \Core\Validator::make($request->all(), [
    'email' => 'required|email',
]);

if ($validator->fails()) {
    // $validator->errors()
}

$ok = $validator->validated();
```

## กฎที่มีให้

| กฎ | ความหมาย |
| --- | --- |
| `required` | ต้องมีค่า |
| `nullable` | ว่างได้ ถ้าว่างจะข้ามกฎอื่น |
| `email` | อีเมล |
| `integer` | จำนวนเต็ม |
| `numeric` | เป็นตัวเลข |
| `string` | สตริง |
| `array` | อาร์เรย์ |
| `boolean` | true/false, 0/1 |
| `url` | URL |
| `min:n` | ความยาวน้อยสุด หรือค่าน้อยสุดถ้าเป็นตัวเลข |
| `max:n` | ความยาวมากสุด หรือขนาดไฟล์เป็น KB |
| `in:a,b,c` | ต้องเป็นค่าในรายการ |
| `confirmed` | ต้องมีฟิลด์ `ชื่อ_confirmation` ตรงกัน |
| `regex:^[A-Za-z0-9]+$` | รูปแบบ (ไม่ใส่เครื่องหมาย `/`) |
| `file` | เป็นไฟล์อัปโหลด |
| `image` | เป็นรูป |
| `mimes:jpg,png,pdf` | นามสกุลที่อนุญาต |

Validation ช่วยให้ข้อมูลอยู่ในรูปที่ต้องการ แต่**ไม่แทน**การ bind query และไม่แทนการ escape ตอนแสดง HTML
