<?php

namespace App\Domain\Pos\Banquet;

use App\Models\BanquetEvent;
use App\Models\BanquetExtra;
use App\Models\BanquetHallRate;
use App\Models\BanquetPackage;
use App\Models\BanquetPackageItem;
use App\Models\Hall;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;

/**
 * P6-52: the store's banquet catalogue — packages (per-person price, minimum guests, what is included, menu items per
 * person), extras (per event or per person) and the hall rent per slot. Every change is audited. An edit never
 * reaches an event whose prices were frozen on the contract day (BanquetPricing); an unfrozen event takes it on its
 * next change. A package or an extra an event uses is archived (inactive) instead of deleted.
 */
class BanquetCatalog
{
    public const PACKAGE = 'array{id: int, uuid: string, name: string, per_person_price: string, minimum_guests: int, included: list<string>, description: string|null, is_active: bool, sort_order: int, items: list<array{id: int, product_id: int|null, product_variant_id: int|null, name: string, quantity_per_person: string, unit: string}>}';

    public const EXTRA = 'array{id: int, uuid: string, name: string, price: string, pricing: string, pricing_label: string, is_active: bool, sort_order: int}';

    public const HALL = 'array{hall_id: int, hall_name: string, branch_id: int, is_active: bool, rates: array{morning: string|null, day: string|null, evening: string|null, custom: string|null}}';

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function packageView(BanquetPackage $package): array
    {
        $package->loadMissing('items');

        return [
            'id' => (int) $package->id,
            'uuid' => (string) $package->uuid,
            'name' => $package->name,
            'per_person_price' => Money::of($package->per_person_price)->toString(),
            'minimum_guests' => (int) $package->minimum_guests,
            'included' => array_map('strval', $package->included ?? []),
            'description' => $package->description,
            'is_active' => (bool) $package->is_active,
            'sort_order' => (int) $package->sort_order,
            'items' => $package->items->map(fn (BanquetPackageItem $item) => [
                'id' => (int) $item->id,
                'product_id' => $item->product_id !== null ? (int) $item->product_id : null,
                'product_variant_id' => $item->product_variant_id !== null ? (int) $item->product_variant_id : null,
                'name' => $item->name,
                'quantity_per_person' => Quantity::of($item->quantity_per_person)->toString(),
                'unit' => $item->unit,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function extraView(BanquetExtra $extra): array
    {
        return [
            'id' => (int) $extra->id,
            'uuid' => (string) $extra->uuid,
            'name' => $extra->name,
            'price' => Money::of($extra->price)->toString(),
            'pricing' => $extra->pricing->value,
            'pricing_label' => $extra->pricing->label(),
            'is_active' => (bool) $extra->is_active,
            'sort_order' => (int) $extra->sort_order,
        ];
    }

    /**
     * The store's halls (optionally one branch) with their rent per slot.
     *
     * @return list<array<string, mixed>>
     */
    public function halls(int $storeId, ?int $branchId = null): array
    {
        $halls = Hall::where('store_id', $storeId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('branch_id')->orderBy('sort_order')->orderBy('id')
            ->get();
        $rates = BanquetHallRate::whereIn('hall_id', $halls->pluck('id')->all() ?: [0])->get()->groupBy('hall_id');

        return $halls->map(function (Hall $hall) use ($rates) {
            $byslot = ($rates->get($hall->id) ?? collect())->keyBy(fn (BanquetHallRate $rate) => $rate->slot->value);
            $out = [];
            foreach (BanquetSlot::cases() as $slot) {
                $rate = $byslot->get($slot->value);
                $out[$slot->value] = $rate !== null ? Money::of($rate->price)->toString() : null;
            }

            return [
                'hall_id' => (int) $hall->id,
                'hall_name' => (string) $hall->name,
                'branch_id' => (int) $hall->branch_id,
                'is_active' => (bool) $hall->is_active,
                'rates' => $out,
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function savePackage(int $storeId, ?BanquetPackage $package, array $data, User $actor): BanquetPackage
    {
        return DB::transaction(function () use ($storeId, $package, $data, $actor) {
            $creating = $package === null;
            $package ??= new BanquetPackage(['store_id' => $storeId]);
            $before = $creating ? [] : $this->packageView($package);

            foreach (['name', 'description', 'is_active', 'sort_order', 'minimum_guests'] as $field) {
                if (array_key_exists($field, $data)) {
                    $package->{$field} = $data[$field];
                }
            }
            if (array_key_exists('per_person_price', $data)) {
                $package->per_person_price = Money::of($data['per_person_price'])->toString();
            }
            if (array_key_exists('included', $data)) {
                $package->included = array_values(array_filter(array_map(fn ($line) => trim((string) $line), (array) ($data['included'] ?? [])), fn (string $line) => $line !== ''));
            }
            $package->save();

            if (array_key_exists('items', $data)) {
                $package->items()->delete();
                foreach (array_values((array) ($data['items'] ?? [])) as $i => $item) {
                    [$productId, $variantId, $name] = $this->menuItem($storeId, $item);
                    $package->items()->create([
                        'product_id' => $productId,
                        'product_variant_id' => $variantId,
                        'name' => $name,
                        'quantity_per_person' => Quantity::of($item['quantity_per_person'])->toString(),
                        'unit' => $item['unit'] ?? 'portion',
                        'sort_order' => $i,
                    ]);
                }
                $package->unsetRelation('items');
            }

            $this->audit->record($creating ? 'banquet.package_created' : 'banquet.package_updated', $package, $before, $this->packageView($package->refresh()), [], ['actor' => $actor, 'branch_id' => null]);

            return $package;
        });
    }

    /**
     * @return bool true when it was deleted, false when it was archived (an event uses it)
     */
    public function deletePackage(BanquetPackage $package, User $actor): bool
    {
        return DB::transaction(function () use ($package, $actor) {
            $before = $this->packageView($package);
            if (BanquetEvent::where('banquet_package_id', $package->id)->exists()) {
                $package->forceFill(['is_active' => false])->save();
                $this->audit->record('banquet.package_archived', $package, $before, $this->packageView($package), [], ['actor' => $actor, 'branch_id' => null]);

                return false;
            }
            $this->audit->record('banquet.package_deleted', $package, $before, [], [], ['actor' => $actor, 'branch_id' => null]);
            $package->delete();

            return true;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveExtra(int $storeId, ?BanquetExtra $extra, array $data, User $actor): BanquetExtra
    {
        return DB::transaction(function () use ($storeId, $extra, $data, $actor) {
            $creating = $extra === null;
            $extra ??= new BanquetExtra(['store_id' => $storeId]);
            $before = $creating ? [] : $this->extraView($extra);
            foreach (['name', 'pricing', 'is_active', 'sort_order'] as $field) {
                if (array_key_exists($field, $data)) {
                    $extra->{$field} = $data[$field];
                }
            }
            if (array_key_exists('price', $data)) {
                $extra->price = Money::of($data['price'])->toString();
            }
            $extra->save();
            $this->audit->record($creating ? 'banquet.extra_created' : 'banquet.extra_updated', $extra, $before, $this->extraView($extra->refresh()), [], ['actor' => $actor, 'branch_id' => null]);

            return $extra;
        });
    }

    public function deleteExtra(BanquetExtra $extra, User $actor): bool
    {
        return DB::transaction(function () use ($extra, $actor) {
            $before = $this->extraView($extra);
            if (DB::table('banquet_event_lines')->where('banquet_extra_id', $extra->id)->exists()) {
                $extra->forceFill(['is_active' => false])->save();
                $this->audit->record('banquet.extra_archived', $extra, $before, $this->extraView($extra), [], ['actor' => $actor, 'branch_id' => null]);

                return false;
            }
            $this->audit->record('banquet.extra_deleted', $extra, $before, [], [], ['actor' => $actor, 'branch_id' => null]);
            $extra->delete();

            return true;
        });
    }

    /**
     * Sets a hall's rent per slot (a null price removes that slot's rent).
     *
     * @param  array<string, string|null>  $rates  slot => price
     */
    public function saveHallRates(Hall $hall, array $rates, User $actor): void
    {
        DB::transaction(function () use ($hall, $rates, $actor) {
            $before = $this->halls((int) $hall->store_id, (int) $hall->branch_id);
            foreach ($rates as $slot => $price) {
                if ($price === null || $price === '') {
                    BanquetHallRate::where('hall_id', $hall->id)->where('slot', $slot)->delete();

                    continue;
                }
                BanquetHallRate::updateOrCreate(
                    ['hall_id' => $hall->id, 'slot' => $slot],
                    ['store_id' => $hall->store_id, 'price' => Money::of($price)->toString()],
                );
            }
            $beforeHall = collect($before)->firstWhere('hall_id', (int) $hall->id);
            $after = collect($this->halls((int) $hall->store_id, (int) $hall->branch_id))->firstWhere('hall_id', (int) $hall->id);
            $this->audit->record('banquet.hall_rates_updated', $hall, (array) ($beforeHall['rates'] ?? []), (array) ($after['rates'] ?? []), [], ['actor' => $actor]);
        });
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{0: int|null, 1: int|null, 2: string}
     */
    private function menuItem(int $storeId, array $item): array
    {
        $productId = null;
        $variantId = null;
        $name = isset($item['name']) ? trim((string) $item['name']) : '';
        if (isset($item['product_variant_id'])) {
            $variant = ProductVariant::whereKey((int) $item['product_variant_id'])->whereHas('product', fn ($query) => $query->where('store_id', $storeId))->with('product')->first();
            if ($variant === null) {
                throw new BanquetException('Taom topilmadi.', 'product_not_found', 422, ['product_variant_id' => (int) $item['product_variant_id']]);
            }
            $variantId = (int) $variant->id;
            $productId = (int) $variant->product_id;
            $name = $name !== '' ? $name : (string) $variant->product->name;
        } elseif (isset($item['product_id'])) {
            $product = Product::where('store_id', $storeId)->find((int) $item['product_id']);
            if ($product === null) {
                throw new BanquetException('Taom topilmadi.', 'product_not_found', 422, ['product_id' => (int) $item['product_id']]);
            }
            $productId = (int) $product->id;
            $name = $name !== '' ? $name : (string) $product->name;
        }
        if ($name === '') {
            throw new BanquetException('Taom nomini yozing yoki menyudan tanlang.', 'item_name_required', 422);
        }

        return [$productId, $variantId, $name];
    }
}
