<?php

namespace App\Services\Finance\Billing;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * P4-21: the plan or the dunning lock refusing a gated write (docs/BILLING.md, "Funksiyalar"). The API's answer, never
 * reported: 403 {success: false, message, code, feature?, required_plan?, data: {reason: code, ...}}.
 *
 * - plan_feature_missing: the store's plan does not have the feature (`feature`, `required_plan`: the cheapest plan
 *   that has it, `required_plan_name`);
 * - subscription_locked: an invoice is unpaid more than a week after its due date (`locked_since`);
 * - device_limit: a terminal over the plan's while the subscription is locked (`device_type`, `included`, `active`).
 */
class BillingRefusal extends RuntimeException implements ShouldntReport
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(string $message, public readonly string $reason, public readonly array $data = [], public readonly int $status = 403)
    {
        parent::__construct($message);
    }

    public static function featureMissing(\App\Enums\PlanFeature $feature, ?\App\Models\TariffPlan $required): self
    {
        $message = $required !== null
            ? "Bu imkoniyat ({$feature->label()}) {$required->name} tarifida bor."
            : "Bu imkoniyat ({$feature->label()}) tarifingizda yo'q.";

        return new self($message, 'plan_feature_missing', [
            'feature' => $feature->value,
            'feature_label' => $feature->label(),
            'required_plan' => $required?->code,
            'required_plan_name' => $required?->name,
        ]);
    }

    public static function locked(?string $lockedSince): self
    {
        return new self(
            "To'lanmagan hisob-faktura sababli yangi sozlamalar yopilgan. To'lov qilinishi bilan darhol ochiladi. Kassa to'lov qabul qilishda davom etadi.",
            'subscription_locked',
            ['locked_since' => $lockedSince],
        );
    }

    public function render(): JsonResponse
    {
        $top = array_intersect_key($this->data, array_flip(['feature', 'required_plan']));

        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'code' => $this->reason,
            ...$top,
            'data' => ['reason' => $this->reason, ...$this->data],
        ], $this->status);
    }
}
