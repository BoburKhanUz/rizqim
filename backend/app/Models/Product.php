<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\Auditable;

class Product extends Model
{
    use SoftDeletes;
    use \App\Traits\HasPublicUuid;
    use Auditable;
    
    protected $guarded = ['id'];
    
    protected $appends = ['image_url', 'is_active'];

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function setIsActiveAttribute($value): void
    {
        $this->attributes['status'] = $value ? 'active' : 'inactive';
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }
        
        // Handle fully qualified URLs or local storage files
        if (filter_var($this->image, FILTER_VALIDATE_URL)) {
            return $this->image;
        }

        return asset('storage/' . $this->image);
    }
    
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
    
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}
