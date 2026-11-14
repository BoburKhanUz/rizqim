<?php

namespace App\Domain\Pos\Brands;

use App\Enums\OrderChannel;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * P6-55 (module multi_brand): what the brands change on the menu, read by the existing pipeline so a store without
 * brands sees nothing new:
 *
 *  - ChannelMenu (catalogue, checkout, the till): a dish of an inactive brand, or of a brand hidden on the channel
 *    (brand_channels.is_hidden), is hidden there; a brand's `price_percent` on the channel adjusts its dishes'
 *    prices there (round half up to whole so'm) unless the variant has its own channel price (that one wins).
 *  - MenuAvailability / OrderLineBuilder::stopList: a brand switched off on a branch (branch_brands) stops its dishes
 *    there like a stop-list entry ("<brand> bu filialda o'chirilgan").
 */
final class BrandMenu
{
    /**
     * The dishes the brands hide on the channel, and the price percent of the dishes whose brand has one there.
     *
     * @param  list<int>  $storeIds
     * @return array{hidden: array<int, true>, percents: array<int, string>}
     */
    public static function channelRules(array $storeIds, OrderChannel $channel): array
    {
        $rows = DB::table('products')
            ->join('brands', 'brands.id', '=', 'products.brand_id')
            ->leftJoin('brand_channels', fn ($join) => $join->on('brand_channels.brand_id', '=', 'brands.id')->where('brand_channels.channel', '=', $channel->value))
            ->whereIn('products.store_id', $storeIds)
            ->where(fn ($query) => $query->where('brands.is_active', false)
                ->orWhere('brand_channels.is_hidden', true)
                ->orWhereNotNull('brand_channels.price_percent'))
            ->get(['products.id', 'brands.is_active', 'brand_channels.is_hidden', 'brand_channels.price_percent']);

        $hidden = [];
        $percents = [];
        foreach ($rows as $row) {
            if (!(bool) $row->is_active || (bool) $row->is_hidden) {
                $hidden[(int) $row->id] = true;
            } elseif ($row->price_percent !== null) {
                $percents[(int) $row->id] = (string) $row->price_percent;
            }
        }

        return ['hidden' => $hidden, 'percents' => $percents];
    }

    /**
     * A price adjusted by a brand's channel percent: price × (100 + percent) / 100, half up to whole so'm.
     */
    public static function adjust(Money $price, string $percent): Money
    {
        return $price->plus($price->percent($percent))->roundTo(0);
    }

    /**
     * The brand percent of one dish on the channel (PriceHistory), null when there is none.
     */
    public static function percentFor(?int $brandId, OrderChannel $channel): ?string
    {
        if ($brandId === null) {
            return null;
        }

        $row = DB::table('brand_channels')->where('brand_id', $brandId)->where('channel', $channel->value)->first(['price_percent']);

        return $row !== null && $row->price_percent !== null ? (string) $row->price_percent : null;
    }

    /**
     * The dishes of the brands switched off on the branch, with the reason the menu shows.
     *
     * @return array<int, string>
     */
    public static function offOnBranch(int $branchId): array
    {
        $rows = DB::table('branch_brands')
            ->join('brands', 'brands.id', '=', 'branch_brands.brand_id')
            ->join('products', 'products.brand_id', '=', 'brands.id')
            ->where('branch_brands.branch_id', $branchId)
            ->where('branch_brands.is_active', false)
            ->whereNull('products.deleted_at')
            ->orderBy('products.id')
            ->get(['products.id', 'brands.name']);

        $off = [];
        foreach ($rows as $row) {
            $off[(int) $row->id] = self::offReason((string) $row->name);
        }

        return $off;
    }

    /**
     * The store's active brands not hidden on the channel, for the catalogue: [{id, uuid, name, code, color, logo_url}].
     *
     * @return list<array{id: int, uuid: string, name: string, code: string, color: string, logo_url: string|null}>
     */
    public static function catalogBrands(int $storeId, OrderChannel $channel): array
    {
        return DB::table('brands')
            ->leftJoin('brand_channels', fn ($join) => $join->on('brand_channels.brand_id', '=', 'brands.id')->where('brand_channels.channel', '=', $channel->value))
            ->where('brands.store_id', $storeId)
            ->where('brands.is_active', true)
            ->where(fn ($query) => $query->whereNull('brand_channels.is_hidden')->orWhere('brand_channels.is_hidden', false))
            ->orderBy('brands.sort_order')
            ->orderBy('brands.id')
            ->get(['brands.id', 'brands.uuid', 'brands.name', 'brands.short_code', 'brands.color', 'brands.logo_url'])
            ->map(fn ($row) => ['id' => (int) $row->id, 'uuid' => (string) $row->uuid, 'name' => (string) $row->name, 'code' => (string) $row->short_code, 'color' => (string) $row->color, 'logo_url' => $row->logo_url !== null ? (string) $row->logo_url : null])
            ->values()
            ->all();
    }

    public static function offReason(string $brand): string
    {
        return "{$brand} bu filialda o'chirilgan";
    }
}
