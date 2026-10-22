<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P3-17: one movement of a customer account's debt: a bill closed on credit (charge, +), a repayment with its CREDIT
 * receipt (−) or a manager's correction (adjustment, ±).
 *
 * @property \App\Domain\Pos\Accounts\CustomerAccountEntryType $type
 * @property numeric-string $amount
 * @property numeric-string $balance_after
 */
class CustomerAccountEntry extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => \App\Domain\Pos\Accounts\CustomerAccountEntryType::class,
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<CustomerAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
