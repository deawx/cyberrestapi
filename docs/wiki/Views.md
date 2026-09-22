# View และ HTTP client

## เรนเดอร์หน้า HTML

ไฟล์อยู่ที่ `Views/` (เปลี่ยนได้ด้วย `VIEW_PATH`)  
ชื่อวิวใช้จุดแทนโฟลเดอร์ เช่น `errors.403` → `Views/errors/403.php`

```php
$this->view('index', ['title' => 'หน้าแรก']);
```

หรือ

```php
\Core\View::render('index', ['title' => 'หน้าแรก']);
```

### Layout (header / footer)

หน้าในโฟลเดอร์ย่อยเป็นแค่ **body** แล้วหุ้มด้วย layout ที่ include header/footer

โครงสร้างตัวอย่าง:

```text
Views/
  layouts/
    app.php              ← โครง HTML + <?= $content ?>
    partials/
      header.php
      footer.php
  admin/
    dashboard.php        ← แค่เนื้อหา
  user/
    home.php
```

เรียกใช้:

```php
$this->view('admin.dashboard', [
    'title' => 'แดชบอร์ด',
    'appname' => 'CyberRestAPI',
], layout: 'layouts.app');
```

ใน `Views/layouts/app.php`:

```php
<!doctype html>
<html>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main>
<?= $content ?>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
```

ใน `Views/admin/dashboard.php` มีแค่เนื้อหา ไม่ต้องซ้ำ header/footer

ตัวอย่างที่ `/testview` ใช้แล้ว:

```php
\Core\View::render('index', [
    'title' => 'CyberAPP',
    'appname' => 'CyberAPP Rest Api Core',
], layout: 'layouts.app');
```

- body: `Views/index.php`
- layout: `Views/layouts/app.php` (+ `partials/header.php`, `partials/footer.php`)

### ในเทมเพลตเต็มหน้า (ไม่มี layout)

```php
<!doctype html>
<html>
<head>
    <title><?= $title ?></title>
    <link rel="stylesheet" href="<?= $asset('css/app.css') ?>">
</head>
<body>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
    </form>
    <script src="<?= $asset('js/app.js') ?>"></script>
</body>
</html>
```

`$asset('css/app.css')` ชี้ไปที่ `public/assets/css/app.css` (inject อัตโนมัติทุกวิว/เลย์เอาต์)  
ถ้าต้องการเรียกยาว: `\Core\View::asset('css/app.css')`

ข้อมูลที่ส่งเข้าวิวถูก sanitize และมี `$csrf_token` กับ `$asset` ให้อัตโนมัติ  
ห้ามใช้ `..` ในชื่อวิว

`View::render(..., withJs: true)` จะฝัง `window.appData` ในหน้า

หน้า error ตัวอย่างอยู่ที่ `Views/errors/403.php`

## HTTP client (เรียก API อื่น)

```php
use Core\Http;

Http::timeout(15);

$res = Http::get('https://api.example.com/health');
$res = Http::post('https://api.example.com/users', ['name' => 'Deawx']);
$res = Http::withToken($jwt, 'GET', 'https://api.example.com/me');
```

ผลที่คืนมา

```php
[
    'status'  => 200,
    'headers' => [...],
    'body'    => '...',
    'json'    => [...],   // ถ้า Content-Type เป็น JSON
    'info'    => [...],
]
```

ถ้าล้มเหลวจะมีคีย์ `error`

อนุญาตเฉพาะ `http` และ `https`  
ตรวจ SSL ของปลายทางอยู่แล้ว
