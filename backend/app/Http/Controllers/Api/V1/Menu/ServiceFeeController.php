<?php

namespace App\Http\Controllers\Api\V1\Menu;

use App\Enums\OrderChannel;
use App\Enums\PlaceFeeMode;
use App\Models\Branch;
use App\Models\ServiceFeeSetting;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[Group('Owner: service fees', "Xizmat haqi (%) va joy narxi: do'kon va filial sozlamalari (P1-21, POS zal uchun). Telegram buyurtmalariga qo'llanmaydi.")]
class ServiceFeeController extends MenuStaffController
{
    /**
     * Service charge and place fee settings
     *
     * The store's default (`branch_id` null) and the branch overrides. A channel list null means `["pos_hall"]`;
     * Telegram orders never get either fee. P3-12: a row with `hall_id` is that hall's own (it replaces the branch's
     * in the hall).
     */
    public function index(Request $request): JsonResponse
    {
        $settings = ServiceFeeSetting::where('store_id', $this->storeId($request))->orderByRaw('COALESCE(branch_id, 0)')->orderByRaw('COALESCE(hall_id, 0)')->get();

        return $this->success([
            'channels' => OrderChannel::values(),
            'settings' => $settings->map(fn (ServiceFeeSetting $setting) => $this->present($setting))->values(),
        ]);
    }

    /**
     * Set the service charge and place fee
     *
     * For the store (`branch_id` null) or one branch. `service_charge_percent` 0-30; `place_fee_mode` per_person
     * (× guests) or per_table. Channels default to pos_hall; telegram is not accepted. P3-12: with `hall_id`, the
     * hall's own settings (R6: e.g. VIP rooms 15%, a self-service zone 0%); `DELETE /owner/service-fees/halls/{id}`
     * removes them.
     */
    public function update(Request $request, AuditLogger $audit): JsonResponse
    {
        $storeId = $this->storeId($request);
        $channels = array_values(array_diff(OrderChannel::values(), [OrderChannel::TELEGRAM->value]));

        $data = $request->validate([
            'branch_id' => 'nullable|integer',
            'service_charge_percent' => 'required|numeric|min:0|max:30|decimal:0,2',
            'service_charge_channels' => 'nullable|array',
            'service_charge_channels.*' => ['string', Rule::in($channels)],
            'place_fee_amount' => 'required|numeric|min:0|max:100000000|decimal:0,2',
            'place_fee_mode' => ['required', 'string', Rule::in(array_map(fn (PlaceFeeMode $mode) => $mode->value, PlaceFeeMode::cases()))],
            'place_fee_channels' => 'nullable|array',
            'place_fee_channels.*' => ['string', Rule::in($channels)],
            // P3-12: a hall's own settings (its branch is the hall's)
            'hall_id' => 'nullable|integer|min:1',
        ]);

        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;
        if ($branchId !== null && !Branch::where('store_id', $storeId)->whereKey($branchId)->exists()) {
            throw ValidationException::withMessages(['branch_id' => 'Filial topilmadi.']);
        }

        // P3-12: a hall row (R6) belongs to the hall's branch
        $hallId = isset($data['hall_id']) ? (int) $data['hall_id'] : null;
        if ($hallId !== null) {
            $hall = \App\Models\Hall::where('store_id', $storeId)->find($hallId);
            if ($hall === null || ($branchId !== null && (int) $hall->branch_id !== $branchId)) {
                throw ValidationException::withMessages(['hall_id' => 'Zal topilmadi.']);
            }
            $branchId = (int) $hall->branch_id;
        }

        $setting = ServiceFeeSetting::where('store_id', $storeId)
            ->when($hallId !== null, fn ($query) => $query->where('hall_id', $hallId), fn ($query) => $query->whereNull('hall_id')
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId), fn ($query) => $query->whereNull('branch_id')))
            ->first() ?? new ServiceFeeSetting(['store_id' => $storeId, 'branch_id' => $branchId, 'hall_id' => $hallId]);

        $before = $setting->exists ? $this->present($setting) : [];
        $setting->fill([
            'service_charge_percent' => Money::of($data['service_charge_percent'])->toString(),
            'service_charge_channels' => isset($data['service_charge_channels']) ? array_values(array_unique($data['service_charge_channels'])) : null,
            'place_fee_amount' => Money::of($data['place_fee_amount'])->toString(),
            'place_fee_mode' => $data['place_fee_mode'],
            'place_fee_channels' => isset($data['place_fee_channels']) ? array_values(array_unique($data['place_fee_channels'])) : null,
        ])->save();

        $audit->record('settings.service_fees_changed', $setting, $before, $this->present($setting), array_filter(['hall_id' => $hallId]), ['store_id' => $storeId, 'branch_id' => $branchId]);

        return $this->success($this->present($setting), 'Xizmat haqi sozlamalari saqlandi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ServiceFeeSetting $setting): array
    {
        return [
            'branch_id' => $setting->branch_id !== null ? (int) $setting->branch_id : null,
            // P3-12: a hall's own row (null: the branch's or the store's)
            'hall_id' => $setting->hall_id !== null ? (int) $setting->hall_id : null,
            'service_charge_percent' => $setting->service_charge_percent,
            'service_charge_channels' => $setting->service_charge_channels,
            'place_fee_amount' => $setting->place_fee_amount,
            'place_fee_mode' => $setting->place_fee_mode->value,
            'place_fee_channels' => $setting->place_fee_channels,
        ];
    }
}
