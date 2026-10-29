<?php

namespace Tests\Feature\AdminCustomers;

use App\Enums\OrderStatus;
use App\Enums\PlatformRole;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\OrderRefundRequest;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P4-31: Mijozlar va yordam → Foydalanuvchilar and the user card: who may read, the role and status filters, search
 * by name and phone under the PII rule, RIZQIM team accounts kept out, the card's parts by permission.
 */
class AdminUserDirectoryTest extends TestCase
{
    use RefreshDatabase;
    use AdminCustomersFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLifecycleStore();
    }

    public function test_who_may_read_the_list(): void
    {
        foreach ([PlatformRole::SUPPORT, PlatformRole::VIEWER, PlatformRole::SUPERADMIN] as $role) {
            $this->adminJson('GET', '/users', $this->signIn($this->makeMember($role)))->assertOk();
        }
        foreach ([PlatformRole::FINANCE, PlatformRole::SALES, PlatformRole::OPERATOR] as $role) {
            $this->adminJson('GET', '/users', $this->signIn($this->makeMember($role)))->assertForbidden();
        }
        $this->adminJson('GET', '/users')->assertUnauthorized();
        // A store owner's own token is no admin session
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/admin/users')->assertUnauthorized();
    }

    public function test_the_list_filters_by_role_and_status_and_leaves_the_team_out(): void
    {
        $token = $this->signIn($support = $this->makeMember(PlatformRole::SUPPORT));
        $blocked = $this->makeCustomer(0, ['name' => 'Bloklangan Mijoz']);
        $blocked->forceFill(['blocked_at' => now(), 'blocked_by' => $support->id, 'blocked_reason' => 'Firibgarlik'])->save();
        $this->orderOf($this->customer);
        $this->orderOf($this->customer);

        $all = $this->adminJson('GET', '/users', $token)->assertOk();
        $ids = collect($all->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains($this->customer->id));
        $this->assertTrue($ids->contains($this->owner->id));
        $this->assertFalse($ids->contains($support->id), 'team members are not users here');
        $this->assertSame('Mijoz', $all->json('data.roles.customer'));
        $row = collect($all->json('data.data'))->firstWhere('id', $this->customer->id);
        $this->assertSame(2, $row['orders_count']);
        $this->assertSame('Mijoz', $row['role_label']);
        $this->assertStringContainsString('***', (string) $row['phone']);

        $couriers = collect($this->adminJson('GET', '/users?role=courier', $token)->json('data.data'))->pluck('id')->all();
        $this->assertSame([$this->courier->id], $couriers);
        $staff = collect($this->adminJson('GET', '/users?role=staff', $token)->json('data.data'))->pluck('id')->sort()->values()->all();
        $this->assertSame([$this->owner->id, $this->manager->id], $staff);
        $this->assertSame([$blocked->id], collect($this->adminJson('GET', '/users?status=blocked', $token)->json('data.data'))->pluck('id')->all());
        $this->adminJson('GET', '/users?role=super_admin', $token)->assertStatus(422)->assertJsonValidationErrors('role');
    }

    public function test_the_list_pages_by_cursor(): void
    {
        $token = $this->signIn($this->makeMember(PlatformRole::SUPPORT));
        for ($i = 0; $i < 4; $i++) {
            $this->makeCustomer();
        }

        $first = $this->adminJson('GET', '/users?role=customer&per_page=3', $token)->assertOk();
        $this->assertCount(3, $first->json('data.data'));
        $cursor = $first->json('data.next_cursor');
        $this->assertNotNull($cursor);
        $second = $this->adminJson('GET', '/users?role=customer&per_page=3&cursor=' . urlencode($cursor), $token)->assertOk();
        $this->assertCount(2, $second->json('data.data'));
        $this->assertNull($second->json('data.next_cursor'));
        $this->assertEmpty(array_intersect(collect($first->json('data.data'))->pluck('id')->all(), collect($second->json('data.data'))->pluck('id')->all()));
    }

    public function test_search_by_phone_needs_the_whole_number_without_pii_view(): void
    {
        $customer = $this->makeCustomer(0, ['name' => 'Olim Egamov', 'phone' => '+998901234567']);
        $support = $this->signIn($this->makeMember(PlatformRole::SUPPORT));

        $this->assertSame([], $this->adminJson('GET', '/users?q=4567', $support)->json('data.data'));
        $this->assertSame([], $this->adminJson('GET', '/users?q=90123', $support)->json('data.data'));
        $found = $this->adminJson('GET', '/users?q=' . urlencode('90 123 45 67'), $support)->json('data.data');
        $this->assertSame([$customer->id], collect($found)->pluck('id')->all());
        $this->assertSame('+998 90 *** ** 67', $found[0]['phone']);
        $this->assertSame([$customer->id], collect($this->adminJson('GET', '/users?q=egam', $support)->json('data.data'))->pluck('id')->all());
        $this->assertSame(0, AuditLog::where('action', 'admin.pii.revealed')->count());

        // With pii.view any 4+ digits match, and the numbers shown are an audited reveal
        $superadmin = $this->signIn($this->makeMember());
        $found = $this->adminJson('GET', '/users?q=4567', $superadmin)->json('data.data');
        $this->assertSame([$customer->id], collect($found)->pluck('id')->all());
        $this->assertSame('+998 90 123 45 67', $found[0]['phone']);
        $this->assertSame(1, AuditLog::where('action', 'admin.pii.revealed')->count());
    }

    public function test_the_card_shows_the_customer_by_permission(): void
    {
        $customer = $this->makeCustomer(12000, ['name' => 'Karim', 'telegram_id' => '5551', 'telegram_username' => 'karim']);
        $delivered = $this->orderOf($customer, OrderStatus::DELIVERED, ['total_price' => 80000]);
        $this->orderOf($customer, OrderStatus::DELIVERED, ['total_price' => 40000]);
        $cancelled = $this->orderOf($customer, OrderStatus::CANCELED, ['payment_method' => 'keshbek', 'payment_status' => 'refunded']);
        Payment::create(['order_id' => $cancelled->id, 'provider' => 'keshbek', 'amount' => '30000.00', 'status' => 'refunded', 'payload' => ['refunded_to_balance' => 30000, 'refunded_at' => now()->toIso8601String()]]);
        OrderRefundRequest::create(['order_id' => $delivered->id, 'store_id' => $this->store->id, 'amount' => '20000.00', 'status' => 'pending', 'reason' => 'Bir taom kelmadi']);
        $this->complaintOf($customer, $delivered);
        $customer->createToken('mobile-api');

        $support = $this->signIn($this->makeMember(PlatformRole::SUPPORT));
        $card = $this->adminJson('GET', "/users/{$customer->id}", $support)->assertOk();
        $card->assertJsonPath('data.profile.name', 'Karim')
            ->assertJsonPath('data.profile.role', 'customer')
            ->assertJsonPath('data.profile.phone', fn ($phone) => str_contains((string) $phone, '***'))
            ->assertJsonPath('data.profile.telegram.linked', true)
            ->assertJsonPath('data.profile.telegram.username', 'karim')
            ->assertJsonPath('data.block.blocked', false)
            ->assertJsonPath('data.stats.orders', 3)
            ->assertJsonPath('data.stats.delivered', 2)
            ->assertJsonPath('data.stats.cancelled', 1)
            ->assertJsonPath('data.stats.delivered_sum', '120000.00')
            ->assertJsonPath('data.stats.avg_check', '60000.00')
            ->assertJsonPath('data.stats.segment.key', 'returning')
            ->assertJsonPath('data.orders.0.href', fn ($href) => str_starts_with((string) $href, '/orders/'))
            ->assertJsonPath('data.refunds.0.amount', '20000.00')
            ->assertJsonPath('data.refunds.0.status_label', 'Kutilmoqda')
            ->assertJsonPath('data.keshbek.balance', '12000.00')
            ->assertJsonPath('data.complaints.0.type_label', 'Sifat muammosi')
            ->assertJsonPath('data.sessions.active_tokens', 1)
            ->assertJsonPath('data.sessions.tokens.0.kind', 'mini_app')
            ->assertJsonPath('data.membership', null)
            ->assertJsonPath('data.audit', null)
            ->assertJsonPath('data.actions.block', true)
            ->assertJsonPath('data.actions.close_sessions', true);
        $kinds = collect($card->json('data.keshbek.entries'))->pluck('amount', 'kind')->all();
        $this->assertSame(['spent' => '-30000.00', 'refunded' => '30000.00'], $kinds);

        // The viewer reads every part (all view permissions), cannot act
        $viewer = $this->signIn($this->makeMember(PlatformRole::VIEWER));
        $this->adminJson('GET', "/users/{$customer->id}", $viewer)->assertOk()
            ->assertJsonPath('data.actions.block', false)
            ->assertJsonPath('data.actions.close_sessions', false)
            ->assertJsonPath('data.complaints.0.type_label', 'Sifat muammosi')
            ->assertJsonPath('data.audit', []);

        // The superadmin also sees the audit entries about the account and the full phone (an audited reveal)
        $superadmin = $this->signIn($this->makeMember());
        $this->adminJson('GET', "/users/{$customer->id}", $superadmin)->assertOk()
            ->assertJsonPath('data.profile.phone', fn ($phone) => !str_contains((string) $phone, '***'))
            ->assertJsonPath('data.audit', []);
        $this->assertSame(1, AuditLog::where('action', 'admin.pii.revealed')->count());
    }

    public function test_the_card_of_staff_shows_the_store_and_linked_accounts(): void
    {
        $twin = $this->makeCustomer(0, ['phone' => $this->courier->phone, 'name' => 'Kuryer mijoz sifatida']);
        $token = $this->signIn($this->makeMember(PlatformRole::SUPPORT));

        $this->adminJson('GET', "/users/{$this->courier->id}", $token)->assertOk()
            ->assertJsonPath('data.membership.store.id', $this->store->id)
            ->assertJsonPath('data.membership.branch.id', $this->branch->id)
            ->assertJsonPath('data.membership.role_label', 'Kuryer')
            ->assertJsonPath('data.profile.courier_type.value', 'own')
            ->assertJsonPath('data.linked_accounts.0.id', $twin->id)
            ->assertJsonPath('data.linked_accounts.0.same', ['phone']);
    }

    public function test_a_team_account_is_not_found_here(): void
    {
        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $legacy = User::create(['name' => 'Eski superadmin', 'role' => UserRole::SUPER_ADMIN->value]);
        $token = $this->signIn($this->makeMember());

        $this->adminJson('GET', "/users/{$operator->id}", $token)->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->adminJson('GET', "/users/{$legacy->id}", $token)->assertNotFound();
        $this->adminJson('GET', '/users/999999', $token)->assertNotFound();
    }

    public function test_the_keshbek_history_reads_partial_refunds_and_referral_rewards(): void
    {
        $customer = $this->makeCustomer(5000);
        $order = $this->orderOf($customer, OrderStatus::DELIVERED, ['payment_method' => 'keshbek', 'payment_status' => 'paid']);
        Payment::create(['order_id' => $order->id, 'provider' => 'keshbek', 'amount' => '40000.00', 'status' => 'refunded', 'payload' => [
            'refunded_to_balance_parts' => [['refund_request_id' => 1, 'amount' => '10000.00', 'refunded_at' => now()->subHour()->toIso8601String()], ['refund_request_id' => 2, 'amount' => '30000.00', 'refunded_at' => now()->toIso8601String()]],
            // A request that returned the last part also writes the whole amount: counted by its parts already
            'refunded_to_balance' => 40000,
            'refunded_at' => now()->toIso8601String(),
        ]]);
        $friend = $this->makeCustomer(0, ['name' => 'Do\'st']);
        DB::table('users')->where('id', $friend->id)->update(['referred_by' => $customer->id]);
        $friendOrder = $this->orderOf($friend);
        DB::table('store_transactions')->insert([
            'store_id' => $this->store->id, 'type' => 'commission', 'amount' => '5000.00', 'balance_before' => '0.00', 'balance_after' => '5000.00',
            'reference_id' => $friendOrder->uuid, 'order_id' => $friendOrder->id, 'notes' => "Referral cashback reward cost for referred user #{$friend->id}",
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $entries = collect($this->adminJson('GET', "/users/{$customer->id}", $this->signIn($this->makeMember(PlatformRole::SUPPORT)))->assertOk()->json('data.keshbek.entries'));
        $this->assertSame(['-40000.00', '5000.00', '10000.00', '30000.00'], $entries->pluck('amount')->sortBy(fn ($amount) => (float) $amount)->values()->all());
        $this->assertSame(['referral' => 1, 'refunded' => 2, 'spent' => 1], $entries->countBy('kind')->sortKeys()->all());
        $this->assertSame(1, $this->adminJson('GET', "/users/{$customer->id}", $this->signIn($this->makeMember(PlatformRole::SUPPORT)))->json('data.profile.referrals'));
    }
}
