<?php

namespace Tests\Feature\PosQr;

use App\Models\Branch;
use App\Models\Order;
use App\Models\PosQrPayment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosBilling\PosBillingFixtures;

/**
 * P6-41/P6-42 helpers: the P3-18 till on a `restoran` branch (qr_payments on), the store collecting Click and Payme
 * into its own merchant account (keys below), a billed order, the till's QR requests, the Click SHOP API and Payme
 * Merchant API callbacks as the providers sign them, and owner requests.
 */
trait PosQrFixtures
{
    use PosBillingFixtures;

    protected const CLICK_SECRET = 'store_click_secret';

    protected const CLICK_SERVICE = '41001';

    protected const PAYME_SECRET = 'store_payme_key';

    protected const PAYME_MERCHANT = '65f0c0ffee0000000000aaaa';

    protected function setUpQr(string $profile = 'restoran'): void
    {
        $this->setUpPosBilling();
        config(['qr_payments.sandbox' => false, 'qr_payments.simulate' => true, 'services.payme.secret_key' => 'platform_payme_key', 'services.click.secret_key' => 'platform_click_secret']);
        $this->profile($this->branch, $profile);
        $this->ownMerchant();
    }

    protected function profile(Branch $branch, string $profile, array $modules = []): void
    {
        $settings = is_array($branch->fresh()->pos_settings) ? $branch->fresh()->pos_settings : [];
        Branch::whereKey($branch->id)->update(['pos_settings' => json_encode(['profile' => $profile, 'modules' => $modules] + $settings)]);
        app()->forgetScopedInstances();
    }

    protected function ownMerchant(bool $click = true, bool $payme = true): void
    {
        $this->store->forceFill([
            'payment_account_type' => 'own_account',
            'fiscal_settings' => ['enabled' => true],
            'payment_settings' => [
                'cash' => ['enabled' => true],
                'click' => $click
                    ? ['enabled' => true, 'service_id' => self::CLICK_SERVICE, 'merchant_id' => '22001', 'merchant_user_id' => '33001', 'secret_key' => self::CLICK_SECRET]
                    : ['enabled' => false],
                'payme' => $payme
                    ? ['enabled' => true, 'merchant_id' => self::PAYME_MERCHANT, 'secret_key' => self::PAYME_SECRET]
                    : ['enabled' => false],
            ],
        ])->save();
    }

    /**
     * A hall order billed once: [order, bill uuid].
     *
     * @return array{0: Order, 1: string}
     */
    protected function billed(array $lines = [['Osh', '1', '143000']]): array
    {
        $order = $this->posOrder($lines, ['waiter_id' => $this->waiter->id]);

        return [$order, (string) $this->bill($order)->assertCreated()->json('data.bills.0.uuid')];
    }

    protected function qr(string $billUuid, ?string $provider = 'click', array $extra = []): TestResponse
    {
        return $this->pos('POST', "/bills/{$billUuid}/qr", array_filter(['provider' => $provider], fn ($v) => $v !== null) + $extra);
    }

    protected function qrModel(string $uuid): PosQrPayment
    {
        return PosQrPayment::where('uuid', $uuid)->firstOrFail();
    }

    /**
     * A Click SHOP API callback (action 0 Prepare, 1 Complete) signed with $secret.
     */
    protected function click(int $action, PosQrPayment $qr, ?string $amount = null, string $clickTransId = '7700001', ?string $secret = self::CLICK_SECRET, int|string|null $prepareId = null, array $overrides = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $amount ??= Money::of($qr->amount)->toString();
        $prepareId ??= $qr->id;
        $signTime = '2026-11-19 12:00:00';
        $account = $qr->account();
        $data = [
            'click_trans_id' => $clickTransId,
            'service_id' => self::CLICK_SERVICE,
            'click_paydoc_id' => '880001',
            'merchant_trans_id' => $account,
            'amount' => $amount,
            'action' => $action,
            'error' => 0,
            'error_note' => 'Success',
            'sign_time' => $signTime,
            'sign_string' => md5($clickTransId . self::CLICK_SERVICE . $secret . $account . ($action === 1 ? $prepareId : '') . $amount . $action . $signTime),
        ];
        if ($action === 1) {
            $data['merchant_prepare_id'] = $prepareId;
        }

        return $this->postJson('/api/v1/payments/click/webhook', array_merge($data, $overrides));
    }

    /**
     * A Payme Merchant API call with Basic `Paycom:$key`.
     */
    protected function payme(string $method, array $params, string $key = self::PAYME_SECRET): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withHeader('Authorization', 'Basic ' . base64_encode("Paycom:{$key}"))
            ->postJson('/api/v1/payments/payme/webhook', ['jsonrpc' => '2.0', 'id' => 101, 'method' => $method, 'params' => $params]);
    }

    protected function paymeAccount(PosQrPayment $qr): array
    {
        return ['order_id' => $qr->account()];
    }

    protected function owner(string $method, string $uri, array $data = [], ?User $user = null): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->actingAs($user ?? $this->owner, 'sanctum')->json($method, '/api/v1' . $uri, $data);
    }
}
