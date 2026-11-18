<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wave M4 H1: one rating per order, kept by the database (StoreRatingController checks it too, under the store's
     * row lock). Ratings written before this rule may repeat an order: the earliest keeps the order, the later ones
     * keep their stars (the store's aggregate does not move) but lose the order link. Ratings without an order (the old
     * endpoint allowed them) stay as they are; PostgreSQL lets any number of NULLs past a unique index.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE store_ratings SET order_id = NULL
            WHERE order_id IS NOT NULL
              AND id <> (SELECT MIN(r.id) FROM store_ratings r WHERE r.order_id = store_ratings.order_id)
        SQL);

        Schema::table('store_ratings', function (Blueprint $table) {
            $table->unique('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('store_ratings', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
        });
    }
};
