<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneVerification extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];
}
