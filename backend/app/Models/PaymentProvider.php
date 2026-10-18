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
