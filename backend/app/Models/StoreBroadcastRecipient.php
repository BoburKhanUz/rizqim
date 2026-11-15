<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Wave M1: one owner chat of an ommaviy e'lon (StoreBroadcastService); `queued_at` once its message was handed to the
 * Telegram queue.
 *
 * @property int $id
 * @property int $store_broadcast_id
 * @property int|null $user_id
 * @property string $telegram_id
 * @property \Illuminate\Support\Carbon|null $queued_at
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class StoreBroadcastRecipient extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['store_broadcast_id', 'user_id', 'telegram_id', 'queued_at'];

    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
        ];
    }
}
