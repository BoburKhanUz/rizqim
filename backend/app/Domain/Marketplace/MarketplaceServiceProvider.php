<?php

namespace App\Domain\Marketplace;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wave M2 marketplace read model: the hooks of its own two revisions (MarketplaceRevision::directory/global).
 *
 * Per-store documents need no hook here: they are keyed by ChangeRevision::store, which App\Domain\Realtime touches on
 * every write to a menu, stop-list, store or branch table (RevisionMap). The area and global documents span many
 * stores, so they listen to the writes themselves (MarketplaceWrites): any insert, update or delete of the tables
 * they show, Eloquent or not, touches their revision after the transaction commits.
 */
final class MarketplaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One per application (per request under FPM): the cache-down backoff is shared by every read of the request
        $this->app->singleton(ReadModelCache::class);
        $this->app->singleton(MarketplaceRevision::class);
        $this->app->singleton(MarketplaceWrites::class);
    }

    public function boot(): void
    {
        Event::listen(QueryExecuted::class, function (QueryExecuted $query) {
            $this->app->make(MarketplaceWrites::class)->seen($query->sql);
        });
    }
}
