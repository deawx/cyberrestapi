<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Jwt;
use PHPUnit\Framework\TestCase;

final class JwtTest extends TestCase
{
    private string $secret;
    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secret = str_repeat('a', 32);
        $this->storage = sys_get_temp_dir() . '/cyberrestapi-jwt-' . bin2hex(random_bytes(4));
        Jwt::useStorage($this->storage);
    }

    protected function tearDown(): void
    {
        Jwt::useStorage(null);
        $this->removeDir($this->storage);
        parent::tearDown();
    }

    public function testCreateAndVerify(): void
    {
        $token = Jwt::create([
            'sub' => '7',
            'role' => 'admin',
        ], $this->secret, 60);

        $payload = Jwt::verify($token, $this->secret);

        $this->assertIsArray($payload);
        $this->assertSame('7', $payload['sub']);
        $this->assertSame('admin', $payload['role']);
        $this->assertSame('access', $payload['typ']);
        $this->assertNotEmpty($payload['jti']);
        $this->assertArrayHasKey('exp', $payload);
    }

    public function testVerifyRejectsTamperedToken(): void
    {
        $token = Jwt::create(['sub' => '1'], $this->secret, 60);

        $this->assertNull(Jwt::verify($token . 'x', $this->secret));
        $this->assertNull(Jwt::verify($token, str_repeat('b', 32)));
    }

    public function testIssueRefreshAndRevoke(): void
    {
        $pair = Jwt::issue([
            'sub' => '9',
            'role' => 'member',
            'email' => 'a@example.com',
            'username' => 'alice',
        ], $this->secret, 60, 3600);

        $this->assertNotSame('', $pair['access_token']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $pair['refresh_token']);
        $this->assertSame(60, $pair['expires_in']);

        $refreshed = Jwt::refresh($pair['refresh_token'], $this->secret, 60, 3600);
        $this->assertIsArray($refreshed);
        $this->assertNotSame($pair['access_token'], $refreshed['access_token']);
        $this->assertNotSame($pair['refresh_token'], $refreshed['refresh_token']);

        // หลัง refresh ทั้ง access และ refresh เก่าใช้ไม่ได้
        $this->assertNull(Jwt::verify($pair['access_token'], $this->secret));
        $this->assertNull(Jwt::refresh($pair['refresh_token'], $this->secret));

        $access = $refreshed['access_token'];
        $this->assertNotNull(Jwt::verify($access, $this->secret));
        $this->assertTrue(Jwt::revoke($access, $this->secret));
        $this->assertNull(Jwt::verify($access, $this->secret));
        $this->assertNull(Jwt::refresh($refreshed['refresh_token'], $this->secret));
    }

    public function testRevokeRefreshAlsoKillsAccess(): void
    {
        $pair = Jwt::issue(['sub' => '5', 'role' => 'member'], $this->secret, 60, 3600);
        $this->assertTrue(Jwt::revokeRefresh($pair['refresh_token']));
        $this->assertNull(Jwt::verify($pair['access_token'], $this->secret));
        $this->assertNull(Jwt::refresh($pair['refresh_token'], $this->secret));
    }

    public function testRevokePair(): void
    {
        $pair = Jwt::issue(['sub' => '3', 'role' => 'admin'], $this->secret, 60, 3600);
        Jwt::revokePair($pair['access_token'], $pair['refresh_token'], $this->secret);

        $this->assertNull(Jwt::verify($pair['access_token'], $this->secret));
        $this->assertNull(Jwt::refresh($pair['refresh_token'], $this->secret));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*') ?: [] as $item) {
            if (is_dir($item)) {
                $this->removeDir($item);
            } else {
                @unlink($item);
            }
        }
        @rmdir($dir);
    }
}
