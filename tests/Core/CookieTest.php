<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Cookie;
use PHPUnit\Framework\TestCase;

final class CookieTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['APP_ENV'], $_ENV['COOKIE_SECURE'], $_ENV['COOKIE_SAMESITE']);
        parent::tearDown();
    }

    public function testSecureAutoFollowsProd(): void
    {
        $_ENV['APP_ENV'] = 'prod';
        unset($_ENV['COOKIE_SECURE']);
        $this->assertTrue(Cookie::secureDefault());

        $_ENV['APP_ENV'] = 'dev';
        $this->assertFalse(Cookie::secureDefault());
    }

    public function testSecureCanBeForced(): void
    {
        $_ENV['APP_ENV'] = 'dev';
        $_ENV['COOKIE_SECURE'] = 'true';
        $this->assertTrue(Cookie::secureDefault());

        $_ENV['APP_ENV'] = 'prod';
        $_ENV['COOKIE_SECURE'] = 'false';
        $this->assertFalse(Cookie::secureDefault());
    }

    public function testSameSiteDefaultsAndNormalizes(): void
    {
        unset($_ENV['COOKIE_SAMESITE']);
        $this->assertSame('Strict', Cookie::sameSiteDefault());

        $_ENV['COOKIE_SAMESITE'] = 'lax';
        $this->assertSame('Lax', Cookie::sameSiteDefault());

        $_ENV['COOKIE_SAMESITE'] = 'none';
        $this->assertSame('None', Cookie::sameSiteDefault());
    }

    public function testSessionParamsAlwaysHttpOnly(): void
    {
        $_ENV['APP_ENV'] = 'prod';
        $_ENV['COOKIE_SAMESITE'] = 'Lax';
        $params = Cookie::sessionParams(0);

        $this->assertTrue($params['httponly']);
        $this->assertTrue($params['secure']);
        $this->assertSame('Lax', $params['samesite']);
        $this->assertSame('/', $params['path']);
    }

    public function testDefaultsMergeSecureSameSite(): void
    {
        $_ENV['APP_ENV'] = 'dev';
        $_ENV['COOKIE_SECURE'] = 'true';
        $_ENV['COOKIE_SAMESITE'] = 'Strict';
        $defaults = Cookie::defaults();

        $this->assertTrue($defaults['httponly']);
        $this->assertTrue($defaults['secure']);
        $this->assertSame('Strict', $defaults['samesite']);
    }
}
