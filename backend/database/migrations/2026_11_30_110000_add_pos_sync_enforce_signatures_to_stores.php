<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave M5 S3-d (docs/scale/M5_DESIGN.md §4, owner answer 4): the store's "enforce" switch of the signed offline queue
 * (App\Domain\Pos\Sync\SignaturePolicy). False (observe): what a signature was worth is only recorded. True: an
 * unsigned or badly signed operation that is not money is rejected, a payment is recorded as `payment_unverified`.
 * One boolean with a constant default: no table rewrite, safe on a live database. Reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->boolean('pos_sync_enforce_signatures')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('pos_sync_enforce_signatures');
        });
    }
};
