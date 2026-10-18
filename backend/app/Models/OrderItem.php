<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use SoftDeletes;
    
    protected $guarded = ['id'];
    
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
    
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function variant(): BelongsTo
    {
        return $this->productVariant();
    }
}
