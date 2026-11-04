<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6 local bridge (docs/POS_SPEC.md §5.5): a branch's LAN secret and hub (App\Domain\Pos\Lan\BranchLan reads and changes
 * it). One row per branch, made the first time the branch's LAN is asked for. The secret never leaves the server except
 * in the hub's own bootstrap; it is hidden from serialisation.
 *
 * @property int $id
 * @property int $store_id
 * @property int $branch_id
 * @property int|null $hub_device_id the owner's choice (null: the default hub)
 * @property int $port
 * @property int $key_id
 * @property string $secret base64 of 32 random bytes (encrypted at rest)
 * @property \Illuminate\Support\Carbon|null $rotated_at
 * @property int|null $rotated_by
 * @property int|null $issued_hub_device_id the device last given the hub's secret
 * @property int|null $previous_hub_device_id the one before it
 * @property \Illuminate\Support\Carbon|null $hub_changed_at when the secret went to another hub
 */
class BranchLanSettings extends Model
{
    protected $table = 'branch_lan_settings';

    protected $guarded = ['id'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'hub_device_id' => 'integer',
            'port' => 'integer',
            'key_id' => 'integer',
            'secret' => 'encrypted',
            'rotated_at' => 'datetime',
            'rotated_by' => 'integer',
            'issued_hub_device_id' => 'integer',
            'previous_hub_device_id' => 'integer',
            'hub_changed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
