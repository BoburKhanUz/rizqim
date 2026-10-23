<?php

namespace App\Services\Catalog;

use App\Domain\Pos\Shifts\OrderStamp;
use App\Enums\OrderChannel;
use App\Models\ChannelPrice;
use App\Models\MenuPriceHistory;
use App\Models\Modifier;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Review P3-35: the prices a till may have sold at offline (docs/POS_SPEC.md §5.3). Every change of what a variant
 * costs on a POS channel (its own price, or the channel's price: ChannelPrice) and of a modifier's price_delta is kept
 * in menu_price_history (written by the models' events, PosSyncServiceProvider). At sync a device's price is kept only
 * when the variant or modifier had it at some moment between the device's menu snapshot and now; else the server's
 * price applies (PosLineBuilder::addOffline).
 */
final class PriceHistory
{
    /**
     * Records the variant's price on each POS channel when it differs from the last one recorded.
     */
    public function recordVariant(int $variantId): void
    {
        $variant = ProductVariant::withTrashed()->with(['product' => fn ($query) => $query->withTrashed()])->find($variantId);
        if ($variant === null || $variant->product === null) {
            return;
        }

        $overrides = ChannelPrice::where('product_variant_id', $variant->id)
            ->whereIn('channel', array_map(fn (OrderChannel $channel) => $channel->value, OrderStamp::CHANNELS))
            ->whereNotNull('price')
            ->get(['channel', 'price'])
            ->keyBy(fn (ChannelPrice $row) => $row->channel->value);

        foreach (OrderStamp::CHANNELS as $channel) {
            $price = Money::of($overrides->get($channel->value)->price ?? $variant->price);
            $last = MenuPriceHistory::where('product_variant_id', $variant->id)->where('channel', $channel->value)
                ->orderByDesc('effective_from')->orderByDesc('id')->value('price');
            if ($last !== null && $price->equals((string) $last)) {
                continue;
            }

            MenuPriceHistory::create([
                'store_id' => $variant->product->store_id,
                'product_variant_id' => $variant->id,
                'channel' => $channel->value,
                'price' => $price->toString(),
                'effective_from' => now(),
            ]);
        }
    }

    public function recordProduct(int $productId): void
    {
        foreach (ProductVariant::withTrashed()->where('product_id', $productId)->pluck('id') as $variantId) {
            $this->recordVariant((int) $variantId);
        }
    }

    public function recordModifier(Modifier $modifier): void
    {
        $storeId = $modifier->group()->withTrashed()->value('store_id');
        if ($storeId === null) {
            return;
        }

        $price = Money::of($modifier->price_delta);
        $last = MenuPriceHistory::where('modifier_id', $modifier->id)->orderByDesc('effective_from')->orderByDesc('id')->value('price');
        if ($last !== null && $price->equals((string) $last)) {
            return;
        }

        MenuPriceHistory::create([
            'store_id' => $storeId,
            'modifier_id' => $modifier->id,
            'price' => $price->toString(),
            'effective_from' => now(),
        ]);
    }

    /**
     * The prices each variant had on the channel from $from until now: the one in effect at $from (the oldest known
     * when nothing was recorded before it) and every later one.
     *
     * @param  list<int>  $variantIds
     * @return array<int, list<Money>>
     */
    public function variantPrices(array $variantIds, OrderChannel $channel, Carbon $from): array
    {
        $rows = $variantIds === [] ? collect() : MenuPriceHistory::whereIn('product_variant_id', $variantIds)
            ->where('channel', $channel->value)
            ->orderBy('effective_from')
            ->orderBy('id')
            ->get(['product_variant_id', 'price', 'effective_from'])
            ->groupBy('product_variant_id');

        $prices = [];
        foreach ($variantIds as $id) {
            $prices[$id] = $this->window($rows->get($id, collect())->all(), $from);
        }

        return $prices;
    }

    /**
     * @param  list<int>  $modifierIds
     * @return array<int, list<Money>>
     */
    public function modifierPrices(array $modifierIds, Carbon $from): array
    {
        $rows = $modifierIds === [] ? collect() : MenuPriceHistory::whereIn('modifier_id', $modifierIds)
            ->orderBy('effective_from')
            ->orderBy('id')
            ->get(['modifier_id', 'price', 'effective_from'])
            ->groupBy('modifier_id');

        $prices = [];
        foreach ($modifierIds as $id) {
            $prices[$id] = $this->window($rows->get($id, collect())->all(), $from);
        }

        return $prices;
    }

    /**
     * @param  list<MenuPriceHistory>  $rows  oldest first
     * @return list<Money>
     */
    private function window(array $rows, Carbon $from): array
    {
        $start = 0;
        foreach ($rows as $i => $row) {
            if ($row->effective_from->lte($from)) {
                $start = $i;
            }
        }

        return array_map(fn (MenuPriceHistory $row) => Money::of($row->price), array_slice($rows, $start));
    }
}
