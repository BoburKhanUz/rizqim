<?php

namespace App\Domain\Pos\Shifts;

use App\Domain\Pos\Setup\PosBootstrapContributor;
use App\Models\Branch;
use App\Models\Device;
use App\Models\User;

/**
 * P6-13: the `queue` section of GET /pos/bootstrap: whether the branch numbers its counter orders (`numbers`, the
 * `queue_numbers` module) and calls them (`calling`, `queue_calling`), and this till's number blocks of today — a
 * till running low (or with none yet) gets its next block here (QueueNumbers::state). The till numbers its orders from
 * them, online (sending `queue_number` with POST /pos/orders) and offline (`queue_number` in order.open), never
 * reusing a number of the day; no block left: the order goes without a number and the server gives one.
 */
final class QueueBootstrapSection implements PosBootstrapContributor
{
    public function __construct(
        private readonly QueueNumbers $numbers,
    ) {}

    /**
     * @return array{queue: array<string, mixed>}
     */
    public function contribute(User $user, Branch $branch, ?Device $device): array
    {
        return ['queue' => $this->numbers->state($branch, $device, true)];
    }
}
