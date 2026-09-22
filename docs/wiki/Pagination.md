# Pagination

รองรับ `?page=1&per_page=20`  
ถ้าไม่ส่ง — **`page` เริ่มต้น `1`**, **`per_page` เริ่มต้น `20`**  
`per_page` สูงสุด `100`

การค้นข้อความ (`?q=`) **ไม่ได้มากับ Core Paginator โดยตรง** — ทำใน controller/model ของแต่ละ resource  
ตัวอย่าง Auth starter: `GET /api/admin/users?q=admin` ค้น `name` / `username` / `email` (ดู [[Auth Starter]])

## จาก controller

```php
public function index(): never
{
    $this->paginate(\App\Models\User::class);
}

public function active(): never
{
    $this->paginate(\App\Models\User::class, ['status' => 'active']);
}
```

`$where` เป็นการเทียบค่าตรงกัน ไม่ใช่ค้นข้อความ `?q=`

## ตอบเอง

```php
[$page, $perPage] = \Core\Paginator::fromRequest($this->request);
$result = \App\Models\User::paginate($page, $perPage);
\Core\Response::paginate($result['data'], $result['total'], $page, $perPage);
```

## รูปแบบ meta

```json
{
  "meta": {
    "pagination": {
      "total": 23,
      "per_page": 10,
      "current_page": 2,
      "last_page": 3,
      "from": 11,
      "to": 20
    }
  }
}
```

ถ้าไม่มีแถว `from` และ `to` เป็น `0`
