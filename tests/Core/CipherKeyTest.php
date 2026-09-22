<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Cipher;
use PHPUnit\Framework\TestCase;

final class CipherKeyTest extends TestCase
{
    public function testHexKeyRoundTrip(): void
    {
        $cipher = new Cipher(bin2hex(random_bytes(32)));
        $encrypted = $cipher->encrypt('secret-data');

        $this->assertSame('secret-data', $cipher->decrypt($encrypted));
    }

    public function testBase64KeyRoundTrip(): void
    {
        $cipher = new Cipher(Cipher::generateKey());
        $encrypted = $cipher->encrypt('secret-data');

        $this->assertSame('secret-data', $cipher->decrypt($encrypted));
    }

    public function testFromEnvUsesEncryptionKey(): void
    {
        $_ENV['ENCRYPTION_KEY'] = bin2hex(random_bytes(32));
        $cipher = Cipher::fromEnv();

        $this->assertSame('hello', $cipher->decrypt($cipher->encrypt('hello')));
    }

    public function testEmptyKeyIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        new Cipher('');
    }
}
