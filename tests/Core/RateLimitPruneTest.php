<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\RateLimit;
use PHPUnit\Framework\TestCase;

final class RateLimitPruneTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cyberrestapi-rate-' . bin2hex(random_bytes(8));
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*.json') ?: [] as $path) {
            unlink($path);
        }

        rmdir($this->dir);
    }

    public function testPruneRemovesOnlyExpiredFiles(): void
    {
        $stale = $this->dir . '/stale.json';
        $fresh = $this->dir . '/fresh.json';
        file_put_contents($stale, '[]');
        file_put_contents($fresh, '[]');
        touch($stale, time() - 120);
        touch($fresh, time() - 5);

        $removed = RateLimit::pruneExpired($this->dir, 60);

        $this->assertSame(1, $removed);
        $this->assertFileDoesNotExist($stale);
        $this->assertFileExists($fresh);
    }

    public function testPruneOnMissingDirectoryReturnsZero(): void
    {
        $this->assertSame(0, RateLimit::pruneExpired($this->dir . '/missing', 60));
    }
}
