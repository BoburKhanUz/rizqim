<?php

namespace App\Services\Catalog;

use App\Enums\OrderChannel;
use App\Models\ChannelPrice;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * A store's menu as one sales channel sees it: the variants' prices on that channel (a channel_prices override, else
 * the variant's own price) and what is hidden there. Built once per request from a single query.
 */
final class ChannelMenu
{
    /**
     * @param  array<int, true>  $hiddenProducts
     * @param  array<int, true>  $hiddenVariants
     * @param  array<int, string>  $variantPrices  variant id => decimal string
     */
    private function __construct(
        public readonly OrderChannel $channel,
        private readonly array $hiddenProducts,
        private readonly array $hiddenVariants,
        private readonly array $variantPrices,
    ) {}

    /**
     * @param  int|list<int>  $storeIds
     */
    public static function for(int|array $storeIds, OrderChannel $channel): self
    {
        $hiddenProducts = [];
        $hiddenVariants = [];
        $variantPrices = [];

        $rows = ChannelPrice::query()
            ->whereIn('store_id', (array) $storeIds)
            ->where('channel', $channel->value)
            ->get(['product_id', 'product_variant_id', 'price', 'is_hidden']);

        foreach ($rows as $row) {
            if ($row->product_variant_id === null) {
                if ($row->is_hidden) {
                    $hiddenProducts[(int) $row->product_id] = true;
                }
                continue;
            }

            if ($row->is_hidden) {
                $hiddenVariants[(int) $row->product_variant_id] = true;
            }
            if ($row->price !== null) {
                $variantPrices[(int) $row->product_variant_id] = (string) $row->price;
            }
        }

        return new self($channel, $hiddenProducts, $hiddenVariants, $variantPrices);
    }

    public function isProductHidden(Product|int $product): bool
    {
        return isset($this->hiddenProducts[$product instanceof Product ? (int) $product->id : $product]);
    }

    public function isVariantHidden(ProductVariant $variant): bool
    {
        return isset($this->hiddenVariants[(int) $variant->id]) || $this->isProductHidden((int) $variant->product_id);
    }

    /**
     * The variant's price per unit on this channel.
     */
    public function priceOf(ProductVariant $variant): Money
    {
        return Money::of($this->variantPrices[(int) $variant->id] ?? $variant->price);
    }

    /**
     * The products shown on this channel, their hidden variants dropped and each variant's `price` set to the
     * channel's (its own price stays in `base_price`; both additive for clients that only read `price`).
     *
     * @param  EloquentCollection<int, Product>  $products  with variants loaded
     * @return EloquentCollection<int, Product>
     */
    public function apply(EloquentCollection $products): EloquentCollection
    {
        return $products
            ->reject(fn (Product $product) => $this->isProductHidden($product))
            ->each(function (Product $product) {
                if (!$product->relationLoaded('variants')) {
                    return;
                }

                $variants = $product->variants
                    ->reject(fn (ProductVariant $variant) => $this->isVariantHidden($variant))
                    ->values()
                    ->each(function (ProductVariant $variant) {
                        $variant->setAttribute('base_price', $variant->price);
                        $variant->setAttribute('price', $this->priceOf($variant)->toString());
                    });

                $product->setRelation('variants', $variants);
            })
            ->values();
    }
}
