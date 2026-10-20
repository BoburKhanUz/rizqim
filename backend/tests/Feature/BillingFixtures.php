<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Models\Device;
use App\Models\Store;
use App\Models\TariffPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * P1-23: tariff plans, terminals at given moments and Tashkent-time helpers for the billing tests.
 */
trait BillingFixtures
{
    /**
     * A moment in Asia/Tashkent wall time, as the app-timezone (UTC) instant the columns hold.
     */
    protected function tashkent(string $time): Carbon
    {
        return Carbon::parse($time, 'Asia/Tashkent')->setTimezone(config('app.timezone'));
    }

    protected function billingStore(string $name = 'Billing Cafe', array $attributes = []): Store
    {
        return Store::forceCreate(array_merge([
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(5),
            'status' => 'active',
            'commission_rate' => 10,
            'credit_limit' => -10000000,
        ], $attributes));
    }

    protected function plan(array $attributes = []): TariffPlan
    {
        static $sequence = 0;
        $sequence++;

        return TariffPlan::create(array_merge([
            'name' => "Test plan {$sequence}",
            'code' => "test-{$sequence}",
            'is_active' => true,
            'commission_percent' => null,
            'monthly_base_fee' => null,
            'monthly_fee_per_terminal' => null,
            'included_terminals' => 0,
            'trial_days' => 0,
        ], $attributes));
    }

    protected function terminal(Store $store, string $pairedAt, ?string $revokedAt = null, DeviceType $type = DeviceType::POS): Device
    {
        $device = new Device(['store_id' => $store->id, 'type' => $type, 'name' => 'Kassa']);
        $device->forceFill([
            'created_at' => $this->tashkent($pairedAt),
            'updated_at' => $this->tashkent($pairedAt),
            'revoked_at' => $revokedAt ? $this->tashkent($revokedAt) : null,
        ]);
        $device->timestamps = false;
        $device->save();

        return $device;
    }
}
