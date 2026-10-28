<?php

namespace Tests\Feature\AdminFinance;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\Store;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Services\Finance\LedgerService;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AdminFixtures;
use Tests\Feature\BillingV2\BillingV2Fixtures;

/**
 * P4-23: admin members (sign-in, step-up), v2 stores with subscription invoices, platform-collected orders and payout
 * requests for the Moliya tests.
 */
trait FinanceFixtures
{
    use AdminFixtures;
    use BillingV2Fixtures;

    private ?User $financeCustomer = null;

    protected function ledger(): LedgerService
    {
        return app(LedgerService::class);
    }

    /**
     * A v2 Start store billed for September and October 2026: two invoices of 149 000 on its debt (298 000).
     */
    protected function storeWithTwoInvoices(string $name = 'Netting Cafe', array $attributes = []): Store
    {
        $this->withoutTrials();
        $store = $this->v2Store($name, $attributes);
        $this->billing()->assignPlan($store, $this->v2('start'), false, null, $this->at('2026-09-01 00:00'));
        $this->billing()->run($this->at('2026-11-01 00:15'));

        return $store->refresh();
    }

    /**
     * @return list<SubscriptionInvoice> oldest first
     */
    protected function invoicesOf(Store $store): array
    {
        return SubscriptionInvoice::where('store_id', $store->id)->orderBy('period')->get()->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function financeOrder(Store $store, array $attributes = []): Order
    {
        $this->financeCustomer ??= User::create(['name' => 'Mijoz', 'phone' => '+998901110077', 'password' => bcrypt('x')]);

        return Order::create($attributes + [
            'store_id' => $store->id,
            'user_id' => $this->financeCustomer->id,
            'status' => OrderStatus::DELIVERED,
            'order_type' => 'pickup',
            'payment_method' => 'cash',
            'total_price' => 100000,
            'commission_amount' => 0,
        ]);
    }

    /**
     * A delivered order paid online to RIZQIM ($provider: click, payme, keshbek) with its payment row.
     */
    protected function platformOrder(Store $store, string $provider, string $total, string $commission = '0'): Order
    {
        $order = $this->financeOrder($store, [
            'payment_method' => $provider,
            'payment_owner' => 'PLATFORM',
            'payment_status' => PaymentStatus::PAID,
            'total_price' => $total,
            'commission_amount' => $commission,
        ]);
        Payment::create(['order_id' => $order->id, 'provider' => $provider, 'amount' => $total, 'status' => 'paid', 'paid_at' => now()]);

        return $order->refresh();
    }

    protected function payout(Store $store, string $amount, string $card = '8600123456789012'): PayoutRequest
    {
        return PayoutRequest::create(['store_id' => $store->id, 'amount' => $amount, 'card_number' => $card, 'status' => 'pending']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function financeJson(string $method, string $uri, string $token, array $data = [], array $headers = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($token)->withHeaders($headers)->json($method, '/api/v1/admin' . $uri, $data);
    }
}
