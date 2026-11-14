<?php

namespace App\Domain\Pos\Brands;

use App\Enums\OrderChannel;
use App\Events\Broadcast\MenuAvailabilityChangedBroadcast;
use App\Models\Branch;
use App\Models\BranchBrand;
use App\Models\Brand;
use App\Models\BrandChannel;
use App\Models\Product;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\PriceHistory;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * P6-55 (module multi_brand): the owner's brands. Every change is audited; a brand's channel percent records the new
 * POS prices of its dishes (PriceHistory, so an offline till's price is still accepted), a branch switch is broadcast
 * like a stop-list change (menu.availability_changed). The menu reads them through BrandMenu.
 */
final class BrandService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, short_code: string, color?: string|null, logo_url?: string|null, is_active?: bool|null, sort_order?: int|null}  $data
     */
    public function create(int $storeId, array $data): Brand
    {
        $code = $this->code($storeId, $data['short_code']);

        return DB::transaction(function () use ($storeId, $data, $code) {
            $brand = Brand::create([
                'store_id' => $storeId,
                'name' => trim($data['name']),
                'short_code' => $code,
                'color' => strtoupper($data['color'] ?? '#64748B'),
                'logo_url' => $data['logo_url'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);
            $this->audit->record('brand.created', $brand, [], $this->auditable($brand), ['brand' => $brand->name]);

            return $brand;
        });
    }

    /**
     * @param  array<string, mixed>  $data  name, short_code, color, logo_url, is_active, sort_order (each optional)
     */
    public function update(Brand $brand, array $data): Brand
    {
        if (array_key_exists('short_code', $data)) {
            $data['short_code'] = $this->code((int) $brand->store_id, (string) $data['short_code'], (int) $brand->id);
        }
        if (isset($data['name'])) {
            $data['name'] = trim((string) $data['name']);
        }
        if (isset($data['color'])) {
            $data['color'] = strtoupper((string) $data['color']);
        }

        return DB::transaction(function () use ($brand, $data) {
            $before = $this->auditable($brand);
            $brand->fill(array_intersect_key($data, array_flip(['name', 'short_code', 'color', 'logo_url', 'is_active', 'sort_order'])))->save();
            $after = $this->auditable($brand);
            if ($before !== $after) {
                $this->audit->record('brand.updated', $brand, $before, $after, ['brand' => $brand->name]);
            }

            return $brand;
        });
    }

    /**
     * Deletes the brand; its dishes go back to the house brand (sold lines keep their snapshot).
     */
    public function delete(Brand $brand): void
    {
        DB::transaction(function () use ($brand) {
            $products = Product::withTrashed()->where('brand_id', $brand->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
            Product::withTrashed()->whereIn('id', $products)->update(['brand_id' => null]);
            $this->audit->record('brand.deleted', $brand, $this->auditable($brand), [], ['brand' => $brand->name, 'products' => count($products)]);
            $brand->delete();
            $this->recordPrices($products);
        });
    }

    /**
     * Replaces the brand's channel rules: {channel, is_hidden, price_percent}; a channel left out is visible at the
     * dishes' own prices.
     *
     * @param  list<array{channel: string, is_hidden?: bool|null, price_percent?: string|int|float|null}>  $rows
     */
    public function setChannels(Brand $brand, array $rows): Brand
    {
        $seen = [];
        $clean = [];
        foreach ($rows as $position => $row) {
            if (isset($seen[$row['channel']])) {
                throw ValidationException::withMessages(["channels.{$position}.channel" => 'Har kanal uchun bitta qator.']);
            }
            $seen[$row['channel']] = true;
            $percent = isset($row['price_percent']) ? Money::of((string) $row['price_percent'])->toString() : null;
            $hidden = (bool) ($row['is_hidden'] ?? false);
            if (!$hidden && ($percent === null || Money::of($percent)->isZero())) {
                continue; // the default
            }
            $clean[$row['channel']] = ['is_hidden' => $hidden, 'price_percent' => $hidden ? null : $percent];
        }
        ksort($clean);

        return DB::transaction(function () use ($brand, $clean) {
            $before = $this->channels($brand);
            BrandChannel::where('brand_id', $brand->id)->delete();
            foreach ($clean as $channel => $row) {
                BrandChannel::create(['store_id' => $brand->store_id, 'brand_id' => $brand->id, 'channel' => $channel] + $row);
            }
            $after = $this->channels($brand);
            if ($before !== $after) {
                $this->audit->record('brand.channels_changed', $brand, ['channels' => $before], ['channels' => $after], ['brand' => $brand->name]);
                $this->recordPrices(Product::withTrashed()->where('brand_id', $brand->id)->pluck('id')->map(fn ($id) => (int) $id)->all());
            }

            return $brand;
        });
    }

    /**
     * Switches the brand on or off on branches of its store (on: no row).
     *
     * @param  list<array{branch_id: int, is_active: bool}>  $rows
     */
    public function setBranches(Brand $brand, array $rows): Brand
    {
        $branches = Branch::where('store_id', $brand->store_id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($rows as $position => $row) {
            if (!in_array((int) $row['branch_id'], $branches, true)) {
                throw ValidationException::withMessages(["branches.{$position}.branch_id" => 'Filial topilmadi.']);
            }
        }

        return DB::transaction(function () use ($brand, $rows) {
            $before = $this->offBranches($brand);
            foreach ($rows as $row) {
                if ((bool) $row['is_active']) {
                    BranchBrand::where('brand_id', $brand->id)->where('branch_id', (int) $row['branch_id'])->delete();
                } else {
                    BranchBrand::updateOrCreate(['brand_id' => $brand->id, 'branch_id' => (int) $row['branch_id']], ['store_id' => $brand->store_id, 'is_active' => false]);
                }
            }
            $after = $this->offBranches($brand);
            if ($before !== $after) {
                $this->audit->record('brand.branches_changed', $brand, ['off_branches' => $before], ['off_branches' => $after], ['brand' => $brand->name]);
                foreach (array_unique(array_merge(array_diff($before, $after), array_diff($after, $before))) as $branchId) {
                    event(new MenuAvailabilityChangedBroadcast((int) $brand->store_id, (int) $branchId, [], 'brand'));
                }
            }

            return $brand;
        });
    }

    /**
     * Puts dishes of the store under a brand (null: the house brand).
     *
     * @param  list<int>  $productIds
     * @return int how many changed
     */
    public function assignProducts(int $storeId, ?Brand $brand, array $productIds): int
    {
        $products = Product::where('store_id', $storeId)->whereIn('id', $productIds)->get(['id', 'name', 'brand_id']);
        if ($products->count() !== count(array_unique($productIds))) {
            throw ValidationException::withMessages(['product_ids' => 'Mahsulot topilmadi.']);
        }

        $brandId = $brand !== null ? (int) $brand->id : null;
        $changed = $products->filter(fn (Product $product) => ($product->brand_id !== null ? (int) $product->brand_id : null) !== $brandId)->values();
        if ($changed->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($storeId, $brand, $brandId, $changed) {
            $ids = $changed->pluck('id')->map(fn ($id) => (int) $id)->all();
            Product::whereIn('id', $ids)->update(['brand_id' => $brandId]);
            $this->audit->record('brand.products_assigned', $brand, [
                'products' => $changed->map(fn (Product $product) => ['id' => (int) $product->id, 'brand_id' => $product->brand_id !== null ? (int) $product->brand_id : null])->all(),
            ], ['brand_id' => $brandId, 'product_ids' => $ids], ['brand' => $brand?->name, 'count' => count($ids)], ['store_id' => $storeId]);
            $this->recordPrices($ids);
        });

        return $changed->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Brand $brand): array
    {
        $brand->loadMissing('channels', 'branchSwitches');

        return [
            'id' => (int) $brand->id,
            'uuid' => (string) $brand->uuid,
            'name' => (string) $brand->name,
            'short_code' => (string) $brand->short_code,
            'color' => (string) $brand->color,
            'logo_url' => $brand->logo_url,
            'is_active' => (bool) $brand->is_active,
            'sort_order' => (int) $brand->sort_order,
            'products_count' => (int) ($brand->getAttribute('products_count') ?? $brand->products()->count()),
            'channels' => $this->channels($brand),
            'off_branch_ids' => $this->offBranches($brand),
        ];
    }

    /**
     * @return list<array{channel: string, is_hidden: bool, price_percent: string|null}>
     */
    private function channels(Brand $brand): array
    {
        return BrandChannel::where('brand_id', $brand->id)->orderBy('channel')->get()
            ->map(fn (BrandChannel $row) => ['channel' => $row->channel->value, 'is_hidden' => (bool) $row->is_hidden, 'price_percent' => $row->price_percent])
            ->values()->all();
    }

    /**
     * @return list<int>
     */
    private function offBranches(Brand $brand): array
    {
        return BranchBrand::where('brand_id', $brand->id)->where('is_active', false)->orderBy('branch_id')->pluck('branch_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function auditable(Brand $brand): array
    {
        return $brand->only(['name', 'short_code', 'color', 'logo_url', 'is_active', 'sort_order']);
    }

    private function code(int $storeId, string $code, ?int $except = null): string
    {
        $code = mb_strtoupper(trim($code));
        $taken = Brand::where('store_id', $storeId)->where('short_code', $code)->when($except !== null, fn ($query) => $query->whereKeyNot($except))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['short_code' => 'Bu qisqa kod boshqa brendda bor.']);
        }

        return $code;
    }

    /**
     * @param  list<int>  $productIds
     */
    private function recordPrices(array $productIds): void
    {
        $history = app(PriceHistory::class);
        foreach ($productIds as $productId) {
            $history->recordProduct($productId);
        }
    }

    /**
     * The channels a brand rule may name.
     *
     * @return list<string>
     */
    public static function channelValues(): array
    {
        return OrderChannel::values();
    }
}
