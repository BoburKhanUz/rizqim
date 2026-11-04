<?php

namespace Tests\Unit\PosLan;

use App\Domain\Pos\Lan\LanKeys;
use PHPUnit\Framework\TestCase;

/**
 * P6 local bridge: the server's key derivation and grant signature are pos_lan's byte for byte (the Dart test
 * pos/packages/pos_lan/test/crypto_test.dart pins the same vectors).
 */
class LanKeysTest extends TestCase
{
    private const DEVICE = '0f6a6c1e-2b7d-4c1a-9a51-3c2f1e0d9b88';

    private function secret(): string
    {
        return implode('', array_map('chr', range(0, 31)));
    }

    public function test_the_keys_match_the_dart_vectors(): void
    {
        $this->assertSame('8eeeaf0d18e62a6085f622deea049fdc1139322ce3bf301d5a2c493ef9a5554f', bin2hex(LanKeys::device($this->secret(), self::DEVICE)));
        $this->assertSame(bin2hex(LanKeys::device($this->secret(), self::DEVICE)), bin2hex(LanKeys::device($this->secret(), strtoupper(self::DEVICE))));
        $this->assertSame('aec44fd45366bb12d4141bcbb61a9bb00a46b8a53936798ede9ab26be5c01504', bin2hex(LanKeys::beacon($this->secret())));
        $this->assertSame('ab45536716e8567647eb795b765bffb5dc9a9c32e0ffd13a65c235b846979c95', bin2hex(LanKeys::grant($this->secret())));
    }

    public function test_a_grant_is_the_one_the_dart_test_verifies_and_a_tampered_one_is_not(): void
    {
        $grant = LanKeys::signGrant($this->secret(), [
            'v' => 1, 'kid' => 1, 'branch_id' => 12, 'device' => self::DEVICE, 'staff_id' => 4, 'name' => 'Botir Aliyev',
            'role' => 'waiter', 'permissions' => ['orders.view', 'orders.manage'], 'session' => 77, 'iat' => 1793700000, 'exp' => 1793829600,
        ]);
        $this->assertSame('uCxfjDnTdiPMgW_9tLa8NelGrF6KqO-3-nJs547yads', explode('.', $grant)[1]);
        $this->assertSame(4, LanKeys::verifyGrant($this->secret(), $grant)['staff_id'] ?? null);
        $this->assertNull(LanKeys::verifyGrant(str_repeat("\x01", 32), $grant));
        $this->assertNull(LanKeys::verifyGrant($this->secret(), 'x' . $grant));
        $this->assertNull(LanKeys::verifyGrant($this->secret(), 'nodot'));
    }
}
