<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * P4-31: the support side (support_status, assigned_to, answered_at, resolved_at, replies) is set by
 * App\Services\Admin\Customers\ComplaintInbox only, so those columns are not mass assignable.
 *
 * @property string $status pending | owner_replied | in_review | closed (the customer ↔ store flow)
 * @property \App\Enums\ComplaintSupportStatus $support_status
 * @property int|null $assigned_to
 * @property \Illuminate\Support\Carbon|null $support_status_changed_at
 * @property \Illuminate\Support\Carbon|null $answered_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 */
class Complaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'type',
        'description',
        'status',
        'reply',
        'resolution_comment',
        'resolved_by',
    ];

    /**
     * The team member who took it is the platform's business, not the customer's or the store's (their complaint APIs
     * serialize the model).
     *
     * @var list<string>
     */
    protected $hidden = ['assigned_to'];

    protected function casts(): array
    {
        return [
            'support_status' => \App\Enums\ComplaintSupportStatus::class,
            'support_status_changed_at' => 'datetime',
            'answered_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * A new complaint starts on the support side as `new` (the column's default, also on a model built in memory).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'support_status' => 'new',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });

        static::updated(function ($model) {
            if ($model->wasChanged('reply') || $model->wasChanged('resolution_comment') || $model->wasChanged('status')) {
                \App\Models\NotificationLog::firstOrCreate([
                    'user_id' => $model->user_id,
                    'order_id' => $model->order_id,
                    'type' => 'complaint_reply',
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }
        });
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * P4-31: what the support team wrote to the customer, oldest first.
     *
     * @return HasMany<ComplaintReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ComplaintReply::class)->orderBy('id');
    }
}
