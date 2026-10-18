<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\PaymentStatus;

class Payment extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'payload' => 'array',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
