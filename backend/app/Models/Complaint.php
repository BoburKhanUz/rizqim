<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
