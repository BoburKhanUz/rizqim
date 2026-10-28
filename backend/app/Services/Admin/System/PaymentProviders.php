<?php

namespace App\Services\Admin\System;

use App\Models\PaymentProvider;

/**
 * P4-31 Tizim → To'lov tizimlari: the platform's own merchant accounts (Payme, Click, Uzum Bank, Paynet; the
 * `payment_providers` table the old superadmin panel edited). The checkout links and the webhooks read a key through
 * PaymentProvider::platformCredential(): the value stored here, else config/services.php (.env). This service shows that
 * resolution without the values (SecretMask) and changes the stored ones one key at a time.
 *
 * `is_active` and `mode` (test / production) are the team's switches; P4-32: the order flow reads them through
 * App\Services\Payment\PaymentProviderSwitch (an inactive provider is not offered and takes no new payment; the mode
 * picks Payme's sandbox checkout).
 */
final class PaymentProviders
{
    /**
     * The keys each provider's code knows, with their names. A stored key outside this list is shown too.
     *
     * @var array<string, array<string, string>>
     */
    public const KNOWN_KEYS = [
        'payme' => ['merchant_id' => 'Merchant ID', 'secret_key' => 'Maxfiy kalit'],
        'click' => ['service_id' => 'Service ID', 'merchant_id' => 'Merchant ID', 'secret_key' => 'Maxfiy kalit', 'merchant_user_id' => 'Merchant API foydalanuvchisi'],
        'uzum' => ['terminal_id' => 'Terminal ID', 'secret_key' => 'Maxfiy kalit'],
        'paynet' => ['merchant_id' => 'Merchant ID', 'secret_key' => 'Maxfiy kalit'],
    ];

    // Wired into checkout and the webhooks (App\Services\Order\PaymentLinkService, Payme/ClickWebhookController)
    public const WIRED = ['payme', 'click'];

    public const MODES = ['test', 'production'];

    // The old Payme seed kept the mode inside the credentials; it is the `mode` column now and not a credential
    private const NOT_CREDENTIALS = ['test_mode'];

    public const ROW = 'array{id: int, slug: string, name: string, is_active: bool, mode: string, wired: bool, credentials: list<array{key: string, label: string, known: bool, configured: bool, last4: string|null, source: string|null}>, updated_at: string|null}';

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        return PaymentProvider::query()->orderBy('id')->get()->map(fn (PaymentProvider $provider) => $this->row($provider))->values()->all();
    }

    public function find(string $slug): ?PaymentProvider
    {
        return PaymentProvider::query()->where('slug', $slug)->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function row(PaymentProvider $provider): array
    {
        $slug = (string) $provider->slug;
        $stored = $this->stored($provider);
        $known = self::KNOWN_KEYS[$slug] ?? [];

        $keys = array_values(array_unique([...array_keys($known), ...array_keys($stored)]));
        $credentials = [];
        foreach ($keys as $key) {
            $value = $stored[$key] ?? null;
            $source = filled($value) ? 'settings' : null;
            if ($source === null) {
                $value = config("services.{$slug}.{$key}");
                $source = filled($value) ? 'env' : null;
            }
            $credentials[] = [
                'key' => $key,
                'label' => $known[$key] ?? $key,
                'known' => array_key_exists($key, $known),
                ...SecretMask::describe($value),
                'source' => $source,
            ];
        }

        return [
            'id' => (int) $provider->id,
            'slug' => $slug,
            'name' => (string) $provider->name,
            'is_active' => (bool) $provider->is_active,
            'mode' => (string) ($provider->getAttribute('mode') ?? 'production'),
            'wired' => in_array($slug, self::WIRED, true),
            'credentials' => $credentials,
            'updated_at' => $provider->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array{is_active?: bool, mode?: string}  $change
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     */
    public function update(PaymentProvider $provider, array $change): array
    {
        $before = ['is_active' => (bool) $provider->is_active, 'mode' => (string) ($provider->getAttribute('mode') ?? 'production')];
        $after = $before;
        if (array_key_exists('is_active', $change)) {
            $after['is_active'] = (bool) $change['is_active'];
        }
        if (array_key_exists('mode', $change)) {
            $after['mode'] = (string) $change['mode'];
        }

        if ($after !== $before) {
            $provider->forceFill($after)->save();
        }

        return ['before' => $before, 'after' => $after];
    }

    /**
     * Whether a key may be written for this provider: one its code knows, or one already stored.
     */
    public function acceptsKey(PaymentProvider $provider, string $key): bool
    {
        if (in_array($key, self::NOT_CREDENTIALS, true)) {
            return false;
        }

        return array_key_exists($key, self::KNOWN_KEYS[(string) $provider->slug] ?? []) || array_key_exists($key, $this->stored($provider));
    }

    /**
     * Stores (or, with null, removes: the .env value applies again) one credential. Never answers the value.
     *
     * @return array{was_stored: bool, changed: bool}
     */
    public function replaceCredential(PaymentProvider $provider, string $key, ?string $value): array
    {
        if (!$this->acceptsKey($provider, $key)) {
            throw new SystemAdminException("Bu to'lov tizimida bunday kalit yo'q.", 'unknown_key');
        }

        $credentials = $provider->credentials ?? [];
        $wasStored = filled($credentials[$key] ?? null);

        if ($value === null) {
            if (!array_key_exists($key, $credentials)) {
                return ['was_stored' => false, 'changed' => false];
            }
            unset($credentials[$key]);
        } else {
            $credentials[$key] = trim($value);
        }

        $provider->forceFill(['credentials' => $credentials])->save();

        return ['was_stored' => $wasStored, 'changed' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(PaymentProvider $provider): array
    {
        $stored = is_array($provider->credentials) ? $provider->credentials : [];

        return array_diff_key($stored, array_flip(self::NOT_CREDENTIALS));
    }
}
