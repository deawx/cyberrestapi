<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Paginator;
use PHPUnit\Framework\TestCase;

final class PaginatorTest extends TestCase
{
    public function testMetaOnEmptyResult(): void
    {
        $meta = Paginator::meta(0, 1, 15);

        $this->assertSame(0, $meta['total']);
        $this->assertSame(0, $meta['from']);
        $this->assertSame(0, $meta['to']);
        $this->assertSame(1, $meta['last_page']);
    }

    public function testMetaOnLastPartialPage(): void
    {
        $meta = Paginator::meta(23, 2, 10);

        $this->assertSame(11, $meta['from']);
        $this->assertSame(20, $meta['to']);
        $this->assertSame(3, $meta['last_page']);
    }
}
