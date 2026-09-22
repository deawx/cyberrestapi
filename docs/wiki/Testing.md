# ทดสอบและสไตล์โค้ด

```bash
composer test
composer cs-fix
composer cs-check
```

PHPUnit อ่าน `phpunit.xml` แล้วรันทุกไฟล์ใน `tests/`

## ชุดทดสอบที่มีอยู่

| ไฟล์ | ตรวจอะไร |
| --- | --- |
| `tests/Core/ModelFillTest.php` | fillable / guarded |
| `tests/Core/CipherKeyTest.php` | คีย์เข้ารหัสจาก env |
| `tests/Core/ValidatorTest.php` | กฎ validation |
| `tests/Core/PaginatorTest.php` | ตัดหน้าและ meta |
| `tests/Core/BlueprintTest.php` | คอลัมน์ PK FK index |
| `tests/Core/UploadTest.php` | เก็บไฟล์และกันนามสกุลอันตราย |
| `tests/Core/RateLimitPruneTest.php` | ลบไฟล์ rate ที่หมดอายุ |
| `tests/Core/LogPruneTest.php` | ลบไฟล์ล็อกที่หมดอายุ |
| `tests/Core/MigratorRollbackTest.php` | เลือกไฟล์ rollback แบบ `--step` / `--batch` / `--path` |
| `tests/Core/EnvWriterTest.php` | เขียนคีย์ใน `.env` และคีย์จาก `key:generate` |
| `tests/Core/DbSetupTest.php` | ตรวจชื่อฐาน / format env / merge config / จับคู่ charset↔collation |
| `tests/Core/JwtTest.php` | ออก / ตรวจ / refresh / revoke JWT |
| `tests/Core/JwtRoleRouteTest.php` | JwtAuth + RequireRole บน route |
| `tests/Core/CookieTest.php` | Cookie Secure / SameSite / session params |
| `tests/Core/SecurityHeadersTest.php` | CSP_ENABLED / HSTS / report-only |

## เขียนเทสเพิ่ม

วางไฟล์ใน `tests/Core/` แล้วสืบทอด `PHPUnit\Framework\TestCase`

```php
<?php

declare(strict_types=1);

namespace Tests\Core;

use PHPUnit\Framework\TestCase;

final class ExampleTest extends TestCase
{
    public function testTrue(): void
    {
        $this->assertTrue(true);
    }
}
```

## สไตล์

ทุกไฟล์ PHP ใช้ **PER Coding Style 3.0** ผ่าน PHP-CS-Fixer ชุด `@PER-CS3x0`

อย่าใช้ PSR-2 หรือ PHP Resolver / phpcs เป็นตัวชี้สไตล์
