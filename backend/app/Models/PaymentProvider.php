<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cast attributes, declared because Larastan does not read casts from the casts() method:
 *
 * @property bool $is_active
 * @property string $mode test | production (P4-31; read by App\Services\Payment\PaymentProviderSwitch)
 * @property array<string, mixed>|null $credentials
 */
class PaymentProvider extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'is_active',
        'mode',
        'credentials',
    ];

    protected static function booted(): void
    {
        // The order flow reads the switches through a cache (PaymentProviderSwitch)
        static::saved(fn () => \App\Services\Payment\PaymentProviderSwitch::forget());
        static::deleted(fn () => \App\Services\Payment\PaymentProviderSwitch::forget());
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'credentials' => 'encrypted:array',
        ];
    }

    /**
     * Platform merchant credential (service_id, merchant_id, secret_key): the superadmin-managed value
     * when set, otherwise config/services.php. Payment URLs and webhooks must resolve it the same way.
     */
    public static function platformCredential(string $provider, string $key): ?string
    {
        $value = static::where('slug', $provider)->first()?->credentials[$key] ?? null;

        if (blank($value)) {
            $value = config("services.{$provider}.{$key}");
        }

        return filled($value) ? trim((string) $value) : null;
    }
}
