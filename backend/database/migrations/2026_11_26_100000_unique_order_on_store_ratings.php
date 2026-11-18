<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wave M4 H1: one rating per order, kept by the database (StoreRatingController checks it too, under the store's
     * row lock). The old endpoint let anyone cite any order: a rating whose order belongs to another user or another
     * store first loses the order link (else it would hold the real customer's order and refuse their rating for
     * good). Then the ratings that still repeat an order: the earliest keeps the order, the later ones lose the link.
     * Every rating keeps its stars (the store's aggregate does not move); none is deleted. Ratings without an order stay
     * as they are; PostgreSQL lets any number of NULLs past a unique index.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE store_ratings r SET order_id = NULL
            FROM orders o
            WHERE o.id = r.order_id AND (o.user_id <> r.user_id OR o.store_id <> r.store_id)
        SQL);

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
