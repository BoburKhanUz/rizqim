<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use App\Support\Money;
use App\Traits\Auditable;

class PayoutRequest extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = [
        'processed_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::updating(function ($payout) {
            if (!$payout->isDirty('status')) {
                return;
            }

            $newStatus = $payout->status;
            if (!in_array($newStatus, ['completed', 'rejected'], true)) {
                throw new \DomainException("Payout so'rovi holatini '{$newStatus}' ga o'zgartirib bo'lmaydi.");
            }

            $payout->processed_at = now();
            $payout->processed_by = auth()->id();

            DB::transaction(function () use ($payout, $newStatus) {
                // The in-memory original may be stale (double click / two admins): decide on the locked row.
                $currentStatus = static::whereKey($payout->getKey())->lockForUpdate()->value('status');
                if ($currentStatus !== 'pending') {
                    throw new \DomainException("Payout so'rovi allaqachon ko'rib chiqilgan.");
                }

                // Transitioning from pending -> completed
                if ($newStatus === 'completed') {
                    $store = Store::where('id', $payout->store_id)->lockForUpdate()->firstOrFail();

                    $amount = Money::of($payout->amount);
                    $balanceBefore = Money::of($store->wallet_balance);
                    $balanceAfter = $balanceBefore->minus($amount);

                    if ($balanceAfter->isNegative()) {
                        throw new \DomainException("Hamyonda yetarli mablag' yo'q: " . number_format($balanceBefore->toFloat()) . " UZS.");
                    }

                    // Update store wallet balance
                    $store->forceFill([
                        'wallet_balance' => $balanceAfter->toString(),
                    ])->save();

                    // Record wallet transaction
                    StoreWalletTransaction::create([
                        'store_id' => $store->id,
                        'type' => \App\Enums\StoreTransactionType::PAYOUT->value,
                        'amount' => $amount->negated()->toString(),
                        'balance_before' => $balanceBefore->toString(),
                        'balance_after' => $balanceAfter->toString(),
                        'notes' => "Payout completed to card: {$payout->card_number}",
                        'performed_by' => auth()->id(),
                    ]);
                }

                // Persist the transition while the row lock is held, so a parallel approval sees it.
                static::whereKey($payout->getKey())->update([
                    'status' => $newStatus,
                    'processed_at' => $payout->processed_at,
                    'processed_by' => $payout->processed_by,
                ]);
            });
        });
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
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
