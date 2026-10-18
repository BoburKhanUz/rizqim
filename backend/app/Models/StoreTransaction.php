<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreTransaction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'type' => \App\Enums\StoreTransactionType::class,
    ];
}
