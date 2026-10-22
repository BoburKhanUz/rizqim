<?php

namespace App\Http\Controllers\Api\V1\PosHall;

use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Management\ManagementController;
use App\Models\AutoGuestItem;
use App\Models\Hall;
use App\Models\ProductVariant;
use App\Services\Audit\AuditLogger;
use App\Support\Quantity;
use App\Support\RowId;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * P3-12: the "choy-non" rule (R11): items a hall order gets per guest automatically. settings.manage; never from a
 * terminal PIN session.
 */
#[Group('Owner: auto guest items', "Kishi boshiga avtomatik qator (choy-non qoidasi): filial yoki zal bo'yicha (P3-12).")]
class AutoGuestItemController extends ManagementController
{
    public const ITEM = 'array{id: int, branch_id: int, hall_id: int|null, product_variant_id: int, product_name: string|null, variant_name: string|null, unit: string|null, quantity_per_guest: int|float, is_active: bool, sort_order: int}';

    /**
     * Automatic per-guest items
     *
     * A hall order (pos_hall) gets `quantity_per_guest` of each item per guest when it opens and when guests join
     * (pieces rounded up: 0.5 choynak per guest is 2 for 3 guests). A hall's own rule replaces the branch rule of the
     * same item in that hall; an inactive hall rule switches it off there.
     */
    #[QueryParameter('branch_id', description: "The branch; defaults to the caller's.", type: 'int')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<' . self::ITEM . '>}')]
    public function index(Request $request): JsonResponse
    {
        $request->validate(['branch_id' => 'nullable|integer|min:1']);
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE, $request->query('branch_id'));

        $items = AutoGuestItem::with('variant.product')->where('branch_id', $branch->id)
            ->orderByRaw('hall_id IS NOT NULL')->orderBy('hall_id')->orderBy('sort_order')->orderBy('id')
            ->get();

        return $this->success($items->map(fn (AutoGuestItem $item) => $this->present($item))->values()->all());
    }

    /**
     * Add an automatic per-guest item
     *
     * For the branch, or one hall of it (`hall_id`). `product_variant_id`: a variant of the store's menu.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . self::ITEM . '}')]
    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'required|integer|min:1',
            'hall_id' => 'nullable|integer|min:1',
            'product_variant_id' => 'required|integer|min:1',
            'quantity_per_guest' => 'required|numeric|gt:0|max:100|decimal:0,3',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:100000',
        ]);
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE, $data['branch_id']);
        $hallId = isset($data['hall_id']) ? (int) $data['hall_id'] : null;
        if ($hallId !== null && !Hall::where('branch_id', $branch->id)->whereKey($hallId)->exists()) {
            throw ValidationException::withMessages(['hall_id' => 'Zal bu filialda topilmadi.']);
        }

        $variant = ProductVariant::whereKey((int) $data['product_variant_id'])
            ->whereHas('product', fn ($query) => $query->where('store_id', $branch->store_id))
            ->first();
        if ($variant === null) {
            throw ValidationException::withMessages(['product_variant_id' => 'Taom topilmadi.']);
        }

        $exists = AutoGuestItem::where('branch_id', $branch->id)->where('product_variant_id', $variant->id)
            ->when($hallId !== null, fn ($query) => $query->where('hall_id', $hallId), fn ($query) => $query->whereNull('hall_id'))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['product_variant_id' => "Bu taom uchun qoida allaqachon bor: uni tahrirlang."]);
        }

        $item = AutoGuestItem::create([
            'store_id' => $branch->store_id,
            'branch_id' => $branch->id,
            'hall_id' => $hallId,
            'product_variant_id' => $variant->id,
            'quantity_per_guest' => Quantity::of($data['quantity_per_guest'])->toString(),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);
        $audit->record('pos.auto_guest_item_created', $item, [], [], $this->present($item), ['branch_id' => (int) $branch->id]);

        return $this->success($this->present($item->refresh()), "Qoida qo'shildi.", 201);
    }

    /**
     * Edit an automatic per-guest item
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ITEM . '}')]
    public function update(Request $request, string $id, AuditLogger $audit): JsonResponse
    {
        $item = $this->item($request, $id);
        $data = $request->validate([
            'quantity_per_guest' => 'sometimes|required|numeric|gt:0|max:100|decimal:0,3',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0|max:100000',
        ]);

        $before = $this->present($item);
        if (isset($data['quantity_per_guest'])) {
            $data['quantity_per_guest'] = Quantity::of($data['quantity_per_guest'])->toString();
        }
        $item->fill($data)->save();
        $audit->record('pos.auto_guest_item_updated', $item, $before, $this->present($item), [], ['branch_id' => (int) $item->branch_id]);

        return $this->success($this->present($item->refresh()), 'Qoida saqlandi.');
    }

    /**
     * Delete an automatic per-guest item
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{id: int}}')]
    public function destroy(Request $request, string $id, AuditLogger $audit): JsonResponse
    {
        $item = $this->item($request, $id);
        $item->delete();
        $audit->record('pos.auto_guest_item_deleted', $item, [], [], ['product_variant_id' => (int) $item->product_variant_id], ['branch_id' => (int) $item->branch_id]);

        return $this->success(['id' => (int) $item->id], "Qoida o'chirildi.");
    }

    /**
     * @return array<string, mixed>
     */
    private function present(AutoGuestItem $item): array
    {
        $item->loadMissing('variant.product');
        $variant = $item->variant;

        return [
            'id' => (int) $item->id,
            'branch_id' => (int) $item->branch_id,
            'hall_id' => $item->hall_id !== null ? (int) $item->hall_id : null,
            'product_variant_id' => (int) $item->product_variant_id,
            'product_name' => $variant?->product?->name,
            'variant_name' => $variant?->name,
            'unit' => $variant?->unit->value,
            'quantity_per_guest' => Quantity::normalize($item->quantity_per_guest) ?? 0,
            'is_active' => $item->is_active,
            'sort_order' => $item->sort_order,
        ];
    }

    private function item(Request $request, string $id): AutoGuestItem
    {
        $itemId = RowId::parse($id);
        $item = $itemId !== null ? AutoGuestItem::where('store_id', $this->storeId($request))->find($itemId) : null;
        if (!$item instanceof AutoGuestItem) {
            throw new HttpResponseException($this->error('Qoida topilmadi.', [], 404));
        }
        $this->authorizeFor($request, Permission::SETTINGS_MANAGE, $item);

        return $item;
    }
}
