<?php

namespace App\Http\Controllers\Api\V1\Menu;

use App\Enums\OrderChannel;
use App\Models\ChannelPrice;
use App\Models\ProductVariant;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[Group('Owner: channel prices', "Kanal bo'yicha narx va ko'rinish: Telegram, mobil ilova, POS zal/olib ketish, agregator (P1-21).")]
class ChannelPriceController extends MenuStaffController
{
    /**
     * A product's channel prices
     *
     * One row per channel and variant (`product_variant_id` null: the whole product, hidden only). No row: the
     * variant's own price, visible.
     */
    public function index(Request $request, int $id): JsonResponse
    {
        $product = $this->product($request, $id);

        return $this->success([
            'product_id' => (int) $product->id,
            'channels' => OrderChannel::values(),
            'prices' => $this->rows($product->id),
        ]);
    }

    /**
     * Replace a product's channel prices
     *
     * `prices` replaces every row of the product. A row may set a variant's `price` on a channel and/or hide the
     * variant (`is_hidden`); a row without `product_variant_id` hides the whole product there (no price).
     */
    public function update(Request $request, int $id, AuditLogger $audit): JsonResponse
    {
        $product = $this->product($request, $id);

        $data = $request->validate([
            'prices' => 'present|array|max:200',
            'prices.*.channel' => ['required', 'string', Rule::in(OrderChannel::values())],
            'prices.*.product_variant_id' => 'nullable|integer',
            'prices.*.price' => 'nullable|numeric|min:0|max:9999999999|decimal:0,2',
            'prices.*.is_hidden' => 'nullable|boolean',
        ]);

        $variantIds = ProductVariant::where('product_id', $product->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $seen = [];
        $rows = [];

        foreach ($data['prices'] as $position => $row) {
            $variantId = isset($row['product_variant_id']) ? (int) $row['product_variant_id'] : null;
            if ($variantId !== null && !in_array($variantId, $variantIds, true)) {
                throw ValidationException::withMessages(["prices.{$position}.product_variant_id" => 'Variant bu mahsulotga tegishli emas.']);
            }
            if ($variantId === null && isset($row['price'])) {
                throw ValidationException::withMessages(["prices.{$position}.price" => "Narx variant uchun beriladi (product_variant_id)."]);
            }

            $key = $row['channel'] . ':' . ($variantId ?? 0);
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(["prices.{$position}" => 'Bir kanal va variant uchun bitta qator bo\'lishi kerak.']);
            }
            $seen[$key] = true;

            $price = isset($row['price']) ? Money::of($row['price'])->toString() : null;
            $hidden = (bool) ($row['is_hidden'] ?? false);
            if ($price === null && !$hidden) {
                continue; // the default: nothing to store
            }

            $rows[] = [
                'store_id' => $product->store_id,
                'product_id' => $product->id,
                'product_variant_id' => $variantId,
                'channel' => $row['channel'],
                'price' => $price,
                'is_hidden' => $hidden,
            ];
        }

        $before = $this->rows($product->id);

        DB::transaction(function () use ($product, $rows) {
            ChannelPrice::where('product_id', $product->id)->delete();
            foreach ($rows as $row) {
                ChannelPrice::create($row);
            }
        });

        $after = $this->rows($product->id);
        if ($before !== $after) {
            $audit->record('product.channel_prices_changed', $product, ['prices' => $before], ['prices' => $after], ['product' => $product->name]);
        }

        return $this->success(['product_id' => (int) $product->id, 'prices' => $after], 'Kanal narxlari saqlandi.');
    }

    /**
     * @return list<array{channel: string, product_variant_id: int|null, price: string|null, is_hidden: bool}>
     */
    private function rows(int $productId): array
    {
        return ChannelPrice::where('product_id', $productId)
            ->orderBy('channel')
            ->orderByRaw('COALESCE(product_variant_id, 0)')
            ->get()
            ->map(fn (ChannelPrice $row) => [
                'channel' => $row->channel->value,
                'product_variant_id' => $row->product_variant_id !== null ? (int) $row->product_variant_id : null,
                'price' => $row->price,
                'is_hidden' => $row->is_hidden,
            ])
            ->values()
            ->all();
    }
}
