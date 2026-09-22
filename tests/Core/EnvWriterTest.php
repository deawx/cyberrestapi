<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Cipher;
use Core\Console\KeyGenerateCommand;
use Core\EnvWriter;
use PHPUnit\Framework\TestCase;

final class EnvWriterTest extends TestCase
{
    private string $env;

    protected function setUp(): void
    {
        $this->env = sys_get_temp_dir() . '/cyberrestapi-env-' . bin2hex(random_bytes(4));
        file_put_contents($this->env, "APP_NAME=Demo\nENCRYPTION_KEY=\nJWT_SECRET=\n");
    }

    protected function tearDown(): void
    {
        if (is_file($this->env)) {
            unlink($this->env);
        }
    }

    public function testSetReplacesEmptyKeys(): void
    {
        EnvWriter::set($this->env, 'ENCRYPTION_KEY', 'abc');
        EnvWriter::set($this->env, 'JWT_SECRET', 'def');

        $this->assertSame('abc', EnvWriter::read($this->env, 'ENCRYPTION_KEY'));
        $this->assertSame('def', EnvWriter::read($this->env, 'JWT_SECRET'));
        $this->assertTrue(EnvWriter::isFilled($this->env, 'ENCRYPTION_KEY'));
        $this->assertStringContainsString('APP_NAME=Demo', (string) file_get_contents($this->env));
    }

    public function testEmptyKeyIsNotFilled(): void
    {
        $this->assertFalse(EnvWriter::isFilled($this->env, 'ENCRYPTION_KEY'));
        $this->assertSame('', EnvWriter::read($this->env, 'ENCRYPTION_KEY'));
    }

    public function testAppendsMissingKey(): void
    {
        EnvWriter::set($this->env, 'SESSION_PREFIX', 'app_');

        $this->assertSame('app_', EnvWriter::read($this->env, 'SESSION_PREFIX'));
    }

    public function testGeneratedKeysWorkWithCipher(): void
    {
        $keys = KeyGenerateCommand::makeKeys();

        $this->assertSame(64, strlen($keys['ENCRYPTION_KEY']));
        $this->assertSame(64, strlen($keys['JWT_SECRET']));

        $cipher = new Cipher($keys['ENCRYPTION_KEY']);
        $this->assertSame('ready', $cipher->decrypt($cipher->encrypt('ready')));
    }
}
