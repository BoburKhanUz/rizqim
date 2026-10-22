<?php

namespace Tests\Feature\PosBooking;

use App\Domain\Pos\Contracts\ManagerApprovals;

/**
 * ManagerApprovals for the P3-17 tests (P3-18 owns the real one): tokens granted with grant(), each usable once, for
 * one permission and (when bound) one device.
 */
class FakeManagerApprovals implements ManagerApprovals
{
    /** @var array<string, array{user_id: int, permission: string, device_id: int|null}> */
    private array $tokens = [];

    /** @var list<array{token: string, permission: string, device_id: int|null}> */
    public array $consumed = [];

    public function grant(int $userId, string $permission, ?int $deviceId = null): string
    {
        $token = 'approval-' . bin2hex(random_bytes(6));
        $this->tokens[$token] = ['user_id' => $userId, 'permission' => $permission, 'device_id' => $deviceId];

        return $token;
    }

    public function consume(string $token, string $permission, ?int $deviceId): int
    {
        $grant = $this->tokens[$token] ?? null;
        if ($grant === null || $grant['permission'] !== $permission || ($grant['device_id'] !== null && $grant['device_id'] !== $deviceId)) {
            throw new \RuntimeException('Tasdiq yaroqsiz.');
        }

        unset($this->tokens[$token]);
        $this->consumed[] = ['token' => $token, 'permission' => $permission, 'device_id' => $deviceId];

        return $grant['user_id'];
    }
}
