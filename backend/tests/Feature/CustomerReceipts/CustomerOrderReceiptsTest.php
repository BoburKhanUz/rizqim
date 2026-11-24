<?php

namespace Tests\Feature\CustomerReceipts;

use App\Domain\Fiscal\FiscalReceiptLock;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\FiscalReceipt;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * Wave P (receipts): a customer's GET /orders carries `seller` and `receipts` on each of their orders (ZRU-792 art. 23:
 * the electronic receipt reaches the buyer, the order history being their "shaxsiy kabinet"). A receipt's link is
 * given only when it is https on the tax committee's host; pending and failed receipts show as such, never with
 * the internal reason. A customer sees only their own orders' receipts.
 */
class CustomerOrderReceiptsTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    private const SALE_URL = 'https://ofd.soliq.uz/epi?t=EP000000000025&r=121&c=20261124120000&s=800031554082';
    private const REFUND_URL = 'https://ofd.soliq.uz/epi?t=EP000000000025&r=123&c=20261124130000&s=900031555055';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        config(['qr_payments.uzqr.receipt_hosts' => ['ofd.soliq.uz']]);
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
            'fiscal_sign' => '800031554082',
            'issued_at' => now(),
            'attempts' => 0,
        ], $attributes)));
    }

    private function bill(Order $order): OrderBill
    {
        return OrderBill::forceCreate([
            'uuid' => (string) Str::uuid(),
            'store_id' => $order->store_id,
            'branch_id' => $order->branch_id,
            'order_id' => $order->id,
            'number' => 1,
            'status' => 'paid',
            'basis' => 'test',
            'total' => '50000.00',
        ]);
    }

    private function list(User $user, array $query = []): TestResponse
    {
        return $this->actingAs($user, 'sanctum')->getJson('/api/v1/orders' . ($query ? '?' . http_build_query($query) : ''))->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function orderIn(TestResponse $response, Order $order): array
    {
        $row = collect($response->json('data'))->firstWhere('uuid', $order->uuid);
        $this->assertIsArray($row, 'order missing from the list');

        return $row;
    }

    public function test_sale_and_refund_receipts_come_with_their_links_in_every_scope(): void
    {
        $order = $this->makeOrder(OrderStatus::REFUNDED, ['payment_method' => 'payme']);
        $sale = $this->receipt($order, ['issued_at' => now()->subHour()]);
        $refund = $this->receipt($order, ['type' => 'refund', 'qr_url' => self::REFUND_URL, 'original_receipt_id' => $sale->id]);

        $expected = [
            ['uuid' => $sale->uuid, 'type' => 'sale', 'status' => 'ready', 'provider' => 'payme', 'url' => self::SALE_URL, 'issued_at' => $sale->issued_at->toIso8601String()],
            ['uuid' => $refund->uuid, 'type' => 'refund', 'status' => 'ready', 'provider' => 'payme', 'url' => self::REFUND_URL, 'issued_at' => $refund->issued_at->toIso8601String()],
        ];

        foreach ([[], ['scope' => 'history']] as $query) {
            $this->assertSame($expected, $this->orderIn($this->list($this->customer, $query), $order)['receipts'], json_encode($query));
        }

        $active = $this->makeOrder(OrderStatus::ACCEPTED, ['payment_method' => 'payme']);
        $this->receipt($active);
        $this->assertSame('ready', $this->orderIn($this->list($this->customer, ['scope' => 'active']), $active)['receipts'][0]['status']);
    }

    public function test_only_an_https_link_on_the_tax_host_is_given(): void
    {
        $bad = [
            'http://ofd.soliq.uz/epi?r=1',
            'https://ofd.soliq.uz:8443/epi?r=1',
            'https://user@ofd.soliq.uz/epi?r=1',
            'https://ofd.soliq.uz.evil.example/epi?r=1',
            'https://evil.example/?u=https://ofd.soliq.uz',
            'javascript:alert(1)',
            "https://ofd.soliq.uz/epi?r=1\nX",
        ];

        foreach ($bad as $url) {
            $order = $this->makeOrder(OrderStatus::DELIVERED, ['payment_method' => 'payme']);
            $this->receipt($order, ['qr_url' => $url]);

            $receipts = $this->orderIn($this->list($this->customer), $order)['receipts'];
            $this->assertCount(1, $receipts, $url);
            $this->assertNull($receipts[0]['url'], $url);
            $this->assertSame('unavailable', $receipts[0]['status'], $url);
        }
    }

    public function test_pending_and_failed_receipts_show_their_state_without_the_reason(): void
    {
        $pendingOrder = $this->makeOrder(OrderStatus::DELIVERED);
        $this->receipt($this->bill($pendingOrder)->order, [
            'subject_type' => OrderBill::FISCAL_SUBJECT, 'subject_id' => $pendingOrder->bills()->value('id'),
            'status' => 'pending', 'driver' => 'sandbox', 'provider' => null, 'qr_url' => null, 'issued_at' => null, 'payment_form' => 'cash',
        ]);

        $failedOrder = $this->makeOrder(OrderStatus::DELIVERED);
        $bill = $this->bill($failedOrder);
        $this->receipt($failedOrder, [
            'subject_type' => OrderBill::FISCAL_SUBJECT, 'subject_id' => $bill->id,
            'status' => 'failed', 'driver' => 'sandbox', 'provider' => null, 'qr_url' => null, 'issued_at' => null,
            'error' => "Soliq serveri rad etdi: MXIK noto'g'ri (secret-detail)",
        ]);

        $response = $this->list($this->customer);

        $this->assertSame([['uuid' => FiscalReceipt::where('subject_id', $pendingOrder->bills()->value('id'))->value('uuid'), 'type' => 'sale', 'status' => 'pending', 'provider' => 'pos', 'url' => null, 'issued_at' => null]], $this->orderIn($response, $pendingOrder)['receipts']);
        $failed = $this->orderIn($response, $failedOrder)['receipts'];
        $this->assertSame('unavailable', $failed[0]['status']);
        $this->assertSame('pos', $failed[0]['provider']);
        $this->assertStringNotContainsString('secret-detail', $response->getContent());
        $this->assertStringNotContainsString('MXIK', $response->getContent());
    }

    public function test_a_pos_refund_receipt_of_the_bill_is_the_orders_refund(): void
    {
        $order = $this->makeOrder(OrderStatus::REFUNDED);
        $bill = $this->bill($order);
        $sale = $this->receipt($order, ['subject_type' => OrderBill::FISCAL_SUBJECT, 'subject_id' => $bill->id, 'driver' => 'sandbox', 'provider' => null]);
        $refund = $this->receipt($order, ['type' => 'refund', 'subject_type' => 'pos_refund', 'subject_id' => 987654, 'driver' => 'sandbox', 'provider' => null, 'qr_url' => self::REFUND_URL, 'original_receipt_id' => $sale->id]);

        $receipts = $this->orderIn($this->list($this->customer), $order)['receipts'];

        $this->assertSame([[$sale->uuid, 'sale', 'ready'], [$refund->uuid, 'refund', 'ready']], array_map(fn (array $r) => [$r['uuid'], $r['type'], $r['status']], $receipts));
    }

    public function test_an_online_payment_whose_receipt_is_waiting_or_blocked_says_so(): void
    {
        $waiting = $this->makeOrder(OrderStatus::ACCEPTED, ['payment_method' => 'click', 'payment_status' => PaymentStatus::PAID->value]);
        Payment::create(['order_id' => $waiting->id, 'store_id' => $waiting->store_id, 'provider' => 'click', 'amount' => 50000, 'status' => PaymentStatus::PAID->value, 'payload' => ['fiscal' => ['provider' => 'click', 'status' => 'pending', 'problems' => []]]]);

        $blocked = $this->makeOrder(OrderStatus::ACCEPTED, ['payment_method' => 'payme', 'payment_status' => PaymentStatus::PAID->value]);
        Payment::create(['order_id' => $blocked->id, 'store_id' => $blocked->store_id, 'provider' => 'payme', 'amount' => 50000, 'status' => PaymentStatus::PAID->value, 'payload' => ['fiscal' => ['provider' => 'payme', 'status' => 'blocked', 'problems' => ['store_tin_missing']]]]);

        // Not paid yet: nothing to say about a receipt
        $unpaid = $this->makeOrder(OrderStatus::NEW, ['payment_method' => 'payme']);
        Payment::create(['order_id' => $unpaid->id, 'store_id' => $unpaid->store_id, 'provider' => 'payme', 'amount' => 50000, 'status' => PaymentStatus::PENDING->value, 'payload' => ['fiscal' => ['provider' => 'payme', 'status' => 'blocked', 'problems' => ['store_tin_missing']]]]);

        // A Paylov refund whose refund receipt failed
        $refunded = $this->makeOrder(OrderStatus::REFUNDED, ['payment_method' => 'paylov', 'payment_status' => PaymentStatus::REFUNDED->value]);
        $this->receipt($refunded, ['provider' => 'paylov']);
        Payment::create(['order_id' => $refunded->id, 'store_id' => $refunded->store_id, 'provider' => 'paylov', 'amount' => 50000, 'status' => PaymentStatus::REFUNDED->value, 'payload' => ['fiscal' => ['provider' => 'paylov', 'status' => 'recorded', 'refund_status' => 'failed', 'refund_problems' => ['paylov_refund_x']]]]);

        $response = $this->list($this->customer);

        $this->assertSame([['uuid' => null, 'type' => 'sale', 'status' => 'pending', 'provider' => 'click', 'url' => null, 'issued_at' => null]], $this->orderIn($response, $waiting)['receipts']);
        $this->assertSame([['uuid' => null, 'type' => 'sale', 'status' => 'unavailable', 'provider' => 'payme', 'url' => null, 'issued_at' => null]], $this->orderIn($response, $blocked)['receipts']);
        $this->assertSame([], $this->orderIn($response, $unpaid)['receipts']);
        $this->assertSame([['sale', 'ready'], ['refund', 'unavailable']], array_map(fn (array $r) => [$r['type'], $r['status']], $this->orderIn($response, $refunded)['receipts']));
        $this->assertStringNotContainsString('store_tin_missing', $response->getContent());
        $this->assertStringNotContainsString('paylov_refund_x', $response->getContent());
    }

    public function test_non_fiscal_advance_and_credit_receipts_are_left_out(): void
    {
        $order = $this->makeOrder(OrderStatus::DELIVERED, ['payment_method' => 'payme']);
        $this->receipt($order, ['non_fiscal' => true]);
        $this->receipt($order, ['type' => 'advance']);
        $this->receipt($order, ['type' => 'credit']);

        $this->assertSame([], $this->orderIn($this->list($this->customer), $order)['receipts']);
    }

    public function test_a_customer_sees_only_their_own_orders_receipts(): void
    {
        $mine = $this->makeOrder(OrderStatus::DELIVERED, ['payment_method' => 'payme']);
        $this->receipt($mine);

        $stranger = $this->makeCustomer();
        $theirs = $this->makeOrder(OrderStatus::DELIVERED, ['payment_method' => 'payme', 'user_id' => $stranger->id]);
        $theirReceipt = $this->receipt($theirs, ['qr_url' => self::REFUND_URL]);

        // Another store's receipt that names my order's id as its subject (a foreign store's own "order" numbering)
        $foreignStore = $this->makeStore('Foreign');
        $foreign = FiscalReceiptLock::outsideIssuerForTests(fn () => FiscalReceipt::forceCreate([
            'store_id' => $foreignStore->id, 'type' => 'sale', 'payment_form' => 'card', 'status' => 'issued', 'driver' => 'external',
            'provider' => 'click', 'idempotency_key' => 'foreign-1', 'subject_type' => 'order', 'subject_id' => $mine->id,
            'total' => '1.00', 'lines' => [], 'payments' => [], 'qr_url' => 'https://ofd.soliq.uz/epi?r=999', 'issued_at' => now(), 'attempts' => 0,
        ]));

        $response = $this->list($this->customer);

        $this->assertSame([self::SALE_URL], array_column($this->orderIn($response, $mine)['receipts'], 'url'));
        $this->assertNull(collect($response->json('data'))->firstWhere('uuid', $theirs->uuid));
        $this->assertStringNotContainsString($theirReceipt->uuid, $response->getContent());
        $this->assertStringNotContainsString($foreign->uuid, $response->getContent());
        $this->assertStringNotContainsString('r=999', $response->getContent());

        // The stranger sees theirs only
        $this->assertSame([self::REFUND_URL], array_column($this->orderIn($this->list($stranger), $theirs)['receipts'], 'url'));
    }

    public function test_staff_lists_are_unchanged(): void
    {
        $order = $this->makeOrder(OrderStatus::DELIVERED, ['payment_method' => 'payme']);
        $this->receipt($order);

        $row = $this->orderIn($this->list($this->owner), $order);

        $this->assertArrayNotHasKey('receipts', $row);
        $this->assertArrayNotHasKey('seller', $row);
        $this->assertArrayNotHasKey('seller_snapshot', $row);
    }

    public function test_receipts_cost_a_fixed_number_of_queries(): void
    {
        $make = function (int $count) {
            for ($i = 0; $i < $count; $i++) {
                $order = $this->makeOrder(OrderStatus::DELIVERED, ['payment_method' => 'payme']);
                $sale = $this->receipt($order);
                $this->receipt($order, ['type' => 'refund', 'original_receipt_id' => $sale->id]);
                $bill = $this->bill($this->makeOrder(OrderStatus::DELIVERED));
                $posSale = $this->receipt($bill->order, ['subject_type' => OrderBill::FISCAL_SUBJECT, 'subject_id' => $bill->id, 'driver' => 'sandbox']);
                $this->receipt($bill->order, ['type' => 'refund', 'subject_type' => 'pos_refund', 'subject_id' => 1000 + $i, 'driver' => 'sandbox', 'original_receipt_id' => $posSale->id]);
            }
        };
        $count = function () {
            $this->list($this->customer, ['scope' => 'history']); // warm-up
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->list($this->customer, ['scope' => 'history']);
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $make(2);
        $few = $count();
        $make(8);

        $this->assertSame($few, $count());
    }
}
