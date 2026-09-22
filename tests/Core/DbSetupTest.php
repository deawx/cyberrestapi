<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\DbSetup;
use PHPUnit\Framework\TestCase;

final class DbSetupTest extends TestCase
{
    public function testAssertDatabaseNameRejectsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DbSetup::assertDatabaseName('bad-name');
    }

    public function testAssertDatabaseNameAllowsUnderscore(): void
    {
        DbSetup::assertDatabaseName('cyber_fastapi');
        $this->assertTrue(true);
    }

    public function testAssertMysqlFamilyRejectsSqlite(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DbSetup::assertMysqlFamily('sqlite');
    }

    public function testFormatEnvValueQuotesSpecialChars(): void
    {
        $this->assertSame('plain', DbSetup::formatEnvValue('plain'));
        $this->assertSame('"a b"', DbSetup::formatEnvValue('a b'));
        $this->assertSame('', DbSetup::formatEnvValue(''));
    }

    public function testMergeConfigDefaults(): void
    {
        unset(
            $_ENV['DB_TYPE'],
            $_ENV['DB_HOST'],
            $_ENV['DB_PORT'],
            $_ENV['DB_NAME'],
            $_ENV['DB_USER'],
            $_ENV['DB_PASSWORD'],
            $_ENV['DB_CHARSET'],
            $_ENV['DB_COLLATION'],
        );

        $config = DbSetup::mergeConfig(['name' => 'demo_db']);

        $this->assertSame('mysql', $config['type']);
        $this->assertSame('127.0.0.1', $config['host']);
        $this->assertSame(3306, $config['port']);
        $this->assertSame('demo_db', $config['name']);
        $this->assertSame('utf8mb4_general_ci', $config['collation']);
    }

    public function testDefaultCollationMatchesCharset(): void
    {
        $this->assertSame('utf8mb4_general_ci', DbSetup::defaultCollation('utf8mb4'));
        $this->assertSame('utf8_general_ci', DbSetup::defaultCollation('utf8'));
    }

    public function testCollationForCharsetKeepsMatchingPreferred(): void
    {
        $this->assertSame(
            'utf8mb4_unicode_ci',
            DbSetup::collationForCharset('utf8mb4', 'utf8mb4_unicode_ci'),
        );
        $this->assertSame(
            'utf8_general_ci',
            DbSetup::collationForCharset('utf8', 'utf8mb4_general_ci'),
        );
    }

    public function testMergeConfigRealignsMismatchedCollation(): void
    {
        unset(
            $_ENV['DB_TYPE'],
            $_ENV['DB_HOST'],
            $_ENV['DB_PORT'],
            $_ENV['DB_NAME'],
            $_ENV['DB_USER'],
            $_ENV['DB_PASSWORD'],
            $_ENV['DB_CHARSET'],
            $_ENV['DB_COLLATION'],
        );

        $config = DbSetup::mergeConfig([
            'name' => 'demo_db',
            'charset' => 'utf8',
            'collation' => 'utf8mb4_general_ci',
        ]);

        $this->assertSame('utf8', $config['charset']);
        $this->assertSame('utf8_general_ci', $config['collation']);
    }

    public function testApplyEnvUpdatesSuperglobals(): void
    {
        $config = DbSetup::mergeConfig([
            'type' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3307,
            'name' => 'tmp_db',
            'user' => 'app',
            'password' => 'secret',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ]);

        DbSetup::applyEnv($config);

        $this->assertSame('tmp_db', $_ENV['DB_NAME']);
        $this->assertSame('3307', $_ENV['DB_PORT']);
        $this->assertSame('secret', $_ENV['DB_PASSWORD']);
    }
}
