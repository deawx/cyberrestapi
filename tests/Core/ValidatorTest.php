<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testRequiredAndEmail(): void
    {
        $ok = Validator::make(
            ['email' => 'user@example.com'],
            ['email' => 'required|email'],
        );
        $this->assertFalse($ok->fails());
        $this->assertSame(['email' => 'user@example.com'], $ok->validated());

        $bad = Validator::make(
            ['email' => 'not-an-email'],
            ['email' => 'required|email'],
        );
        $this->assertTrue($bad->fails());
        $this->assertArrayHasKey('email', $bad->errors());
    }

    public function testNullableSkipsOtherRules(): void
    {
        $ok = Validator::make(
            [],
            ['bio' => 'nullable|email'],
        );

        $this->assertFalse($ok->fails());
    }

    public function testMinMaxAndIn(): void
    {
        $ok = Validator::make(
            ['name' => 'Deawx', 'role' => 'admin'],
            ['name' => 'required|min:3|max:20', 'role' => 'in:admin,user'],
        );
        $this->assertFalse($ok->fails());

        $bad = Validator::make(
            ['name' => 'ab', 'role' => 'guest'],
            ['name' => 'required|min:3', 'role' => 'in:admin,user'],
        );
        $this->assertTrue($bad->fails());
        $this->assertArrayHasKey('name', $bad->errors());
        $this->assertArrayHasKey('role', $bad->errors());
    }

    public function testConfirmed(): void
    {
        $ok = Validator::make(
            ['password' => 'secret12', 'password_confirmation' => 'secret12'],
            ['password' => 'required|min:8|confirmed'],
        );
        $this->assertFalse($ok->fails());

        $bad = Validator::make(
            ['password' => 'secret12', 'password_confirmation' => 'nope'],
            ['password' => 'required|confirmed'],
        );
        $this->assertTrue($bad->fails());
    }
}
