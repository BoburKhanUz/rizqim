<?php

namespace Tests\Feature\AdminCustomers;

use App\Enums\OrderStatus;
use App\Enums\PlatformRole;
use App\Models\OrderRefundRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P4-31: Shubhali holatlar: many cancellations by one customer in 7 days, one promo code from accounts sharing a
 * phone or Telegram, refunds well above the average — each row explained in words and linked to the user card.
 */
class AdminSuspiciousCasesTest extends TestCase
{
    use RefreshDatabase;
    use AdminCustomersFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLifecycleStore();
    }

    public function test_many_cancellations_in_a_week(): void
    {
        $serial = $this->makeCustomer(0, ['name' => 'Ko\'p bekor qiluvchi']);
        $this->orderOf($serial, OrderStatus::CANCELED, ['cancelled_by' => $serial->id, 'cancel_reason' => 'customer_request']);
        $this->orderOf($serial, OrderStatus::CANCELED, ['cancelled_by' => $serial->id, 'cancel_reason' => 'customer_request']);
        $this->orderOf($serial, OrderStatus::CANCELED, ['cancelled_by' => $this->owner->id, 'cancel_reason' => 'no_show']);
        // Older than 7 days and the store's own reasons do not count
        $this->orderOf($serial, OrderStatus::CANCELED, ['cancelled_by' => $serial->id, 'cancel_reason' => 'customer_request'], 60 * 24 * 8);
        $calm = $this->makeCustomer();
        $this->orderOf($calm, OrderStatus::CANCELED, ['cancelled_by' => $this->owner->id, 'cancel_reason' => 'out_of_stock']);
        $this->orderOf($calm, OrderStatus::CANCELED, ['cancelled_by' => $this->owner->id, 'cancel_reason' => 'out_of_stock']);
        $this->orderOf($calm, OrderStatus::CANCELED, ['cancelled_by' => $this->owner->id, 'cancel_reason' => 'store_closed']);

        $group = $this->group('cancellations');
        $this->assertCount(1, $group['rows']);
        $row = $group['rows'][0];
        $this->assertSame($serial->id, $row['user']['id']);
        $this->assertSame(3, $row['metric']);
        $this->assertSame("7 kunda 3 ta buyurtma bekor qilindi: 2 tasini o'zi bekor qildi, 1 tasini olib ketmadi.", $row['reason']);
        $this->assertSame('/users/' . $serial->id, $row['href']);
    }

    public function test_one_promo_code_from_accounts_sharing_a_phone_or_telegram(): void
    {
        $promo = DB::table('promo_codes')->insertGetId(['code' => 'BIRINCHI', 'type' => 'fixed', 'value' => 10000, 'created_at' => now(), 'updated_at' => now()]);
        $first = $this->makeCustomer(0, ['phone' => '+998935550011', 'name' => 'Birinchi hisob']);
        $second = $this->makeCustomer(0, ['phone' => '998935550011', 'name' => 'Ikkinchi hisob']);
        $third = $this->makeCustomer(0, ['telegram_id' => '9001', 'name' => 'Uchinchi']);
        $fourth = $this->makeCustomer(0, ['telegram_id' => '9001', 'name' => "To'rtinchi"]);
        $honest = $this->makeCustomer();
        foreach ([$first, $second, $second, $third, $fourth, $honest] as $customer) {
            $this->orderOf($customer, OrderStatus::DELIVERED, ['promo_code_id' => $promo]);
        }

        $rows = collect($this->group('promo_sharing')['rows']);
        $this->assertCount(2, $rows);
        $byPhone = $rows->firstWhere('shared_by', 'phone');
        $this->assertSame([$first->id, $second->id], collect($byPhone['accounts'])->pluck('id')->all());
        $this->assertSame('BIRINCHI kodi 30 kunda bir xil telefon raqamli 2 ta hisobdan 3 marta ishlatildi.', $byPhone['reason']);
        $byTelegram = $rows->firstWhere('shared_by', 'telegram');
        $this->assertSame([$third->id, $fourth->id], collect($byTelegram['accounts'])->pluck('id')->all());
        $this->assertStringNotContainsString('+998', json_encode($rows->all()), 'no phone number in the rows');
    }

    public function test_refunds_well_above_the_average(): void
    {
        $greedy = $this->makeCustomer(0, ['name' => 'Ko\'p qaytaruvchi']);
        foreach (['150000.00', '150000.00'] as $amount) {
            $order = $this->orderOf($greedy);
            OrderRefundRequest::create(['order_id' => $order->id, 'store_id' => $this->store->id, 'amount' => $amount, 'status' => 'approved', 'reason' => 'Kelmadi']);
        }
        for ($i = 0; $i < 8; $i++) {
            $order = $this->orderOf($this->makeCustomer());
            OrderRefundRequest::create(['order_id' => $order->id, 'store_id' => $this->store->id, 'amount' => '20000.00', 'status' => $i === 0 ? 'rejected' : 'approved', 'reason' => 'Sovuq']);
        }

        $group = $this->group('refunds');
        $this->assertCount(1, $group['rows']);
        $row = $group['rows'][0];
        $this->assertSame($greedy->id, $row['user']['id']);
        $this->assertSame('300000.00', $row['metric']);
        // Average of the 9 pending / approved requests: (300 000 + 7 × 20 000) / 9 = 48 888.89
        $this->assertSame("30 kunda 2 ta qaytarish, jami 300 000 so'm — o'rtacha qaytarishdan (48 889 so'm) 6.1 baravar ko'p.", $row['reason']);
    }

    public function test_nothing_suspicious_is_three_empty_groups(): void
    {
        $data = $this->adminJson('GET', '/support/suspicious', $this->signIn($this->makeMember(PlatformRole::SUPPORT)))->assertOk()->json('data');
        $this->assertSame(['cancellations', 'promo_sharing', 'refunds'], collect($data['groups'])->pluck('kind')->all());
        $this->assertSame([[], [], []], collect($data['groups'])->pluck('rows')->all());
        $this->assertNotEmpty($data['groups'][0]['description']);
    }

    /**
     * @return array<string, mixed>
     */
    private function group(string $kind): array
    {
        $data = $this->adminJson('GET', '/support/suspicious', $this->signIn($this->makeMember(PlatformRole::SUPPORT)))->assertOk()->json('data.groups');

        return collect($data)->firstWhere('kind', $kind);
    }
}
