<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentProvider extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'is_active',
        'credentials',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'credentials' => 'encrypted:array',
        ];
    }
}
