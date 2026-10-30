<?php

namespace App\Http\Controllers\Api\V1\Menu;

use App\Domain\Pos\Orders\WeightBasis;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Models\Product;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\PermissionService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * P6-25 (B3, B4): how a dish is sold in a kabobxona or baliqxona — the one-tap ×N buttons of a skewer dish, the grill's
 * time, and "weight later" (docs/API.md "Six, og'irlik"). Data on the product; the till and the waiter phone read it
 * from the POS catalog (GET /catalog?channel=pos_hall: `quick_multipliers`, `wait_minutes`, `weight_mode`), the kitchen
 * ticket shows `wait_minutes`.
 */
#[Group('Owner: menu', "Menyu: mahsulotlar, modifikatorlar, kanal narxlari (P1-21).")]
class ProductPosServiceController extends MenuStaffController
{
    public const SHAPE = 'array{product_id: int, quick_multipliers: list<int>|null, wait_minutes: int|null, weight_mode: ' . WeightBasis::class . '|null}';

    /** The multipliers a dish may offer (×2 … ×10). */
    public const MIN_MULTIPLIER = 2;

    public const MAX_MULTIPLIER = 10;

    public const MAX_WAIT_MINUTES = 240;

    public function __construct(
        PermissionService $permissions,
        protected PosModules $modules,
        protected AuditLogger $audit,
    ) {
        parent::__construct($permissions);
    }

    /**
     * A dish's skewer buttons, grill time and weight rule
     *
     * Only what is sent changes; null clears it. `quick_multipliers`: the ×N buttons (2–10, each once, up to 9; module
     * `skewer_buttons`), `wait_minutes`: the grill's time (0–240; module `skewer_buttons`), `weight_mode`: `raw` |
     * `cooked` — the dish is weighed after it is chosen and its kg price is for that weight (module `weight_later`; only
     * a kg/l dish not sold from a kazan batch). Setting a value whose module is off for every branch of the store: 403
     * `module_disabled` (clearing never is). Audited. menu.manage.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::SHAPE . '}')]
    public function update(Request $request, int $id): JsonResponse
    {
        $product = $this->product($request, $id);
        $data = $request->validate([
            'quick_multipliers' => 'sometimes|nullable|array|max:9',
            'quick_multipliers.*' => 'integer|distinct|between:' . self::MIN_MULTIPLIER . ',' . self::MAX_MULTIPLIER,
            'wait_minutes' => 'sometimes|nullable|integer|between:0,' . self::MAX_WAIT_MINUTES,
            'weight_mode' => ['sometimes', 'nullable', Rule::enum(WeightBasis::class)],
        ]);
        $storeId = (int) $product->store_id;

        $changes = [];
        if (array_key_exists('quick_multipliers', $data)) {
            $list = $data['quick_multipliers'] === null ? [] : array_map('intval', $data['quick_multipliers']);
            sort($list);
            $changes['quick_multipliers'] = $list === [] ? null : $list;
        }
        if (array_key_exists('wait_minutes', $data)) {
            $changes['wait_minutes'] = $data['wait_minutes'] !== null ? (int) $data['wait_minutes'] : null;
        }
        if (array_key_exists('weight_mode', $data)) {
            $changes['weight_mode'] = $data['weight_mode'] !== null ? (string) $data['weight_mode'] : null;
        }

        if (($changes['quick_multipliers'] ?? null) !== null || ($changes['wait_minutes'] ?? null) !== null) {
            $this->modules->assertEnabledInStore($storeId, PosModule::SKEWER_BUTTONS);
        }
        if (($changes['weight_mode'] ?? null) !== null) {
            $this->modules->assertEnabledInStore($storeId, PosModule::WEIGHT_LATER);
            $this->assertWeighable($product);
        }

        $before = $this->present($product);
        if ($changes !== []) {
            $product->forceFill($changes)->save();
            $this->audit->record('menu.product_pos_service_updated', $product, $before, $this->present($product), [], ['store_id' => $storeId]);
        }

        return $this->success($this->present($product), 'Saqlandi.');
    }

    /**
     * @throws ValidationException
     */
    private function assertWeighable(Product $product): void
    {
        if ($product->batch_tracked) {
            throw ValidationException::withMessages(['weight_mode' => "Qozon partiyasidan sotiladigan taomning og'irligi keyin kiritilmaydi."]);
        }
        $measured = $product->variants()->get()->contains(fn ($variant) => $variant->unit->isMeasured());
        if (!$measured) {
            throw ValidationException::withMessages(['weight_mode' => "Og'irlikni keyin kiritish uchun taomning kg yoki litr varianti bo'lishi kerak."]);
        }
    }

    /**
     * @return array{product_id: int, quick_multipliers: list<int>|null, wait_minutes: int|null, weight_mode: string|null}
     */
    private function present(Product $product): array
    {
        return [
            'product_id' => (int) $product->id,
            'quick_multipliers' => $product->quick_multipliers !== null ? array_map('intval', $product->quick_multipliers) : null,
            'wait_minutes' => $product->wait_minutes,
            'weight_mode' => $product->weight_mode,
        ];
    }
}
