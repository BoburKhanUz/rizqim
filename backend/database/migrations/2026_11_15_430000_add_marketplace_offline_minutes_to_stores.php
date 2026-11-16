<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave M2 (store reachability, OFF by default): when set and config marketplace.reachability.enabled is on, a store
 * whose paired POS/KDS terminals have all been silent for this many minutes takes no marketplace orders
 * (App\Domain\Marketplace\StoreReachability). Null: the rule never applies to the store.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('stores', 'marketplace_offline_minutes')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->unsignedSmallInteger('marketplace_offline_minutes')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stores', 'marketplace_offline_minutes')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->dropColumn('marketplace_offline_minutes');
            });
        }
    }
};
