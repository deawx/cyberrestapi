<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Security;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersTest extends TestCase
{
    /** @var array<string, string> */
    private array $cspBackup = [];

    protected function setUp(): void
    {
        $this->cspBackup = Security::cspDirectives();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        unset(
            $_ENV['APP_ENV'],
            $_ENV['CSP_ENABLED'],
            $_ENV['CSP_REPORT_ONLY'],
            $_ENV['HSTS_ENABLED'],
        );
        foreach ($this->cspBackup as $directive => $value) {
            Security::setCspDirective($directive, $value);
        }
        parent::tearDown();
    }

    public function testCspAutoOffInDevOnElsewhere(): void
    {
        unset($_ENV['CSP_ENABLED']);
        $_ENV['APP_ENV'] = 'dev';
        $this->assertFalse(Security::cspEnabled());

        $_ENV['APP_ENV'] = 'staging';
        $this->assertTrue(Security::cspEnabled());

        $_ENV['APP_ENV'] = 'prod';
        $this->assertTrue(Security::cspEnabled());
    }

    public function testCspCanForceOnInDev(): void
    {
        $_ENV['APP_ENV'] = 'dev';
        $_ENV['CSP_ENABLED'] = 'true';
        $this->assertTrue(Security::cspEnabled());
    }

    public function testCspCanForceOffInProd(): void
    {
        $_ENV['APP_ENV'] = 'prod';
        $_ENV['CSP_ENABLED'] = 'false';
        $this->assertFalse(Security::cspEnabled());
    }

    public function testHstsAutoOnlyProd(): void
    {
        unset($_ENV['HSTS_ENABLED']);
        $_ENV['APP_ENV'] = 'staging';
        $this->assertFalse(Security::hstsEnabled());

        $_ENV['APP_ENV'] = 'prod';
        $this->assertTrue(Security::hstsEnabled());

        $_ENV['HSTS_ENABLED'] = 'true';
        $_ENV['APP_ENV'] = 'staging';
        $this->assertTrue(Security::hstsEnabled());
    }

    public function testCspReportOnlyFlag(): void
    {
        unset($_ENV['CSP_REPORT_ONLY']);
        $this->assertFalse(Security::cspReportOnly());

        $_ENV['CSP_REPORT_ONLY'] = 'true';
        $this->assertTrue(Security::cspReportOnly());
    }

    public function testSetCspDirective(): void
    {
        Security::setCspDirective('script-src', "'self'");
        $this->assertSame("'self'", Security::cspDirectives()['script-src']);
    }
}
