<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramAction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
