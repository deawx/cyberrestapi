# ล็อกและ Rate limit

## ล็อก

```php
\Core\Log::write('สร้างผู้ใช้แล้ว id=12');
\Core\Log::write('ต่อฐานข้อมูลไม่ได้', 'error');
```

ไฟล์อยู่ที่ `Storage/logs/`

- `app_YYYY-MM-DD.log`
- `error_YYYY-MM-DD.log`

ช่องทางที่ใช้ได้คือ `app` และ `error` เท่านั้น

ระบบสุ่มกวาดไฟล์เก่าตาม `LOG_RETENTION_DAYS` (ค่าเริ่มต้น 14 วัน) ไม่เกิน 250 ไฟล์ต่อครั้ง

อย่า commit ไฟล์ล็อก

## Rate limit

ตั้งใน `.env`

```env
RATE_LIMIT=120
RATE_LIMIT_WINDOW=60
```

`RATE_LIMIT=0` คือปิด

นับต่อ IP ในหน้าต่างเวลาที่กำหนด  
เกินแล้วตอบ `429 Too Many Requests`

ไฟล์นับอยู่ที่ `Storage/cache/rate/` และถูกกวาดเมื่อหมดอายุหน้าต่าง

บน XAMPP ไม่ต้องเปิด `TRUST_PROXIES` เพื่อไม่ให้นับ IP จาก header ที่ปลอมได้
