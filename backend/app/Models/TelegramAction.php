<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramAction extends Model
{
    use MassPrunable;

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /**
     * M3: a button token is useless once expired (24 h); kept 30 days more for support questions, then deleted by the
     * daily `php artisan model:prune`.
     *
     * @return Builder<TelegramAction>
     */
    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now()->subDays(30));
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
