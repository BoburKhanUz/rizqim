<?php

namespace App\Domain\Fiscal;

use App\Models\Product;
use App\Models\Store;

/**
 * "MXIK'siz sotilmaydi": in fiscal mode every dish and service needs a valid MXIK code before it can be put on an
 * order (the POS order lines call assertSellable), and the panel lists what is still missing.
 */
class FiscalReadiness
{
    public function enabled(Store $store): bool
    {
        return FiscalSettings::of($store)->enabled();
    }

    /**
     * Throws FiscalException::MXIK_MISSING (422 in an API request) when the product's store sells fiscally and the
     * product has no valid 17-digit MXIK code; a store outside fiscal mode may sell anything.
     */
    public function assertSellable(Product $product, ?Store $store = null): void
    {
        $store ??= $product->relationLoaded('store') ? $product->store : Store::find($product->store_id);
        if (!$store instanceof Store || !$this->enabled($store)) {
            return;
        }

        if (!preg_match(FiscalSettings::MXIK_PATTERN, (string) $product->mxik_code)) {
            throw FiscalException::missingMxik((string) $product->name, (int) $product->id);
        }
    }

    /**
     * The store's products (active or not, deleted ones left out) without a valid MXIK code, by menu order.
     *
     * @return list<array{id: int, uuid: string, name: string, kind: string, status: string, category_id: int}>
     */
    public function missingMxik(Store $store): array
    {
        return Product::where('store_id', $store->id)
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'uuid', 'name', 'kind', 'status', 'category_id', 'mxik_code'])
            ->reject(fn (Product $product) => (bool) preg_match(FiscalSettings::MXIK_PATTERN, (string) $product->mxik_code))
            ->map(fn (Product $product) => [
                'id' => (int) $product->id,
                'uuid' => (string) $product->uuid,
                'name' => (string) $product->name,
                'kind' => (string) ($product->getAttributes()['kind'] ?? 'dish'),
                'status' => (string) $product->status,
                'category_id' => (int) $product->category_id,
            ])
            ->values()
            ->all();
    }

    /**
     * The charges whose receipt line has no MXIK code (they cannot be charged in fiscal mode).
     *
     * @return list<string>
     */
    public function chargesWithoutMxik(Store $store): array
    {
        $settings = FiscalSettings::of($store);

        return array_values(array_filter(FiscalSettings::CHARGES, fn (string $charge) => blank($settings->charge($charge)['mxik'])));
    }
}
