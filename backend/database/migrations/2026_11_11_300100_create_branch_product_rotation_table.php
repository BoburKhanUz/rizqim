<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The daily rotation (which dishes are sold only while on the "Bugungi menyu") was a store-wide flag on `products`
     * while the menu itself is per branch, so one branch's manager could stop dishes in another branch. It moves to a
     * per-branch table; the current flags are copied to EVERY branch of the store, so nothing changes at the moment of
     * the migration, and the column is dropped.
     */
    public function up(): void
    {
        Schema::create('branch_product_rotation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
            $table->index('product_id');
        });

        if (Schema::hasColumn('products', 'daily_rotation')) {
            $now = now();
            DB::table('products')
                ->join('branches', 'branches.store_id', '=', 'products.store_id')
                ->where('products.daily_rotation', true)
                ->orderBy('branches.id')
                ->orderBy('products.id')
                ->select(['branches.id as branch_id', 'products.id as product_id'])
                ->chunk(500, function ($rows) use ($now) {
                    DB::table('branch_product_rotation')->insert($rows->map(fn ($row) => [
                        'branch_id' => $row->branch_id,
                        'product_id' => $row->product_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all());
                });

            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('daily_rotation');
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('daily_rotation')->default(false);
        });

        // A dish that rotates on any branch of its store rotates store-wide again
        DB::table('products')->whereIn('id', DB::table('branch_product_rotation')->select('product_id'))->update(['daily_rotation' => true]);

        Schema::dropIfExists('branch_product_rotation');
    }
};
