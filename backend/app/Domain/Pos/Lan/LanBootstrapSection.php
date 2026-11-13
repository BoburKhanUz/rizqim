<?php

namespace App\Domain\Pos\Lan;

use App\Domain\Pos\Setup\PosBootstrapContributor;
use App\Models\Branch;
use App\Models\Device;
use App\Models\User;

/**
 * P6 local bridge: the `lan` section of GET /pos/bootstrap (BranchLan::section): a PIN session on a terminal of the
 * branch gets its keys, the hub and its signed offline grant; the hub also its secret and the terminals it lets in. A
 * normal staff or owner sign-in (no terminal) and another branch's terminal get null.
 */
final class LanBootstrapSection implements PosBootstrapContributor
{
    public const SECTION = BranchLan::SECTION . '|null';

    public function __construct(
        private readonly BranchLan $lan,
    ) {}

    /**
     * @return array{lan: array<string, mixed>|null}
     */
    public function contribute(User $user, Branch $branch, ?Device $device): array
    {
        return ['lan' => $this->lan->section($user, $branch, $device)];
    }
}
