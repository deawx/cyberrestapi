<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Blueprint;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BlueprintTest extends TestCase
{
    public function testCompileMedooColumns(): void
    {
        $table = new Blueprint();
        $table->id();
        $table->string('email', 191)->unique();
        $table->string('name')->nullable();
        $table->timestamps();

        $columns = $table->toColumns();

        $this->assertSame('@id', $columns[0]);
        $this->assertSame(['VARCHAR(191)', 'NOT NULL', 'UNIQUE'], $columns['email']);
        $this->assertSame(['VARCHAR(255)', 'NULL'], $columns['name']);
        $this->assertSame(['TIMESTAMP', 'NULL'], $columns['created_at']);
        $this->assertSame(['TIMESTAMP', 'NULL'], $columns['updated_at']);
    }

    public function testCompileForeignKeys(): void
    {
        $table = new Blueprint();
        $table->id();
        $table->foreignId('category_id', 'categories');
        $table->bigInteger('last_post_user_id')->nullable();
        $table->foreign('last_post_user_id', 'users', 'id', 'SET NULL');

        $columns = $table->toColumns();

        $this->assertSame(['BIGINT', 'NOT NULL'], $columns['category_id']);
        $this->assertContains('FOREIGN KEY (<category_id>) REFERENCES <categories> (<id>) ON DELETE CASCADE', $columns);
        $this->assertContains('FOREIGN KEY (<last_post_user_id>) REFERENCES <users> (<id>) ON DELETE SET NULL', $columns);
    }

    public function testCompileIndexes(): void
    {
        $table = new Blueprint();
        $table->id();
        $table->string('slug', 191)->index();
        $table->index(['thread_id', 'created_at']);
        $table->uniqueIndex(['post_id', 'user_id']);

        $columns = $table->toColumns();

        $this->assertContains('INDEX (<slug>)', $columns);
        $this->assertContains('INDEX (<thread_id>, <created_at>)', $columns);
        $this->assertContains('UNIQUE INDEX (<post_id>, <user_id>)', $columns);
    }

    public function testCompileAlterStatements(): void
    {
        $table = new Blueprint();
        $table->string('phone', 20)->nullable();
        $table->index('phone');
        $table->dropColumn('bio');

        $sql = $table->toAlterStatements('users');

        $this->assertContains('ALTER TABLE "users" ADD COLUMN "phone" VARCHAR(20) NULL', $sql);
        $this->assertContains('ALTER TABLE "users" ADD INDEX ("phone")', $sql);
        $this->assertContains('ALTER TABLE "users" DROP COLUMN "bio"', $sql);
    }

    public function testCompileComments(): void
    {
        $table = new Blueprint();
        $table->comment('ตารางผู้ใช้');
        $table->id()->comment('PK');
        $table->string('email', 191)->unique()->comment('อีเมลล็อกอิน');
        $table->foreignId('role_id', 'roles')->comment('FK บทบาท');

        $columns = $table->toColumns();

        $this->assertSame('ตารางผู้ใช้', $table->tableComment());
        $this->assertSame(
            ['BIGINT', 'NOT NULL', 'AUTO_INCREMENT', 'PRIMARY KEY', "COMMENT 'PK'"],
            $columns['id'],
        );
        $this->assertSame(
            ['VARCHAR(191)', 'NOT NULL', 'UNIQUE', "COMMENT 'อีเมลล็อกอิน'"],
            $columns['email'],
        );
        $this->assertSame(
            ['BIGINT', 'NOT NULL', "COMMENT 'FK บทบาท'"],
            $columns['role_id'],
        );
        $this->assertContains('FOREIGN KEY (<role_id>) REFERENCES <roles> (<id>) ON DELETE CASCADE', $columns);
    }

    public function testCompileExtendedTypes(): void
    {
        $table = new Blueprint();
        $table->id();
        $table->char('code', 8);
        $table->json('meta');
        $table->dateTime('published_at');
        $table->date('born_on');
        $table->unsignedBigInteger('hits');
        $table->enum('status', ['draft', 'published']);
        $table->uuid();
        $table->softDeletes();
        $table->integer('score')->unsigned();
        $table->column('geo', 'POINT');

        $columns = $table->toColumns();

        $this->assertSame(['CHAR(8)', 'NOT NULL'], $columns['code']);
        $this->assertSame(['JSON', 'NOT NULL'], $columns['meta']);
        $this->assertSame(['DATETIME', 'NOT NULL'], $columns['published_at']);
        $this->assertSame(['DATE', 'NOT NULL'], $columns['born_on']);
        $this->assertSame(['BIGINT UNSIGNED', 'NOT NULL'], $columns['hits']);
        $this->assertSame(["ENUM('draft','published')", 'NOT NULL'], $columns['status']);
        $this->assertSame(['CHAR(36)', 'NOT NULL'], $columns['uuid']);
        $this->assertSame(['TIMESTAMP', 'NULL'], $columns['deleted_at']);
        $this->assertSame(['INT UNSIGNED', 'NOT NULL'], $columns['score']);
        $this->assertSame(['POINT', 'NOT NULL'], $columns['geo']);
    }

    public function testRejectsUnsafeColumnType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Blueprint())->column('x', 'INT; DROP TABLE users');
    }

    public function testRejectsUnsafeIdentifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Blueprint::ident('users; drop table');
    }
}
