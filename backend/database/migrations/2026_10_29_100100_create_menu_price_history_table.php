<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Review P3-35: the prices a till may have sold at offline. Each row: from `effective_from` on, a variant cost `price`
 * on a POS channel (pos_hall, pos_takeaway: its channel price, else its own price), or a modifier added
 * `price_delta` (channel null). A device price at sync is kept only when it is one of these within the device's menu
 * snapshot window (MenuPriceHistory). Seeded with today's prices, so the first change after the deploy has a
 * starting point.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 32)->nullable(); // pos_hall | pos_takeaway; null for a modifier
            $table->decimal('price', 12, 2);
            $table->timestamp('effective_from');
            $table->timestamp('created_at')->nullable();

            $table->index(['product_variant_id', 'channel', 'effective_from']);
            $table->index(['modifier_id', 'effective_from']);
        });

        $now = now();
        $channels = ['pos_hall', 'pos_takeaway'];
        DB::table('product_variants')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereNull('product_variants.deleted_at')
            ->select(['product_variants.id', 'product_variants.price', 'products.store_id'])
            ->orderBy('product_variants.id')
            ->chunk(500, function ($variants) use ($now, $channels) {
                $overrides = DB::table('channel_prices')
                    ->whereIn('product_variant_id', $variants->pluck('id'))
                    ->whereIn('channel', $channels)
                    ->whereNotNull('price')
                    ->get(['product_variant_id', 'channel', 'price'])
                    ->keyBy(fn ($row) => $row->product_variant_id . ':' . $row->channel);

                $rows = [];
                foreach ($variants as $variant) {
                    foreach ($channels as $channel) {
                        $rows[] = [
                            'store_id' => $variant->store_id,
                            'product_variant_id' => $variant->id,
                            'channel' => $channel,
                            'price' => $overrides->get($variant->id . ':' . $channel)->price ?? $variant->price,
                            'effective_from' => $now,
                            'created_at' => $now,
                        ];
                    }
                }
                DB::table('menu_price_history')->insert($rows);
            });

        DB::table('modifiers')
            ->join('modifier_groups', 'modifier_groups.id', '=', 'modifiers.modifier_group_id')
            ->whereNull('modifiers.deleted_at')
            ->select(['modifiers.id', 'modifiers.price_delta', 'modifier_groups.store_id'])
            ->orderBy('modifiers.id')
            ->chunk(500, function ($modifiers) use ($now) {
                DB::table('menu_price_history')->insert($modifiers->map(fn ($modifier) => [
                    'store_id' => $modifier->store_id,
                    'modifier_id' => $modifier->id,
                    'price' => $modifier->price_delta,
                    'effective_from' => $now,
                    'created_at' => $now,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_price_history');
    }
};
