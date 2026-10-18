<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use SoftDeletes;
    use \App\Traits\HasPublicUuid;
    
    protected $guarded = ['id'];
    
    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) return null;
        if (filter_var($this->image, FILTER_VALIDATE_URL)) return $this->image;
        return asset('storage/' . $this->image);
    }
    
    protected static function booted()
    {
        static::creating(function ($category) {
            if (is_null($category->sort_order) || $category->sort_order <= 0) {
                $category->sort_order = (self::max('sort_order') ?? 0) + 1;
            }
            
            // Shift other categories forward
            self::where('sort_order', '>=', $category->sort_order)->increment('sort_order');
        });

        static::updating(function ($category) {
            if ($category->isDirty('sort_order')) {
                $newOrder = $category->sort_order;
                $oldOrder = $category->getOriginal('sort_order');
                
                if ($newOrder < $oldOrder) {
                    // Moving up (e.g. 5 to 2): shift items from 2 to 4 down by 1 (i.e. +1)
                    self::where('id', '!=', $category->id)
                        ->whereBetween('sort_order', [$newOrder, $oldOrder - 1])
                        ->increment('sort_order');
                } elseif ($newOrder > $oldOrder) {
                    // Moving down (e.g. 2 to 5): shift items from 3 to 5 up by 1 (i.e. -1)
                    self::where('id', '!=', $category->id)
                        ->whereBetween('sort_order', [$oldOrder + 1, $newOrder])
                        ->decrement('sort_order');
                }
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }
    
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
    
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
