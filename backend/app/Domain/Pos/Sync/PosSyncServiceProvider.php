<?php

namespace App\Domain\Pos\Sync;

use App\Domain\Pos\Setup\PosBootstrap;
use App\Models\ChannelPrice;
use App\Models\Device;
use App\Models\Modifier;
use App\Models\ProductVariant;
use App\Services\Catalog\PriceHistory;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * P3-31: the offline limited mode (docs/POS_SPEC.md §5): the `offline` section of GET /pos/bootstrap (what a till keeps
 * to sell through an outage) and the rate limit of POST /pos/sync (a drained queue after an hour comes in a few dozen
 * batches; per till). Review P3-35: the POS price history (PriceHistory) follows every price change.
 */
class PosSyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([OfflineBootstrapSection::class], PosBootstrap::CONTRIBUTORS);
    }

    public function boot(): void
    {
        // Review P3-35: every price a till may have sold at offline (PriceHistory)
        ProductVariant::saved(function (ProductVariant $variant) {
            if ($variant->wasRecentlyCreated || $variant->wasChanged('price')) {
                app(PriceHistory::class)->recordVariant((int) $variant->id);
            }
        });
        $channelPrice = function (ChannelPrice $row) {
            if ($row->product_variant_id !== null) {
                app(PriceHistory::class)->recordVariant((int) $row->product_variant_id);
            }
        };
        ChannelPrice::saved($channelPrice);
        ChannelPrice::deleted($channelPrice);
        Modifier::saved(function (Modifier $modifier) {
            if ($modifier->wasRecentlyCreated || $modifier->wasChanged('price_delta')) {
                app(PriceHistory::class)->recordModifier($modifier);
            }
        });

        RateLimiter::for('pos-sync', fn (Request $request) => Limit::perMinute(120)->by(
            ($device = $request->user('device')) instanceof Device ? 'pos-sync:' . $device->id : 'ip:' . $request->ip()
        ));
    }
}
