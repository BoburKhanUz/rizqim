<?php

namespace Tests\Unit;

use App\Services\Admin\System\SecretMask;
use PHPUnit\Framework\TestCase;

/** Review W3 S#6: the last 4 characters are shown only for a secret of 16+ characters (4 of 8 is half of it). */
class SecretMaskTest extends TestCase
{
    public function test_hint_needs_sixteen_characters(): void
    {
        $this->assertSame(['configured' => false, 'last4' => null], SecretMask::describe(null));
        $this->assertSame(['configured' => false, 'last4' => null], SecretMask::describe('  '));
        $this->assertSame(['configured' => true, 'last4' => null], SecretMask::describe('12345678'));
        $this->assertSame(['configured' => true, 'last4' => null], SecretMask::describe('123456789012345'));
        $this->assertSame(['configured' => true, 'last4' => '3456'], SecretMask::describe('1234567890123456'));
    }

    public function test_hint_can_be_turned_off(): void
    {
        $this->assertSame(['configured' => true, 'last4' => null], SecretMask::describe('rizqim-api-login-4321', false));
    }
}
