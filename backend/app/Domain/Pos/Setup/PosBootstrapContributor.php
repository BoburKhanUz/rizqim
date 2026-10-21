<?php

namespace App\Domain\Pos\Setup;

use App\Models\Branch;
use App\Models\Device;
use App\Models\User;

/**
 * Adds sections to GET /pos/bootstrap without editing it: halls and tables (P3-12), the current shift (P3-18). Bind an
 * implementation and tag it `PosBootstrap::CONTRIBUTORS` in a service provider; the keys it returns are merged into the
 * response's `data` (they replace the placeholders `halls`, `tables` and `shift`). A contributor runs for a branch the
 * caller already has access to and answers for that branch only.
 */
interface PosBootstrapContributor
{
    /**
     * @return array<string, mixed> top-level keys of `data`, e.g. ['halls' => [...], 'tables' => [...]]
     */
    public function contribute(User $user, Branch $branch, ?Device $device): array;
}
