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
        // P4-23: the second approval and the netting (App\Services\Finance\Payouts\PayoutService)
        'first_approved_at' => 'datetime',
        'netted_amount' => 'decimal:2',
        'transferred_amount' => 'decimal:2',
        'netting' => 'array',
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

                // Transitioning from pending -> completed: P4-23, the store's debt is netted first, the rest goes to the
                // card (PayoutService::complete, the control center's path too). A payout that needs two members is
                // only completed in the Boshqaruv markazi.
                if ($newStatus === 'completed') {
                    $payouts = app(\App\Services\Finance\Payouts\PayoutService::class);
                    if ($payouts->needsSecondApproval($payout)) {
                        throw new \DomainException(\App\Services\Finance\Payouts\PayoutService::uzs(\App\Services\Finance\Payouts\PayoutService::threshold())
                            . " va undan katta payoutni Boshqaruv markazida ikki xodim tasdiqlaydi.");
                    }

                    try {
                        $payouts->complete(static::whereKey($payout->getKey())->firstOrFail(), auth()->id(), $payout->resolution_notes);
                    } catch (\App\Services\Finance\Payouts\PayoutException $e) {
                        throw new \DomainException($e->getMessage());
                    }

                    return;
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

    /**
     * P4-23: the member who gave the first of two approvals (a payout of 1 000 000 so'm or more).
     *
     * @return BelongsTo<User, $this>
     */
    public function firstApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'first_approved_by');
    }
}
