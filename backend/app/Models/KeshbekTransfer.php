<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Owner decision A: a keshbek balance moved from one users row to another (migration 2026_12_12_100000): today only the
 * unclaimed invite row's balance handed to the account that accepted the invite (User::mergeInviteInto). Read by
 * KeshbekLiabilityReport (`transferred`, netting to zero platform-wide) and KeshbekHistory ("Taklif hisobidan
 * ko'chirildi"). Append-only: saving an existing record or deleting one throws.
 *
 * @property int $id
 * @property int $from_user_id
 * @property int|null $to_user_id
 * @property string $amount
 * @property string $reason
 * @property list<int>|null $moved_friend_ids
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class KeshbekTransfer extends Model
{
    public const REASON_INVITE_MERGE = 'invite_merge';

    const UPDATED_AT = null;

    protected $fillable = ['from_user_id', 'to_user_id', 'amount', 'reason', 'moved_friend_ids', 'created_at'];

    protected $casts = [
        'amount' => 'decimal:2',
        'moved_friend_ids' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Keshbek transfer records cannot be changed.');
        });

        static::deleting(function (): never {
            throw new \LogicException('Keshbek transfer records cannot be deleted.');
        });
    }
}
