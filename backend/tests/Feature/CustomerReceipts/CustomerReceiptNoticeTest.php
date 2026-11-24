<?php

namespace Tests\Feature\CustomerReceipts;

use App\Domain\Fiscal\FiscalReceiptLock;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Integrations\FiscalizedBy;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\SendCustomerReceiptNotice;
use App\Models\FiscalReceipt;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\Payment;
use App\Services\Payment\Fiscal\OnlineFiscalRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * Wave P (receipts): once a sale or refund receipt of a customer's order is registered, the bot sends the customer one
 * message with a "Chekni ochish" link button (VM 943 1a-ilova 8: the receipt's link reaches the buyer). Never twice
 * for the same receipt, only to the order's customer, nothing for a customer without Telegram. The order also
 * records who issued its receipt (orders.fiscalized_by).
 */
class CustomerReceiptNoticeTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    private const SALE_URL = 'https://ofd.soliq.uz/epi?t=EP000000000025&r=121&c=20261124120000&s=800031554082';
    private const REFUND_URL = 'https://ofd.soliq.uz/epi?t=EP000000000025&r=123&c=20261124130000&s=900031555055';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => '123456:receipt-token',
            'services.telegram.webapp_url' => 'https://app.rizqim.test',
            'qr_payments.uzqr.receipt_hosts' => ['ofd.soliq.uz'],
        ]);
        Http::fake(['*' => Http::response(['ok' => true, 'result' => true])]);

        $this->setUpLifecycleStore();
        $this->customer->update(['telegram_id' => '5009']);
        $this->owner->update(['telegram_id' => '5001']);
    }

    /**
     * @return list<array<string, mixed>> the sendMessage requests the bot made
     */
    private function sent(): array
    {
        return Http::recorded(fn ($request) => str_ends_with($request->url(), '/sendMessage'))
            ->map(fn ($pair) => $pair[0]->data())
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function receiptMessages(): array
    {
        return array_values(array_filter($this->sent(), fn (array $message) => str_contains((string) $message['text'], 'chek')));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function receipt(Order $order, array $attributes = []): FiscalReceipt
    {
        return FiscalReceiptLock::outsideIssuerForTests(fn () => FiscalReceipt::forceCreate(array_merge([
            'store_id' => $order->store_id,
            'branch_id' => $order->branch_id,
            'type' => 'sale',
            'payment_form' => 'card',
            'status' => 'issued',
            'driver' => 'external',
            'provider' => 'payme',
            'idempotency_key' => 'k-' . Str::random(12),
            'subject_type' => 'order',
            'subject_id' => $order->id,
            'total' => '50000.00',
            'lines' => [],
            'payments' => [],
            'qr_url' => self::SALE_URL,
            'issued_at' => now(),
            'attempts' => 0,
        ], $attributes)));
    }

    public function test_a_recorded_online_receipt_reaches_the_customer_once_with_a_link_button(): void
    {
        $order = $this->checkout($this->customer, 2, 'cash', 'delivery');
        $order->forceFill(['payment_method' => 'payme', 'payment_status' => PaymentStatus::PAID])->save();
        $payment = Payment::where('order_id', $order->id)->firstOrFail();
        $payment->forceFill(['provider' => 'payme', 'status' => PaymentStatus::PAID, 'provider_transaction_id' => 'trx-1'])->save();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => true])]); // forget the order's own messages

        $recorder = app(OnlineFiscalRecorder::class);
        $data = ['receipt_id' => 121, 'status_code' => 0, 'terminal_id' => 'EP000000000025', 'fiscal_sign' => '800031554082', 'qr_code_url' => self::SALE_URL];
        $result = $recorder->record($order, $payment, 'payme', FiscalReceiptType::SALE, 'payme:trx-1:perform', $data);
        // Payme repeats SetFiscalData: the same receipt, no second message
        $recorder->record($order, $payment, 'payme', FiscalReceiptType::SALE, 'payme:trx-1:perform', $data);
        $this->assertNotNull($result);

        $messages = $this->receiptMessages();
        $this->assertCount(1, $messages);
        $this->assertSame('5009', (string) $messages[0]['chat_id']);
        $this->assertStringContainsString("#{$order->short_code}", $messages[0]['text']);
        $this->assertStringContainsString('fiskal cheki tayyor', $messages[0]['text']);
        $keyboard = json_decode($messages[0]['reply_markup'], true);
        $this->assertSame(['text' => '🧾 Chekni ochish', 'url' => self::SALE_URL], $keyboard['inline_keyboard'][0][0]);
        $this->assertSame('https://app.rizqim.test', $keyboard['inline_keyboard'][1][0]['web_app']['url']);

        $this->assertSame(FiscalizedBy::PAYMENT_PROVIDER->value, $order->fresh()->fiscalized_by);
        $this->assertNotNull(FiscalReceipt::where('uuid', $result->receiptUuid)->value('customer_notified_at'));

        // The job run again (a retried queue job) sends nothing either
        (new SendCustomerReceiptNotice(FiscalReceipt::where('uuid', $result->receiptUuid)->value('id')))->handle();
        $this->assertCount(1, $this->receiptMessages());
    }

    public function test_a_refund_receipt_is_its_own_message(): void
    {
        $order = $this->makeOrder(OrderStatus::REFUNDED, ['payment_method' => 'payme']);
        $sale = $this->receipt($order);
        $this->receipt($order, ['type' => 'refund', 'qr_url' => self::REFUND_URL, 'original_receipt_id' => $sale->id]);

        $messages = $this->receiptMessages();
        $this->assertCount(2, $messages);
        $this->assertStringContainsString('qaytarish cheki tayyor', $messages[1]['text']);
        $this->assertSame(self::REFUND_URL, json_decode($messages[1]['reply_markup'], true)['inline_keyboard'][0][0]['url']);
    }

    public function test_a_pending_pos_receipt_is_announced_when_it_is_issued(): void
    {
        $order = $this->makeOrder(OrderStatus::DELIVERED);
        $bill = OrderBill::forceCreate(['uuid' => (string) Str::uuid(), 'store_id' => $order->store_id, 'branch_id' => $order->branch_id, 'order_id' => $order->id, 'number' => 1, 'status' => 'paid', 'basis' => 'b', 'total' => '50000.00']);
        $receipt = $this->receipt($order, ['subject_type' => OrderBill::FISCAL_SUBJECT, 'subject_id' => $bill->id, 'status' => 'pending', 'driver' => 'sandbox', 'provider' => null, 'qr_url' => null, 'issued_at' => null, 'payment_form' => 'cash']);

        $this->assertSame([], $this->receiptMessages());
        $this->assertNull($order->fresh()->fiscalized_by);

        // The provider confirms it later (FiscalService settles the row the same way)
        $row = FiscalReceipt::whereKey($receipt->id)->firstOrFail();
        $row->forceFill(['status' => 'issued', 'qr_url' => self::SALE_URL, 'issued_at' => now()])->save();
        // Something else about it changes later: no second message
        $row->forceFill(['attempts' => 3])->save();

        $this->assertCount(1, $this->receiptMessages());
        $this->assertSame(FiscalizedBy::VENUE_POS->value, $order->fresh()->fiscalized_by);
    }

    public function test_nothing_is_sent_without_a_telegram_chat_a_link_or_a_customer(): void
    {
        // No Telegram chat
        $this->customer->update(['telegram_id' => null]);
        $order = $this->makeOrder(OrderStatus::DELIVERED, ['payment_method' => 'payme']);
        $this->receipt($order);

        // A link on another host
        $this->customer->update(['telegram_id' => '5009']);
        $other = $this->makeOrder(OrderStatus::DELIVERED, ['payment_method' => 'payme']);
        $this->receipt($other, ['qr_url' => 'https://evil.example/receipt']);

        // A non-fiscal (sandbox) receipt
        $sandbox = $this->makeOrder(OrderStatus::DELIVERED, ['payment_method' => 'payme']);
        $this->receipt($sandbox, ['non_fiscal' => true]);

        // A till order: its user is the cashier who rang it up (here the owner, who has Telegram), not a customer
        $walkIn = $this->makeOrder(OrderStatus::DELIVERED, ['user_id' => $this->owner->id, 'channel' => 'pos_takeaway']);
        $this->receipt($walkIn);

        $this->assertSame([], $this->receiptMessages());
        $this->assertSame([], $this->sent());
    }

    public function test_only_the_orders_own_customer_is_told(): void
    {
        $stranger = $this->makeCustomer(0, ['telegram_id' => '6006']);
        $order = $this->makeOrder(OrderStatus::DELIVERED, ['payment_method' => 'payme', 'user_id' => $stranger->id]);

        // A receipt of another store naming this order's id is not this order's
        $foreignStore = $this->makeStore('Foreign');
        FiscalReceiptLock::outsideIssuerForTests(fn () => FiscalReceipt::forceCreate([
            'store_id' => $foreignStore->id, 'type' => 'sale', 'payment_form' => 'card', 'status' => 'issued', 'driver' => 'external',
            'provider' => 'click', 'idempotency_key' => 'foreign-1', 'subject_type' => 'order', 'subject_id' => $order->id,
            'total' => '1.00', 'lines' => [], 'payments' => [], 'qr_url' => self::SALE_URL, 'issued_at' => now(), 'attempts' => 0,
        ]));
        $this->assertSame([], $this->receiptMessages());

        $this->receipt($order);
        $chats = array_map(fn (array $message) => (string) $message['chat_id'], $this->receiptMessages());
        $this->assertSame(['6006'], $chats);
    }

    public function test_a_customer_who_switched_order_messages_off_gets_none(): void
    {
        app(\App\Services\Notifications\NotificationPreferenceService::class)->update($this->customer, ['order_updates' => ['telegram' => false]]);
        $order = $this->makeOrder(OrderStatus::DELIVERED, ['payment_method' => 'payme']);
        $this->receipt($order);

        $this->assertSame([], $this->receiptMessages());
    }

    public function test_a_registered_refund_receipt_moves_the_customers_order_list_token(): void
    {
        $order = $this->makeOrder(OrderStatus::REFUNDED, ['payment_method' => 'payme', 'fiscalized_by' => FiscalizedBy::PAYMENT_PROVIDER->value]);
        $sale = $this->receipt($order);
        $revisions = app(\App\Domain\Realtime\ChangeRevision::class);
        $before = $revisions->user($this->customer->id);

        // A refund receipt writes nothing else the customer's list is gated on (the order row stays as it is)
        $this->receipt($order, ['type' => 'refund', 'qr_url' => self::REFUND_URL, 'original_receipt_id' => $sale->id]);

        $this->assertNotSame($before, $revisions->user($this->customer->id));
    }
}
