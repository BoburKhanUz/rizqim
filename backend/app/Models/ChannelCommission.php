<?php

namespace App\Models;

use App\Enums\OrderChannel;
use Illuminate\Database\Eloquent\Model;

/**
 * P6-55: the owner's commission percent on one sales channel (an aggregator's), for the channel net revenue report.
 *
 * @property int $store_id
 * @property OrderChannel $channel
 * @property numeric-string $percent
 * @property string|null $note
 */
class ChannelCommission extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'store_id' => 'integer',
        'channel' => OrderChannel::class,
        'percent' => 'decimal:2',
    ];
}
