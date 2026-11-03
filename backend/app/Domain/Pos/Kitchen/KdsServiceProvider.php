<?php

namespace App\Domain\Pos\Kitchen;

use App\Domain\Pos\Contracts\AssemblyTracker;
use Illuminate\Support\ServiceProvider;

/**
 * P6-33 (KDS v2): binds the AssemblyTracker contract the order core asks (the expo station, module `kds_expo`). A new
 * instance per resolve: what it reads is kept for that one change or ticket listing only.
 */
class KdsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AssemblyTracker::class, KitchenAssembly::class);
    }
}
