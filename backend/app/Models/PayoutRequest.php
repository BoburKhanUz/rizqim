<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use App\Traits\Auditable;

class PayoutRequest extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = [
        'processed_at' => 'datetime',
        'amount' => 'float',
    ];

    protected static function booted()
    {
        static::updating(function ($payout) {
            $originalStatus = $payout->getOriginal('status');
            
            // Transitioning from pending -> completed
            if ($originalStatus === 'pending' && $payout->status === 'completed') {
                $payout->processed_at = now();
                $payout->processed_by = auth()->id();

                DB::transaction(function () use ($payout) {
                    $store = Store::where('id', $payout->store_id)->lockForUpdate()->firstOrFail();
                    
                    $balanceBefore = (float) $store->wallet_balance;
                    $balanceAfter = $balanceBefore - (float) $payout->amount;

                    // Update store wallet balance
                    $store->update([
                        'wallet_balance' => $balanceAfter,
                    ]);

                    // Record wallet transaction
                    StoreWalletTransaction::create([
                        'store_id' => $store->id,
                        'type' => \App\Enums\StoreTransactionType::PAYOUT->value,
                        'amount' => -(float) $payout->amount,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceAfter,
                        'notes' => "Payout completed to card: {$payout->card_number}",
                        'performed_by' => auth()->id(),
                    ]);
                });
            }

            // Transitioning from pending -> rejected
            if ($originalStatus === 'pending' && $payout->status === 'rejected') {
                $payout->processed_at = now();
                $payout->processed_by = auth()->id();
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
