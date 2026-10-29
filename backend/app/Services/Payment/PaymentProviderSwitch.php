<?php

namespace App\Services\Payment;

use App\Models\PaymentProvider;
use Illuminate\Support\Facades\Cache;

/**
 * P4-32 (owner decision): the team's switches for the platform payment providers (Tizim → To'lov tizimlari, the
 * `payment_providers` rows) as the order flow reads them.
 *
 *  - `is_active` false: the provider is off for every store. The Mini App does not offer it (serialized stores show it
 *    {enabled: false, available: false}), checkout and store top-ups refuse it with `payment_provider_inactive`. What a
 *    store saved for it is kept, so switching it back on restores every store as it was. The webhooks never ask: a
 *    payment already started is confirmed, cancelled or refunded as usual.
 *  - `mode` test / production: which checkout host a payment through the platform's own merchant account opens. Payme
 *    has a sandbox (checkout.test.paycom.uz); Click has one host (my.click.uz) for both, its test is a test service.
 *    The keys are the ones stored for the provider (or the .env ones) whatever the mode, exactly as when the mode was a
 *    `test_mode` entry beside them: while testing, the team stores the provider's test keys. A store collecting into its
 *    own merchant account (own_account) always uses production.
 *
 * A provider without a row (keys only in .env) is on and in production. The rows are read once per request or job (a
 * scoped binding, AppServiceProvider) through the cache; saving or deleting a row forgets both (PaymentProvider::booted).
 */
final class PaymentProviderSwitch
{
    public const INACTIVE_CODE = 'payment_provider_inactive';

    private const CACHE_KEY = 'payment-providers:switch';

    private const CACHE_TTL = 300;

    private const PAYME_CHECKOUT = ['production' => 'https://checkout.paycom.uz/', 'test' => 'https://checkout.test.paycom.uz/'];

    private const CLICK_CHECKOUT = 'https://my.click.uz/services/pay';

    /** @var array<string, array{is_active: bool, mode: string}>|null the rows of this request or job (a scoped binding) */
    private ?array $state = null;

    public function isActive(string $slug): bool
    {
        return $this->state()[$slug]['is_active'] ?? true;
    }

    /**
     * @return 'test'|'production'
     */
    public function mode(string $slug): string
    {
        return ($this->state()[$slug]['mode'] ?? 'production') === 'test' ? 'test' : 'production';
    }

    /**
     * The Payme checkout host: the sandbox for the platform account in test mode, else production.
     */
    public function paymeCheckoutBase(bool $platformAccount = true): string
    {
        return self::PAYME_CHECKOUT[$platformAccount ? $this->mode('payme') : 'production'];
    }

    public function clickCheckoutBase(): string
    {
        return self::CLICK_CHECKOUT;
    }

    /**
     * The Click checkout link: `transaction_param` is what the webhooks find the order (or `topup_<uuid>`) by.
     */
    public function clickUrl(?string $serviceId, ?string $merchantId, string $amount, string $transactionParam, string $returnUrl): string
    {
        return $this->clickCheckoutBase()
            . "?service_id={$serviceId}"
            . "&merchant_id={$merchantId}"
            . "&amount={$amount}"
            . "&transaction_param={$transactionParam}"
            . '&return_url=' . urlencode($returnUrl);
    }

    /**
     * The Payme checkout link (amount in tiyin; `ac.order_id` is what the webhooks find the order or top-up by).
     */
    public function paymeUrl(?string $merchantId, int $tiyin, string $orderId, string $returnUrl, bool $platformAccount = true): string
    {
        return $this->paymeCheckoutBase($platformAccount)
            . base64_encode("m={$merchantId};ac.order_id={$orderId};a={$tiyin};c=" . urlencode($returnUrl));
    }

    /**
     * Where the customer lands after paying an order: the Mini App (the old store panel /admin is gone), else the site.
     */
    public static function orderReturnUrl(): string
    {
        $miniApp = trim((string) config('services.telegram.webapp_url'));

        return $miniApp !== '' ? $miniApp : url('/');
    }

    /**
     * Where the owner lands after paying a top-up: the partner web panel's Moliya page, else the site.
     */
    public static function topupReturnUrl(): string
    {
        $panel = rtrim(trim((string) config('services.partner_web.url')), '/');

        return $panel !== '' ? $panel . '/finance' : url('/');
    }

    /**
     * The Uzbek refusal for a switched-off provider.
     */
    public static function inactiveMessage(string $slug): string
    {
        return ucfirst($slug) . " orqali to'lov vaqtincha o'chirilgan. Boshqa to'lov usulini tanlang.";
    }

    public static function refusal(string $slug): PaymentProviderInactiveException
    {
        return PaymentProviderInactiveException::for($slug);
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        if (app()->resolved(self::class)) {
            app(self::class)->state = null;
        }
    }

    /**
     * @return array<string, array{is_active: bool, mode: string}>
     */
    private function state(): array
    {
        return $this->state ??= Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => PaymentProvider::query()
            ->get(['slug', 'is_active', 'mode'])
            ->mapWithKeys(fn (PaymentProvider $provider) => [(string) $provider->slug => [
                'is_active' => (bool) $provider->is_active,
                'mode' => (string) ($provider->getAttribute('mode') ?? 'production'),
            ]])
            ->all());
    }
}
