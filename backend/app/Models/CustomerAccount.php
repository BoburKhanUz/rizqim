<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * P3-17: a customer or group that may buy on credit (nasiya). `balance` is the debt (sum of the entries), changed only
 * by App\Domain\Pos\Accounts\CustomerAccountService under a row lock.
 *
 * P6-44: `kind` corporate is a company whose members (CustomerAccountMember) eat on its account within their limits.
 *
 * @property numeric-string|null $credit_limit
 * @property numeric-string $balance
 * @property bool $is_blocked
 * @property \App\Domain\Pos\Accounts\CustomerAccountKind $kind
 * @property \App\Domain\Pos\Accounts\TalonMode $talon_mode
 * @property numeric-string|null $monthly_limit
 * @property bool $charge_approval
 */
class CustomerAccount extends Model
{
    use \App\Traits\HasPublicUuid;

    // The debt changes only through the ledger (explicit forceFill), never via mass assignment
    protected $guarded = ['id', 'balance'];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'balance' => 'decimal:2',
        'is_blocked' => 'boolean',
        'kind' => \App\Domain\Pos\Accounts\CustomerAccountKind::class,
        'talon_mode' => \App\Domain\Pos\Accounts\TalonMode::class,
        'monthly_limit' => 'decimal:2',
        'charge_approval' => 'boolean',
    ];

    protected $attributes = [
        'balance' => '0.00',
        'is_blocked' => false,
        'kind' => 'customer',
        'talon_mode' => 'none',
        'charge_approval' => true,
    ];

    public function isCorporate(): bool
    {
        return $this->kind === \App\Domain\Pos\Accounts\CustomerAccountKind::CORPORATE;
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<CustomerAccountEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(CustomerAccountEntry::class);
    }

    /**
     * @return HasMany<CustomerAccountMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(CustomerAccountMember::class);
    }
}
