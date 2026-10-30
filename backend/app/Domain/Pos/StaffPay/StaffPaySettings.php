<?php

namespace App\Domain\Pos\StaffPay;

use App\Models\Branch;
use App\Models\StaffPaySetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;

/**
 * P6-23: a branch's staff pay settings: the pay period (PayPeriod, a month when never set) and whose sales a bill of a
 * table handed to another waiter is (SalesAttribution, the waiter at payment time when never set). Changing them is
 * audited; a new period changes the periods of statements made afterwards only, a new attribution every statement
 * computed afterwards (a closed statement keeps its numbers).
 */
final class StaffPaySettings
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function period(Branch $branch): PayPeriod
    {
        $stored = StaffPaySetting::query()->where('branch_id', $branch->id)->value('period');

        return PayPeriod::tryFrom((string) $stored) ?? PayPeriod::MONTH;
    }

    public function salesAttribution(Branch $branch): SalesAttribution
    {
        $stored = StaffPaySetting::query()->where('branch_id', $branch->id)->value('sales_attribution');

        return SalesAttribution::tryFrom((string) $stored) ?? SalesAttribution::AT_PAYMENT;
    }

    /**
     * Changes what is given (null: kept) and audits the change once.
     *
     * @return array{period: PayPeriod, sales_attribution: SalesAttribution}
     */
    public function update(Branch $branch, ?PayPeriod $period, ?SalesAttribution $attribution, User $actor): array
    {
        $before = ['period' => $this->period($branch), 'sales_attribution' => $this->salesAttribution($branch)];
        $after = ['period' => $period ?? $before['period'], 'sales_attribution' => $attribution ?? $before['sales_attribution']];
        if ($before === $after && StaffPaySetting::query()->where('branch_id', $branch->id)->exists()) {
            return $after;
        }

        $setting = StaffPaySetting::query()->updateOrCreate(
            ['branch_id' => $branch->id],
            ['store_id' => $branch->store_id, 'period' => $after['period']->value, 'sales_attribution' => $after['sales_attribution']->value, 'updated_by' => $actor->id],
        );

        $values = fn (array $settings) => array_map(fn (\BackedEnum $value) => $value->value, $settings);
        $this->audit->record('staff_pay.settings_changed', $setting, $values($before), $values($after), [
            'branch_name' => $branch->name,
        ], ['actor' => $actor, 'store_id' => (int) $branch->store_id, 'branch_id' => (int) $branch->id]);

        return $after;
    }
}
