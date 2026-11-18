<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

class StoreTransaction extends Model
{
    use BelongsToStore;

    protected $guarded = [];

    protected $casts = [
        'type' => \App\Enums\StoreTransactionType::class,
    ];
}
