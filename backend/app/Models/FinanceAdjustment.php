<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P4-23: a manual adjustment of a store's debt or wallet (Moliya → Qo'lda tuzatish), made only through
 * App\Services\Finance\Adjustments\AdjustmentService: `pending` until a second member approves one of
 * config('finance.four_eyes_threshold') or more, `applied` once its ledger row is written, or `rejected`.
 *
 * @property int $id
 * @property string $uuid
 * @property int $store_id
 * @property string $kind App\Services\Finance\Adjustments\AdjustmentKind
 * @property numeric-string $amount always positive; the kind says which way
 * @property string $reason
 * @property string $status
 * @property bool $needs_second_approval
 * @property string|null $document_path
 * @property string|null $document_name
 * @property string|null $document_mime
 * @property int|null $document_size
 * @property int|null $requested_by
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property int|null $rejected_by
 * @property \Illuminate\Support\Carbon|null $rejected_at
 * @property string|null $decision_note
 * @property \Illuminate\Support\Carbon|null $applied_at
 * @property int|null $debt_transaction_id
 * @property int|null $wallet_transaction_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class FinanceAdjustment extends Model
{
    use \App\Traits\HasPublicUuid;

    public const PENDING = 'pending';

    public const APPLIED = 'applied';

    public const REJECTED = 'rejected';

    protected $guarded = ['id'];

    protected $hidden = ['document_path'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'needs_second_approval' => 'boolean',
            'document_size' => 'integer',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
