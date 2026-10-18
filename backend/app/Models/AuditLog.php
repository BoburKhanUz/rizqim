<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    // No default timestamps columns (only created_at exists, we disable updated_at)
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'payload_json',
    ];

    protected $casts = [
        'payload_json' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
