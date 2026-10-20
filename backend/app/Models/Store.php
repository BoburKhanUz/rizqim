<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\Auditable;

/**
 * Cast attributes, declared because Larastan does not read casts from the casts() method:
 *
 * @property array<string, mixed>|null $payment_settings
 * @property \App\Enums\StoreStatus $status
 * @property numeric-string $debt_balance
 * @property numeric-string $wallet_balance
 * @property numeric-string $credit_limit
 * @property numeric-string $min_payout_reserve
 * @property numeric-string $min_order_amount
 *
 * Loaded with withCount('branches'):
 *
 * @property-read int|null $branches_count
 */
class Store extends Model
{
    use SoftDeletes;
    use \App\Traits\HasPublicUuid;
    use Auditable;
    
    // Ledger balances change only through LedgerService (explicit forceFill), never via mass assignment.
    protected $guarded = ['id', 'debt_balance', 'wallet_balance'];

    protected $hidden = ['telegram_bot_token'];

    protected $appends = ['logo_url', 'banner_url', 'min_order', 'delivery_fee', 'is_order_locked'];

    /**
     * Internal and financial columns left out of serialized stores (API responses, relations). Not $hidden, because
     * Filament fills its forms from attributesToArray(), which honours $hidden; owner endpoints list them explicitly.
     */
    public const INTERNAL_ATTRIBUTES = [
        'debt_balance', 'wallet_balance', 'credit_limit', 'low_balance_threshold', 'min_payout_reserve',
        'commission_rate', 'payment_account_type', 'settlement_type',
        'legal_name', 'tax_number', 'bank_account', 'moderation_note', 'telegram_admin_chat_id',
    ];

    /**
     * Whether new orders are refused: the rule CreateOrderAction applies (payment_required / finance_hold, or debt
     * over the credit limit).
     */
    public function getIsOrderLockedAttribute(): bool
    {
        $status = $this->status->value ?? $this->status;

        if (in_array($status, [\App\Enums\StoreStatus::PAYMENT_REQUIRED->value, \App\Enums\StoreStatus::FINANCE_HOLD->value], true)) {
            return true;
        }

        // Only decidable when the debt columns were selected
        if (!array_key_exists('debt_balance', $this->attributes) || !array_key_exists('credit_limit', $this->attributes)) {
            return false;
        }

        return $this->exceedsCreditLimit();
    }

    /**
     * Whether a debt (the store's own by default) is over its credit limit, the most the store may owe the platform.
     * The limit is kept as a negative balance floor (-100000 = up to 100 000 UZS of debt), so its absolute value
     * counts, and a debt equal to it is still allowed: with a limit of 0 a debt-free store sells, any debt blocks it.
     * Every refusal, block and unblock (CreateOrderAction, LedgerService, is_order_locked) uses this one rule.
     */
    public function exceedsCreditLimit(\App\Support\Money|string|int|float|null $debt = null): bool
    {
        return \App\Support\Money::of(func_num_args() > 0 ? $debt : $this->debt_balance)
            ->isGreaterThan(\App\Support\Money::of($this->credit_limit)->abs());
    }

    /**
     * Whether a payment_settings key holds secret material (secret_key, api_key, password, token...).
     */
    public static function isSecretKey($key): bool
    {
        return is_string($key) && preg_match('/secret|password|passwd|token|private|(^|_)key$/i', $key) === 1;
    }

    /**
     * Payment settings with every secret removed, at any depth.
     */
    public static function withoutSecrets(array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (static::isSecretKey($key)) {
                unset($settings[$key]);
            } elseif (is_array($value)) {
                $settings[$key] = static::withoutSecrets($value);
            }
        }

