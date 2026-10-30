<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6-23: one version of a wage rule of a staff member or a role in a branch (App\Domain\Pos\StaffPay\StaffPayRules),
 * in force from `effective_from` until the subject's next version.
 *
 * @property \Illuminate\Support\Carbon $effective_from
 * @property list<array<string, mixed>> $components App\Domain\Pos\StaffPay\PayComponents
 */
class StaffPayRule extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'components' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
