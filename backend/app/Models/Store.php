<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\Auditable;

class Store extends Model
{
    use SoftDeletes;
    use \App\Traits\HasPublicUuid;
    use Auditable;
    
    protected $guarded = ['id'];
    
    protected $appends = ['logo_url', 'banner_url', 'min_order', 'delivery_fee'];

    public function getMinOrderAttribute(): float
    {
        return (float) ($this->min_order_amount ?? 0);
    }

    public function getDeliveryFeeAttribute(): float
    {
        return (float) ($this->payment_settings['delivery_fee'] ?? 0);
    }

    protected static function booted()
    {
        static::creating(function ($store) {
            if (empty($store->slug)) {
                $store->slug = \Illuminate\Support\Str::slug($store->name) . '-' . uniqid();
            }
        });

        static::saving(function ($store) {
            // Two-way sync: JSON payment_settings.working_hours <-> open_time/close_time columns
            
            // 1. JSON -> Columns
            if (isset($store->payment_settings['working_hours'])) {
                $hours = $store->payment_settings['working_hours'];
                if (!empty($hours['open'])) {
                    $open = $hours['open'];
                    if (strlen($open) === 5) {
                        $open .= ':00';
                    }
                    $store->open_time = $open;
                }
                if (!empty($hours['close'])) {
                    $close = $hours['close'];
                    if (strlen($close) === 5) {
                        $close .= ':00';
                    }
                    $store->close_time = $close;
                }
            }
            
            // 2. Columns -> JSON (only if columns are changed)
            if ($store->isDirty(['open_time', 'close_time'])) {
                $settings = $store->payment_settings ?? [];
                if (!isset($settings['working_hours'])) {
                    $settings['working_hours'] = [];
                }
                if ($store->open_time) {
                    $settings['working_hours']['open'] = substr($store->open_time, 0, 5);
                }
                if ($store->close_time) {
                    $settings['working_hours']['close'] = substr($store->close_time, 0, 5);
                }
                $store->payment_settings = $settings;
            }

            // 3. Sync payment_settings JSON to columns for query compatibility
            $settings = $store->payment_settings ?? [];
            $store->accept_cash = (bool) ($settings['cash']['enabled'] ?? ($settings['cash'] ?? true));
            $store->accept_click = (bool) ($settings['click']['enabled'] ?? ($settings['click'] ?? false));
            $store->accept_payme = (bool) ($settings['payme']['enabled'] ?? ($settings['payme'] ?? false));
        });
    }

    public function getIsOpenAttribute($value): bool
    {
        if (!$value) {
            return false;
        }

        $openTime = $this->open_time ?? null;
        $closeTime = $this->close_time ?? null;

        if ($openTime && $closeTime) {
            // Normalize formatting
            if (strlen($openTime) === 5) $openTime .= ':00';
            if (strlen($closeTime) === 5) $closeTime .= ':00';

            // 24/7 open: if open time equals close time (e.g. 00:00:00 and 00:00:00)
            if ($openTime === $closeTime) {
                return true;
            }

            $now = now()->setTimezone($this->timezone ?? 'Asia/Tashkent')->format('H:i:s');

            if ($closeTime > $openTime) {
                // Standard hours: e.g. 09:00:00 to 22:00:00
                if ($now < $openTime || $now > $closeTime) {
                    return false;
                }
            } else {
                // Overnight hours: e.g. 18:00:00 to 02:00:00 (next day)
                if ($now < $openTime && $now > $closeTime) {
                    return false;
                }
            }
        }

        return true;
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) return null;
        if (filter_var($this->logo, FILTER_VALIDATE_URL)) return $this->logo;
        return asset('storage/' . $this->logo);
    }

    public function getBannerUrlAttribute(): ?string
    {
        if (!$this->banner) return null;
        if (filter_var($this->banner, FILTER_VALIDATE_URL)) return $this->banner;
        return asset('storage/' . $this->banner);
    }

    protected function casts(): array
    {
        return [
            'payment_settings' => 'array',
            'status' => \App\Enums\StoreStatus::class,
            'min_payout_reserve' => 'float',
        ];
    }
    
    public function getActivePaymentMethodsAttribute(): array
    {
        $settings = $this->payment_settings ?? [];
        $active = [];
        
        // Fallback for old data structure
        if (isset($settings['allowed_methods']) && is_array($settings['allowed_methods'])) {
            return $settings['allowed_methods'];
        }
        
        foreach (['cash', 'click', 'payme', 'uzum', 'paynet'] as $provider) {
            $isEnabled = false;
            if (isset($settings[$provider])) {
                if (is_array($settings[$provider])) {
                    $isEnabled = $settings[$provider]['enabled'] ?? false;
                } else {
                    $isEnabled = (bool) $settings[$provider];
                }
            }
            if ($isEnabled) {
                $active[] = $provider;
            }
        }
        return $active;
    }

    public function setActivePaymentMethodsAttribute(?array $methods): void
    {
        $methods = $methods ?? [];
        $currentSettings = $this->payment_settings ?? [];
        
        foreach (['cash', 'click', 'payme', 'uzum', 'paynet'] as $provider) {
            if (!isset($currentSettings[$provider]) || !is_array($currentSettings[$provider])) {
                // If it was a boolean, convert/wrap it into an array
                $oldValue = $currentSettings[$provider] ?? false;
                $currentSettings[$provider] = is_bool($oldValue) ? ['enabled' => $oldValue] : [];
            }
            $currentSettings[$provider]['enabled'] = in_array($provider, $methods);
        }
        
        $this->attributes['payment_settings'] = json_encode($currentSettings);
    }
    
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
    
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
    
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
    
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(StoreWalletTransaction::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(StoreRating::class);
    }
}