        return $settings;
    }

    /**
     * Payment providers: each has a section in payment_settings ({enabled, merchant credentials...}).
     */
    public const PAYMENT_PROVIDERS = ['cash', 'click', 'payme', 'uzum', 'paynet'];

    /**
     * Older rows (the first creation wizard) hold a provider as a bare boolean (`cash: true`), which every reader takes
     * as {enabled: true}; forms and saves work on that section form instead.
     */
    public static function normalizePaymentSettings(array $settings): array
    {
        foreach (self::PAYMENT_PROVIDERS as $provider) {
            if (isset($settings[$provider]) && !is_array($settings[$provider])) {
                $settings[$provider] = ['enabled' => (bool) $settings[$provider]];
            }
        }

        return $settings;
    }

    /**
     * Payment settings as the panel forms edit them: a switch that is missing gets what checkout makes of a missing
     * key (cash, delivery and pickup on; online methods off), so saving a form never turns anything off by itself.
     */
    public static function paymentSettingsForForm(array $settings): array
    {
        $settings = static::normalizePaymentSettings($settings);

        foreach (self::PAYMENT_PROVIDERS as $provider) {
            if (!is_array($settings[$provider] ?? null)) {
                $settings[$provider] = ['enabled' => $provider === 'cash'];
            }
        }

        $settings['delivery_enabled'] ??= true;
        $settings['pickup_enabled'] ??= true;

        return $settings;
    }

    /**
     * Settings a new store starts with: every provider as a section (one not offered yet stays off), delivery and pickup on.
     *
     * @param list<string> $enabledMethods
     */
    public static function initialPaymentSettings(array $enabledMethods): array
    {
        $settings = [];
        foreach (self::PAYMENT_PROVIDERS as $provider) {
            $settings[$provider] = ['enabled' => in_array($provider, $enabledMethods, true) && static::isPaymentMethodAvailable($provider)];
        }

        return $settings + ['delivery_enabled' => true, 'pickup_enabled' => true];
    }

    /**
     * Payment methods not offered yet (config orders.unavailable_payment_methods): checkout refuses them and clients
     * see them switched off, whatever a store saved for them (which is kept).
     *
     * @return list<string>
     */
    public static function unavailablePaymentMethods(): array
    {
        return array_values((array) config('orders.unavailable_payment_methods', []));
    }

    public static function isPaymentMethodAvailable(string $method): bool
    {
        return !in_array($method, static::unavailablePaymentMethods(), true);
    }

    /**
     * Serialized stores (API responses, relations) never carry payment secrets, and show methods not offered yet as
     * {enabled: false, available: false}; internal reads are unaffected.
     */
    public function toArray()
    {
        $array = array_diff_key(parent::toArray(), array_flip(self::INTERNAL_ATTRIBUTES));

        if (array_key_exists('payment_settings', $array)) {
            $settings = is_array($array['payment_settings']) ? static::withoutSecrets($array['payment_settings']) : [];

            foreach (static::unavailablePaymentMethods() as $method) {
                $settings[$method] = ['enabled' => false, 'available' => false];
            }

            $array['payment_settings'] = $settings;
        }

        return $array;
    }

    /**
     * Merchant credentials a store needs to collect a provider's online payments into its own account:
     * the ids its payment URL uses and the key its webhooks are verified with.
     */
    public const OWN_MERCHANT_CREDENTIALS = [
        'click' => ['service_id', 'merchant_id', 'secret_key'],
        'payme' => ['merchant_id', 'secret_key'],
        'uzum' => ['merchant_id', 'secret_key'],
        'paynet' => ['merchant_id', 'secret_key'],
    ];

    /**
     * Whether this store's online payments via $provider go to its own merchant account; otherwise the platform collects them.
     */
    public function collectsOwnPayments(string $provider): bool
    {
        $config = $this->payment_settings[$provider] ?? null;

        if ($this->payment_account_type !== 'own_account' || !is_array($config) || !isset(self::OWN_MERCHANT_CREDENTIALS[$provider])) {
            return false;
        }

        foreach (self::OWN_MERCHANT_CREDENTIALS[$provider] as $credential) {
            if (blank($config[$credential] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Merge incoming payment settings into the stored ones: keys a writer leaves out (e.g. form sections that were
     * hidden) keep their stored value, nested sections merge the same way, lists are replaced whole, and a secret
     * is only replaced by a new non-empty value (clients only ever see secrets removed).
     */
    protected static function mergeSettings(array $stored, array $incoming): array
    {
        foreach ($stored as $key => $value) {
            if (!array_key_exists($key, $incoming)) {
                $incoming[$key] = $value;
            } elseif (static::isSecretKey($key)) {
                $new = $incoming[$key];
                if (filled($value) && ($new === null || (is_string($new) && preg_match('/^[\s*•]*$/u', $new)))) {
                    $incoming[$key] = $value;
                }
            } elseif (is_array($value) && !array_is_list($value) && is_array($incoming[$key])
                && ($incoming[$key] === [] || !array_is_list($incoming[$key]))) {
                $incoming[$key] = static::mergeSettings($value, $incoming[$key]);
            }
        }

        return $incoming;
    }

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
            // Both sides in section form, so a legacy `cash: true` merges with what a form sends for cash
            if ($store->exists && $store->isDirty('payment_settings')) {
                $store->payment_settings = static::mergeSettings(
                    static::normalizePaymentSettings($store->getOriginal('payment_settings') ?? []),
                    static::normalizePaymentSettings($store->payment_settings ?? [])
                );
            } elseif (!$store->exists && is_array($store->payment_settings)) {
                $store->payment_settings = static::normalizePaymentSettings($store->payment_settings);
            }

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
            'debt_balance' => 'decimal:2',
            'wallet_balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'min_payout_reserve' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
        ];
    }
    
    public function getActivePaymentMethodsAttribute(): array
    {
        $settings = $this->payment_settings ?? [];
        $active = [];
        
        // Fallback for old data structure
        if (isset($settings['allowed_methods']) && is_array($settings['allowed_methods'])) {
            return array_values(array_filter($settings['allowed_methods'], fn ($method) => static::isPaymentMethodAvailable((string) $method)));
        }
        
        foreach (self::PAYMENT_PROVIDERS as $provider) {
            if (!static::isPaymentMethodAvailable($provider)) {
                continue;
            }

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
        
        foreach (self::PAYMENT_PROVIDERS as $provider) {
            if (!static::isPaymentMethodAvailable($provider)) {
                continue; // what the store saved for a method not offered yet is kept
            }

            if (!isset($currentSettings[$provider]) || !is_array($currentSettings[$provider])) {
                // If it was a boolean, convert/wrap it into an array
                $oldValue = $currentSettings[$provider] ?? false;
                $currentSettings[$provider] = is_bool($oldValue) ? ['enabled' => $oldValue] : [];
            }
            $currentSettings[$provider]['enabled'] = in_array($provider, $methods);
        }
        
        $this->attributes['payment_settings'] = json_encode($currentSettings);
    }
    
    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
    
    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
    
    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
    
    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<PayoutRequest, $this>
     */
    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class);
    }

    /**
     * @return HasMany<StoreWalletTransaction, $this>
     */
    public function walletTransactions(): HasMany
    {
        return $this->hasMany(StoreWalletTransaction::class);
    }

    /**
     * @return HasMany<StoreRating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(StoreRating::class);
    }

    // --- P1-23: tariffs and subscription billing (docs/BILLING.md) ---

    /**
     * The commission percentage charged on this store's online orders: the current tariff plan's when the plan sets
     * one, otherwise the store's own commission_rate (10 when unset), exactly as before plans existed.
     * CreateOrderAction snapshots it on each order and the ledger charges that snapshot.
     *
     * @return numeric-string
     */
    public function commissionRate(): string
    {
        $planRate = $this->exists
            ? TariffPlan::query()
                ->whereIn('id', StoreSubscription::query()->current()->where('store_id', $this->id)->select('tariff_plan_id'))
                ->value('commission_percent')
            : null;

        $rate = (string) ($planRate ?? $this->commission_rate ?? 10);

        return is_numeric($rate) ? $rate : '10';
    }

    /**
     * Every subscription the store has had, the current one included.
     *
     * @return HasMany<StoreSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(StoreSubscription::class);
    }

    /**
     * @return HasMany<SubscriptionInvoice, $this>
     */
    public function subscriptionInvoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    // --- end P1-23 ---
}
