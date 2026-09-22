<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Model
 *      Active Record บางเบาบน Medoo
 *      รองรับ fillable, guarded, timestamps, paginate
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

use Medoo\Medoo;
use Core\Database;

abstract class Model
{
    /** ชื่อตาราง (ต้องกำหนดใน model ลูก) */
    protected static string $table = '';

    /** ฟิลด์ที่อนุญาตให้ mass assign */
    protected static array $fillable = [];

    /** ฟิลด์ที่ห้าม mass assign (ถ้ากำหนดจะ override fillable) */
    protected static array $guarded = [];

    /** ใช้ timestamps อัตโนมัติหรือไม่ */
    protected static bool $timestamps = true;

    /** ข้อมูลของ instance */
    protected array $attributes = [];

    /** ข้อมูลที่เปลี่ยนแปลง (dirty) */
    protected array $dirty = [];

    /** Instance ของ Medoo */
    private static ?Medoo $db = null;

    /**
     * เริ่มต้น database connection
     */
    private static function db(): Medoo
    {
        if (self::$db === null) {
            self::$db = Database::getInstance()->getConnection();
        }
        return self::$db;
    }

    /**
     * สร้าง instance ใหม่
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Mass assign ข้อมูล (ปลอดภัยด้วย fillable/guarded)
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if (!is_string($key) || !$this->isFillable($key)) {
                continue;
            }
            $this->attributes[$key] = $value;
            $this->dirty[$key] = true;
        }

        return $this;
    }

    private function isFillable(string $key): bool
    {
        if (in_array($key, static::$guarded, true)) {
            return false;
        }
        if (static::$fillable === []) {
            return false;
        }
        return in_array($key, static::$fillable, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function dirtyAttributes(): array
    {
        $data = [];
        foreach ($this->dirty as $key => $isDirty) {
            if ($isDirty === true && array_key_exists($key, $this->attributes)) {
                $data[$key] = $this->attributes[$key];
            }
        }
        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function hydrate(array $data): static
    {
        $model = new static();
        $model->attributes = $data;
        $model->dirty = [];
        return $model;
    }

    public function save(): bool
    {
        $data = $this->dirtyAttributes();

        if (static::$timestamps) {
            $now = date('Y-m-d H:i:s');
            if (!isset($this->attributes['id'])) {
                $data['created_at'] = $now;
            }
            $data['updated_at'] = $now;
        }

        if ($data === []) {
            return true;
        }

        try {
            if (isset($this->attributes['id'])) {
                self::db()->update(static::$table, $data, ['id' => $this->attributes['id']]);
                $this->attributes = array_merge($this->attributes, $data);
                $this->dirty = [];
                return true;
            }

            self::db()->insert(static::$table, $data);
            $id = self::db()->id();
            if ($id) {
                $this->attributes = array_merge($this->attributes, $data);
                $this->attributes['id'] = $id;
                $this->dirty = [];
                return true;
            }
            return false;
        } catch (\Throwable $e) {
            error_log('[Model Error] Save failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * ลบ record ปัจจุบัน
     */
    public function delete(): bool
    {
        if (!isset($this->attributes['id'])) {
            return false;
        }

        try {
            self::db()->delete(static::$table, ['id' => $this->attributes['id']]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * ดึงข้อมูลตาม ID
     */
    public static function find(int $id): ?static
    {
        $data = self::db()->get(static::$table, '*', ['id' => $id]);
        return is_array($data) ? self::hydrate($data) : null;
    }

    /**
     * ดึงหรือ throw error
     */
    public static function findOrFail(int $id): static
    {
        $model = self::find($id);
        if (!$model) {
            Response::error('Record not found', 404);
        }
        return $model;
    }

    /**
     * ดึงข้อมูลทั้งหมด
     */
    public static function all(): array
    {
        $results = self::db()->select(static::$table, '*');
        return array_map(fn($row) => self::hydrate($row), $results ?: []);
    }

    /**
     * สร้าง record ใหม่
     */
    public static function create(array $attributes): ?static
    {
        $model = new static();
        $model->fill($attributes);
        return $model->save() ? $model : null;
    }

    /**
     * ค้นหาตามเงื่อนไขและคืนค่า instance แรก
     */
    public static function where(string $column, string $operator = '=', mixed $value = null): ?static
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
            return null;
        }

        $allowedOperators = ['=', '>', '<', '>=', '<=', '!=', '<>', 'LIKE', 'like'];
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        if (!in_array($operator, $allowedOperators, true)) {
            return null;
        }

        $where = $operator === '=' ? [$column => $value] : [$column . '[' . $operator . ']' => $value];
        $data = self::db()->get(static::$table, '*', $where);
        return is_array($data) ? self::hydrate($data) : null;
    }

    /**
     * @param array<string, mixed> $where
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public static function paginate(int $page = 1, int $perPage = 20, array $where = []): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $safe = $where === [] ? [] : self::filterWhere($where);
        $offset = ($page - 1) * $perPage;

        if ($safe === []) {
            $total = (int) self::db()->count(static::$table);
            $rows = self::db()->select(static::$table, '*', [
                'LIMIT' => [$offset, $perPage],
                'ORDER' => ['id' => 'DESC'],
            ]);
        } else {
            $total = (int) self::db()->count(static::$table, $safe);
            $rows = self::db()->select(static::$table, '*', array_merge($safe, [
                'LIMIT' => [$offset, $perPage],
                'ORDER' => ['id' => 'DESC'],
            ]));
        }

        return [
            'data' => array_map(
                static fn(array $row): array => self::hydrate($row)->toArray(),
                $rows ?: [],
            ),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * ค้นหาทั้งหมดตามเงื่อนไข
     */
    public static function getWhere(array $where): array
    {
        $safe = self::filterWhere($where);
        if ($safe === []) {
            return [];
        }

        $results = self::db()->select(static::$table, '*', $safe);
        return array_map(fn($row) => self::hydrate($row), $results ?: []);
    }

    /**
     * รับเฉพาะชื่อคอลัมน์ธรรมดา กัน Medoo operator จาก input ของผู้ใช้
     *
     * @param array<string|int, mixed> $where
     * @return array<string, mixed>
     */
    private static function filterWhere(array $where): array
    {
        $safe = [];
        foreach ($where as $key => $value) {
            if (!is_string($key) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
                continue;
            }
            $safe[$key] = $value;
        }
        return $safe;
    }

    // Magic methods
    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        if ($this->isFillable($name)) {
            $this->attributes[$name] = $value;
            $this->dirty[$name] = true;
        }
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function __toString(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }
}
