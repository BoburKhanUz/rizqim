<?php

namespace App\Domain\Pos\Setup;

use App\Models\Category;
use App\Models\ChannelPrice;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * `menu_version` of a store (P3-13, POS_SPEC 12.3): a short token that changes whenever the menu a terminal caches
 * changes (products, variants, categories, modifiers, channel prices, their order and visibility). It does not move
 * for availability (stop-list, kazan): that has its own real-time event. A terminal refetches the catalog when the
 * value differs from the one it holds.
 */
final class MenuVersion
{
    public static function forStore(int $storeId): string
    {
        $parts = [];

        $parts[] = self::stamp(Product::withTrashed()->where('store_id', $storeId), true);
        $parts[] = self::variants($storeId);
        $parts[] = self::stamp(ChannelPrice::query()->where('store_id', $storeId));
        $parts[] = self::stamp(ModifierGroup::withTrashed()->where('store_id', $storeId), true);
        $parts[] = self::stamp(Modifier::withTrashed()->whereIn('modifier_group_id', ModifierGroup::withTrashed()->select('id')->where('store_id', $storeId)), true);
        $parts[] = self::stamp(Category::withTrashed(), true);
        $parts[] = self::stamp(DB::table('product_modifier_groups')
            ->whereIn('product_id', Product::withTrashed()->select('id')->where('store_id', $storeId)));

        return substr(sha1(implode('|', $parts)), 0, 12);
    }

    /**
     * The variants as the menu shows them. Their stock is left out: it moves with every sale, and the menu does not.
     */
    private static function variants(int $storeId): string
    {
        $rows = ProductVariant::withTrashed()
            ->whereIn('product_id', Product::withTrashed()->select('id')->where('store_id', $storeId))
            ->orderBy('id')
            ->get();

        return sha1($rows->map(fn (ProductVariant $variant) => json_encode(Arr::except($variant->getAttributes(), ['stock', 'created_at', 'updated_at'])))->implode("\n"));
    }

    /**
     * "count@latest update" of a query: a row added, removed or edited moves it (`$soft`: also a soft delete).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>|\Illuminate\Database\Query\Builder  $query
     */
    private static function stamp(\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query, bool $soft = false): string
    {
        $base = $query instanceof \Illuminate\Database\Eloquent\Builder ? $query->toBase() : $query;
        $row = $base->selectRaw('COUNT(*) as total, MAX(updated_at) as latest' . ($soft ? ', COUNT(deleted_at) as trashed' : ''))->first();

        return ($row->total ?? 0) . '@' . ($row->latest ?? '') . ($soft ? '#' . ($row->trashed ?? 0) : '');
    }
}
