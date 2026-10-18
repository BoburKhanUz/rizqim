<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreStatusLog extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'store_id',
        'from_status',
        'to_status',
        'admin_id',
        'comment',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
