<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Migrator;
use PHPUnit\Framework\TestCase;

final class MigratorRollbackTest extends TestCase
{
    /**
     * @return list<array{migration: string, batch: int}>
     */
    private function sampleRan(): array
    {
        return [
            ['migration' => '20260101000000_create_users.php', 'batch' => 1],
            ['migration' => '20260101010000_create_roles.php', 'batch' => 1],
            ['migration' => '20260102000000_add_phone.php', 'batch' => 2],
            ['migration' => '20260103000000_add_avatar.php', 'batch' => 3],
        ];
    }

    public function testDefaultRollbackUsesLastBatchOnly(): void
    {
        $this->assertSame(
            ['20260103000000_add_avatar.php'],
            Migrator::pickRollback($this->sampleRan()),
        );
    }

    public function testStepRollsBackLastNFilesAcrossBatches(): void
    {
        $this->assertSame(
            [
                '20260103000000_add_avatar.php',
                '20260102000000_add_phone.php',
            ],
            Migrator::pickRollback($this->sampleRan(), 2),
        );
    }

    public function testStepOneIsSingleLatestFile(): void
    {
        $this->assertSame(
            ['20260103000000_add_avatar.php'],
            Migrator::pickRollback($this->sampleRan(), 1),
        );
    }

    public function testBatchRollsBackThatSetNewestFirst(): void
    {
        $this->assertSame(
            [
                '20260101010000_create_roles.php',
                '20260101000000_create_users.php',
            ],
            Migrator::pickRollback($this->sampleRan(), null, 1),
        );
    }

    public function testStepTakesPriorityOverBatch(): void
    {
        $this->assertSame(
            ['20260103000000_add_avatar.php'],
            Migrator::pickRollback($this->sampleRan(), 1, 1),
        );
    }

    public function testUnknownBatchReturnsEmpty(): void
    {
        $this->assertSame([], Migrator::pickRollback($this->sampleRan(), null, 99));
    }

    public function testEmptyHistoryReturnsEmpty(): void
    {
        $this->assertSame([], Migrator::pickRollback([]));
    }

    public function testInvalidStepIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Migrator::pickRollback($this->sampleRan(), 0);
    }

    public function testInvalidBatchIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Migrator::pickRollback($this->sampleRan(), null, 0);
    }

    public function testPathOnlyRollsBackMatchingFileOutsideLastBatch(): void
    {
        $this->assertSame(
            ['20260101000000_create_users.php'],
            Migrator::pickRollback(
                $this->sampleRan(),
                null,
                null,
                ['20260101000000_create_users.php'],
                true,
            ),
        );
    }

    public function testPathFiltersLastBatchLikeLaravelDirectory(): void
    {
        $this->assertSame(
            [],
            Migrator::pickRollback(
                $this->sampleRan(),
                null,
                null,
                ['20260101000000_create_users.php'],
                false,
            ),
        );
    }

    public function testMatchPathNamesAcceptsShortTableName(): void
    {
        $available = [
            '20260101000000_create_users_table.php',
            '20260101010000_create_roles_table.php',
        ];

        $this->assertSame(
            ['20260101000000_create_users_table.php'],
            Migrator::matchPathNames($available, 'create_users_table'),
        );
        $this->assertSame(
            ['20260101000000_create_users_table.php'],
            Migrator::matchPathNames($available, 'users'),
        );
    }
}
