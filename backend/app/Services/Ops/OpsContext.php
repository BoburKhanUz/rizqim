<?php

namespace App\Services\Ops;

use App\Models\Store;

class OpsContext
{
    protected static ?int $storeId = null;

    /**
     * Explicitly set the active store ID for this request or process.
     */
    public static function setStoreId(?int $storeId): void
    {
        self::$storeId = $storeId;
    }

    /**
     * Reset the active store context to prevent memory leaks in long-running 
     * environments like Queue Workers, RoadRunner, or Octane.
     */
    public static function forget(): void
    {
        self::$storeId = null;
    }

    /**
     * Run a closure in an isolated scoped store context and safely reset it back.
     *
     * @param int|null $storeId
     * @param callable $callback
     * @return mixed
     */
    public static function run(?int $storeId, callable $callback): mixed
    {
        $previousStoreId = self::$storeId;
        self::setStoreId($storeId);

        try {
            return $callback();
        } finally {
            self::setStoreId($previousStoreId);
        }
    }

    /**
     * Get the active store ID, dynamically resolving from authenticated context or fallbacks.
     */
    public static function getStoreId(): ?int
    {
        if (self::$storeId !== null) {
            return self::$storeId;
        }

        // Auto-resolve from authenticated user's store
        if (auth()->check() && auth()->user()->store_id) {
            return auth()->user()->store_id;
        }

        // Fallback for CLI, seeders, or guest requests to first store
        return Store::first()?->id;
    }
}
