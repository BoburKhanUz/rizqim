<?php

namespace App\Domain\Pos\Menu;

use App\Domain\Pos\Setup\PosBootstrap;
use Illuminate\Support\ServiceProvider;

/**
 * P6-15: the daily menu, "keyingi partiya" and kompleks sections of GET /pos/bootstrap.
 */
class PosMenuServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([MenuBootstrapSection::class], PosBootstrap::CONTRIBUTORS);
    }
}
