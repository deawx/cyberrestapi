<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Log;
use PHPUnit\Framework\TestCase;

final class LogPruneTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cyberrestapi-logs-' . bin2hex(random_bytes(8));
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (scandir($this->dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            unlink($this->dir . '/' . $name);
        }

        rmdir($this->dir);
    }

    public function testPruneRemovesLogsOlderThanRetention(): void
    {
        $stale = $this->dir . '/app_2026-08-01.log';
        $fresh = $this->dir . '/app_' . date('Y-m-d') . '.log';
        $keepGitkeep = $this->dir . '/.gitkeep';
        file_put_contents($stale, 'old');
        file_put_contents($fresh, 'today');
        file_put_contents($keepGitkeep, '');

        $removed = Log::pruneExpired($this->dir, 14);

        $this->assertSame(1, $removed);
        $this->assertFileDoesNotExist($stale);
        $this->assertFileExists($fresh);
        $this->assertFileExists($keepGitkeep);
    }

    public function testPruneRemovesUndatedErrorLogByMtime(): void
    {
        $stale = $this->dir . '/error.log';
        file_put_contents($stale, 'old');
        touch($stale, time() - (20 * 86400));

        $removed = Log::pruneExpired($this->dir, 14);

        $this->assertSame(1, $removed);
        $this->assertFileDoesNotExist($stale);
    }
}
