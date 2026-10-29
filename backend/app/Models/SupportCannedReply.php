<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P4-31: a tayyor javob of the support team (Shikoyatlar → Tayyor javoblar). Replies copy its text, so editing or
 * deleting it never changes what a customer already got.
 *
 * @property string $title
 * @property string $body
 * @property int $sort_order
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class SupportCannedReply extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
