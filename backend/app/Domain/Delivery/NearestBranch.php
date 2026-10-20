<?php

namespace App\Domain\Delivery;

use App\Models\Branch;

final class NearestBranch
{
    public function __construct(
        public readonly Branch $branch,
        public readonly float $distanceKm,
    ) {}
}
