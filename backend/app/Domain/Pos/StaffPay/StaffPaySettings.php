<?php

namespace App\Domain\Pos\StaffPay;

use App\Models\Branch;
use App\Models\StaffPaySetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;

/**
 * P6-23: a branch's staff pay settings: the pay period (PayPeriod, a month when never set). Changing it is audited and
 * changes the periods of statements made afterwards only.
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

    public function setPeriod(Branch $branch, PayPeriod $period, User $actor): PayPeriod
    {
        $before = $this->period($branch);
        if ($before === $period && StaffPaySetting::query()->where('branch_id', $branch->id)->exists()) {
            return $period;
        }

        $setting = StaffPaySetting::query()->updateOrCreate(
            ['branch_id' => $branch->id],
            ['store_id' => $branch->store_id, 'period' => $period->value, 'updated_by' => $actor->id],
        );

        $this->audit->record('staff_pay.settings_changed', $setting, ['period' => $before->value], ['period' => $period->value], [
            'branch_name' => $branch->name,
        ], ['actor' => $actor, 'store_id' => (int) $branch->store_id, 'branch_id' => (int) $branch->id]);

        return $period;
    }
}
