<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * P1-23: a tariff plan (docs/BILLING.md). commission_percent NULL keeps each store's own commission_rate; the fees
 * NULL mean none. So a plan is commission-only, subscription-only (commission 0 and a fee) or both.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property bool $is_active
 * @property numeric-string|null $commission_percent
 * @property numeric-string|null $monthly_base_fee
 * @property numeric-string|null $monthly_fee_per_terminal
 * @property int $included_terminals
 * @property int $trial_days
 * @property string|null $description
 *
 * P4-21: the v2 catalog (version 2: Start, Asosiy, Pro; docs/BILLING.md)
 * @property int $version
 * @property string|null $tier start | asosiy | pro
 * @property int $sort_order
 * @property numeric-string|null $yearly_fee
 * @property int $included_pos
 * @property int $included_kds
 * @property numeric-string|null $extra_pos_fee
 * @property numeric-string|null $extra_kds_fee
 * @property array<int, string>|null $features App\Enums\PlanFeature values on top of the base ones
 */
class TariffPlan extends Model
{
    protected $fillable = [
        'name',
        'code',
        'is_active',
        'commission_percent',
        'monthly_base_fee',
        'monthly_fee_per_terminal',
        'included_terminals',
        'trial_days',
        'description',
        // P4-21
        'version',
        'tier',
        'sort_order',
        'yearly_fee',
        'included_pos',
        'included_kds',
        'extra_pos_fee',
        'extra_kds_fee',
        'features',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'commission_percent' => 'decimal:2',
            'monthly_base_fee' => 'decimal:2',
            'monthly_fee_per_terminal' => 'decimal:2',
            'included_terminals' => 'integer',
            'trial_days' => 'integer',
            // P4-21
            'version' => 'integer',
            'sort_order' => 'integer',
            'yearly_fee' => 'decimal:2',
            'included_pos' => 'integer',
            'included_kds' => 'integer',
            'extra_pos_fee' => 'decimal:2',
            'extra_kds_fee' => 'decimal:2',
            'features' => 'array',
        ];
    }

    public function baseFee(): Money
    {
        return Money::of($this->monthly_base_fee);
    }

    public function feePerTerminal(): Money
    {
        return Money::of($this->monthly_fee_per_terminal);
    }

    public function hasSubscriptionFee(): bool
    {
        return $this->baseFee()->isPositive() || $this->feePerTerminal()->isPositive();
    }

    public function overridesCommission(): bool
    {
        return $this->commission_percent !== null;
    }

    /**
     * One line for lists: "5% komissiya + 100 000 UZS/oy + 100 000 UZS/terminal (1 tasi kiradi)".
     */
    public function summary(): string
    {
        $parts = [$this->overridesCommission()
            ? rtrim(rtrim((string) $this->commission_percent, '0'), '.') . '% komissiya'
            : "do'kon komissiyasi"];

        if ($this->baseFee()->isPositive()) {
            $parts[] = number_format($this->baseFee()->toFloat(), 0, '.', ' ') . ' UZS/oy';
        }
        if ($this->feePerTerminal()->isPositive()) {
            $parts[] = number_format($this->feePerTerminal()->toFloat(), 0, '.', ' ') . ' UZS/terminal'
                . ($this->included_terminals > 0 ? " ({$this->included_terminals} tasi kiradi)" : '');
        }

        return implode(' + ', $parts);
    }

    // --- P4-21: the v2 catalog ---

    /**
     * A Billing v2 plan (Start, Asosiy, Pro): features, devices by type, yearly price. A P1-23 plan (version 1) keeps
     * every feature and its P1-23 invoice formula.
     */
    public function isV2(): bool
    {
        return $this->version >= 2;
    }

    /**
     * Where the plan stands in the catalog (higher is more): an immediate change to a higher one is an upgrade
     * (prorated), to a lower one a downgrade (at the period's end).
     */
    public function rank(): int
    {
        return $this->sort_order;
    }

    public function yearlyFee(): Money
    {
        return $this->yearly_fee !== null
            ? Money::of($this->yearly_fee)
            : $this->baseFee()->times((int) config('billing.yearly_months_charged', 10));
    }

    public function extraDeviceFee(\App\Enums\DeviceType $type): Money
    {
        return Money::of($type === \App\Enums\DeviceType::KDS ? $this->extra_kds_fee : $this->extra_pos_fee);
    }

    public function includedDevices(\App\Enums\DeviceType $type): int
    {
        return max(0, $type === \App\Enums\DeviceType::KDS ? $this->included_kds : $this->included_pos);
    }

    /**
     * Whether the plan has a gated feature (a base feature is in every plan; a P1-23 plan has them all).
     */
    public function hasFeature(\App\Enums\PlanFeature $feature): bool
    {
        return $feature->isBase() || !$this->isV2() || in_array($feature->value, $this->features ?? [], true);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<TariffPlan>  $query
     * @return \Illuminate\Database\Eloquent\Builder<TariffPlan>
     */
    public function scopeCatalog(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('version', '>=', 2)->orderBy('sort_order')->orderBy('id');
    }

    // --- end P4-21 ---

    /**
     * @return HasMany<StoreSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(StoreSubscription::class);
    }
}
