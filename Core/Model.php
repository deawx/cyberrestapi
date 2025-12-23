<?php

/**
 * Core\Model - Lightweight Active Record ORM สำหรับ REST API
 * ใช้ Medoo เป็น database layer, รองรับ fillable/guarded, timestamps, query builder
 * ตามมาตรฐาน Modern PHP ORM ปี 2026 (lightweight, secure, fast)
 *
 * @version 1.0.0 (December 23, 2025)
 * @author (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 * @license MIT https://cyberthai.net
 */

declare(strict_types=1);

namespace Core;

use Medoo\Medoo;
use Core\Database;
use Core\Security;

abstract class Model {
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
    private static function db(): Medoo {
        if (self::$db === null) {
            self::$db = Database::getInstance()->getConnection();
        }
        return self::$db;
    }

    /**
     * สร้าง instance ใหม่
     */
    public function __construct(array $attributes = []) {
        $this->fill($attributes);
    }

    /**
     * Mass assign ข้อมูล (ปลอดภัยด้วย fillable/guarded)
     */
    public function fill(array $attributes): static {
        $fillable = $this->getFillable();

        foreach ($attributes as $key => $value) {
            if (in_array($key, $fillable, true)) {
                $this->attributes[$key] = $value;
                $this->dirty[$key] = true;
            }
        }

        return $this;
    }

    /**
     * ดึง fillable fields (จัดการ guarded)
     */
    private function getFillable(): array {
        if (!empty(static::$guarded)) {
            return array_diff(['*'], static::$guarded); // ถ้ามี guarded → อนุญาตทุกอย่างยกเว้น guarded
        }

        return static::$fillable;
    }

    /**
     * บันทึกข้อมูล (insert หรือ update)
     */
    public function save(): bool {
        $data = Security::sanitizeArray($this->dirty);

        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            if (!isset($this->attributes['id'])) {
                $data['created_at'] = $now;
            }
            $data['updated_at'] = $now;
        }

        if (empty($data)) {
            return true; // ไม่มีอะไรเปลี่ยน
        }

        try {
            if (isset($this->attributes['id'])) {
                // Update
                self::db()->update(static::$table, $data, ['id' => $this->attributes['id']]);
                $this->dirty = [];
                return true;
            } else {
                // Insert
                self::db()->insert(static::$table, $data);
                $id = self::db()->id();
                if ($id) {
                    $this->attributes['id'] = $id;
                    $this->dirty = [];
                    return true;
                }
                return false;
            }
        } catch (\Throwable $e) {
            error_log('[Model Error] Save failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * ลบ record ปัจจุบัน
     */
    public function delete(): bool {
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
    public static function find(int $id): ?static {
        $data = self::db()->get(static::$table, '*', ['id' => $id]);
        return $data ? new static($data) : null;
    }

    /**
     * ดึงหรือ throw error
     */
    public static function findOrFail(int $id): static {
        $model = self::find($id);
        if (!$model) {
            Response::error('Record not found', 404);
        }
        return $model;
    }

    /**
     * ดึงข้อมูลทั้งหมด
     */
    public static function all(): array {
        $results = self::db()->select(static::$table, '*');
        return array_map(fn($row) => new static($row), $results ?: []);
    }

    /**
     * สร้าง record ใหม่
     */
    public static function create(array $attributes): ?static {
        $model = new static();
        $model->fill($attributes);
        return $model->save() ? $model : null;
    }

    /**
     * ค้นหาตามเงื่อนไขและคืนค่า instance แรก
     */
    public static function where(string $column, string $operator = '=', mixed $value = null): ?static {
        $where = $operator === '=' ? [$column => $value] : [$column . '[' . $operator . ']' => $value];
        $data = self::db()->get(static::$table, '*', $where);
        return $data ? new static($data) : null;
    }

    /**
     * ค้นหาทั้งหมดตามเงื่อนไข
     */
    public static function getWhere(array $where): array {
        $results = self::db()->select(static::$table, '*', $where);
        return array_map(fn($row) => new static($row), $results ?: []);
    }

    // Magic methods
    public function __get(string $name): mixed {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void {
        if (in_array($name, $this->getFillable(), true)) {
            $this->attributes[$name] = $value;
            $this->dirty[$name] = true;
        }
    }

    public function toArray(): array {
        return $this->attributes;
    }

    public function __toString(): string {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }
}
