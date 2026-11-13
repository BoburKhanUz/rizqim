<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6-44: a person of a corporate customer account (a company's employee). `code` is their talon (QR chit or badge),
 * unique in the store; `daily_limit` (sum) and `meals_per_day` (subscription) are counted per business day by
 * App\Domain\Pos\Accounts\CorporateAccounts.
 *
 * @property numeric-string|null $daily_limit
 * @property int|null $meals_per_day
 * @property bool $is_active
 */
class CustomerAccountMember extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'daily_limit' => 'decimal:2',
        'meals_per_day' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return BelongsTo<CustomerAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id');
    }
}
