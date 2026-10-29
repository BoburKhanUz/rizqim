<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P4-31: a message the RIZQIM support team sent to the customer about a complaint (through the bot), as sent.
 * Written by App\Services\Admin\Customers\ComplaintInbox only.
 *
 * @property int $complaint_id
 * @property int|null $author_id
 * @property string $body
 * @property int|null $canned_reply_id
 * @property string $channel
 */
class ComplaintReply extends Model
{
    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Complaint, $this>
     */
    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
