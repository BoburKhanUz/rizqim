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

    /**
     * @return HasMany<StoreSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(StoreSubscription::class);
    }
}
