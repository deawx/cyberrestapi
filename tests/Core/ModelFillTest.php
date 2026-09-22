<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Model;
use PHPUnit\Framework\TestCase;

final class ModelFillTest extends TestCase
{
    public function testFillKeepsOnlyFillableFieldsAndRawValues(): void
    {
        $model = new class extends Model {
            protected static string $table = 'users';
            protected static array $fillable = ['name', 'email'];
            protected static bool $timestamps = false;
        };

        $model->fill([
            'name' => 'A & B',
            'email' => 'a@example.com',
            'role' => 'admin',
        ]);

        $this->assertSame('A & B', $model->name);
        $this->assertSame('a@example.com', $model->email);
        $this->assertNull($model->role);
    }

    public function testEmptyFillableRejectsAllFields(): void
    {
        $model = new class extends Model {
            protected static string $table = 'users';
            protected static array $fillable = [];
            protected static array $guarded = ['id'];
            protected static bool $timestamps = false;
        };

        $model->fill([
            'id' => 9,
            'name' => 'Deawx',
        ]);

        $this->assertNull($model->id);
        $this->assertNull($model->name);
    }

    public function testGuardedFieldsAreRejected(): void
    {
        $model = new class extends Model {
            protected static string $table = 'users';
            protected static array $fillable = ['name'];
            protected static array $guarded = ['id', 'role'];
            protected static bool $timestamps = false;
        };

        $model->fill([
            'id' => 9,
            'name' => 'Deawx',
            'role' => 'admin',
        ]);

        $this->assertNull($model->id);
        $this->assertSame('Deawx', $model->name);
        $this->assertNull($model->role);
    }
}
