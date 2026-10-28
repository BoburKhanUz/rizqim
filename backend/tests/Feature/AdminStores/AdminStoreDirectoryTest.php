<?php

namespace Tests\Feature\AdminStores;

use App\Enums\PlatformRole;
use App\Enums\StoreStatus;
use App\Models\AuditLog;
use App\Models\StoreHealthScore;
use App\Models\StoreStatusLog;
use App\Models\TariffPlan;
use App\Services\Finance\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-22: the Ovqatxonalar list (filters, health counts, no phones), the 360° card (sections, PII), the store and
 * region picker (also for Buyurtmalar).
 */
class AdminStoreDirectoryTest extends TestCase
{
    use RefreshDatabase;
    use AdminStoresFixtures;

    public function test_the_list_filters_counts_and_shows_no_phones(): void
    {
        $tashkent = $this->region('Toshkent shahri');
        $samarqand = $this->region('Samarqand viloyati');
        $osh = $this->makeStore(['name' => 'Osh markazi', 'phone' => '+998901234567'], $tashkent);
        $burger = $this->makeStore(['name' => 'Burger uyi', 'status' => StoreStatus::SUSPENDED->value], $samarqand);
        $debtor = $this->makeStore(['name' => 'Qarzdor kafe'], $samarqand);
        $debtor->forceFill(['debt_balance' => '500000.00'])->save();
        $this->makeOwner($osh, '+998907654321');
        StoreHealthScore::forceCreate(['store_id' => $debtor->id, 'level' => 'churn_risk', 'score' => 40, 'reasons' => [['key' => 'debt_over_limit', 'level' => 'churn_risk', 'points' => 25, 'label' => 'Qarz kredit limitidan oshgan']], 'metrics' => [], 'computed_at' => now()]);
        StoreHealthScore::forceCreate(['store_id' => $osh->id, 'level' => 'good', 'score' => 100, 'reasons' => [], 'metrics' => [], 'computed_at' => now()]);
        $plan = TariffPlan::query()->where('is_active', true)->orderBy('id')->first();

        $token = $this->signIn($this->makeMember(PlatformRole::SUPPORT));
        $all = $this->adminJson('GET', '/stores', $token)->assertOk();
        $this->assertSame(3, $all->json('data.total'));
        $this->assertSame(['all' => 3, 'good' => 1, 'attention' => 0, 'churn_risk' => 1, 'none' => 1], $all->json('data.health_counts'));
        $this->assertStringNotContainsString('901234567', (string) $all->getContent());
        $this->assertStringNotContainsString('907654321', (string) $all->getContent());

        $names = fn (string $query) => collect($this->adminJson('GET', '/stores?' . $query, $token)->assertOk()->json('data.data'))->pluck('name')->sort()->values()->all();
        $this->assertSame(['Burger uyi', 'Qarzdor kafe'], $names('region_id=' . $samarqand->id));
        $this->assertSame(['Burger uyi'], $names('status=suspended'));
        $this->assertSame(['Qarzdor kafe'], $names('debt=over_limit'));
        $this->assertSame(['Qarzdor kafe'], $names('health=churn_risk'));
        $this->assertSame(['Burger uyi'], $names('health=none'));
        $this->assertSame(['Osh markazi'], $names('q=osh'));
        // A whole phone finds the store or its owner; a part of one does not
        $this->assertSame(['Osh markazi'], $names('q=' . urlencode('+998 90 765 43 21')));
        $this->assertSame([], $names('q=7654'));
        $this->assertSame('Qarzdor kafe', $this->adminJson('GET', '/stores?sort=health', $token)->json('data.data.0.name'));
        $this->assertSame('Qarzdor kafe', $this->adminJson('GET', '/stores?sort=debt', $token)->json('data.data.0.name'));

        if ($plan !== null) {
            app(SubscriptionService::class)->assignPlan($osh, $plan);
            $this->assertSame(['Osh markazi'], $names('plan=' . $plan->code));
            $this->assertSame(['Burger uyi', 'Qarzdor kafe'], $names('plan=none'));
            $row = collect($this->adminJson('GET', '/stores', $token)->json('data.data'))->firstWhere('name', 'Osh markazi');
            $this->assertSame($plan->code, $row['plan']['code']);
        }

        // Unpaid subscription invoices never count against the credit limit (P4-21): a store whose debt is mostly
        // subscription debt is not over it
        $mostlyInvoices = $this->makeStore(['name' => 'Obuna qarzdori'], $samarqand);
        $mostlyInvoices->forceFill(['debt_balance' => '500000.00', 'subscription_debt' => '450000.00'])->save();
        $this->assertSame(['Qarzdor kafe'], $names('debt=over_limit'));
        $this->assertSame(['Obuna qarzdori', 'Qarzdor kafe'], $names('debt=has_debt'));
        $split = collect($this->adminJson('GET', '/stores?q=obuna', $token)->json('data.data'))->sole();
        $this->assertSame(['500000.00', '450000.00', '50000.00', false], [$split['debt_balance'], $split['subscription_debt'], $split['commission_debt'], $split['over_limit']]);
        $card = $this->adminJson('GET', "/stores/{$mostlyInvoices->id}", $token)->assertOk();
        $this->assertSame(['500000.00', '450000.00', '50000.00', '100000.00', false], [
            $card->json('data.finance.debt_balance'), $card->json('data.finance.subscription_debt'), $card->json('data.finance.commission_debt'),
            $card->json('data.finance.credit_limit'), $card->json('data.finance.over_limit'),
        ]);
        $this->assertSame([
            'debt_balance' => '500000.00', 'subscription_debt' => '450000.00', 'commission_debt' => '50000.00', 'credit_limit' => '100000.00', 'over_credit_limit' => false,
        ], $card->json('data.plan.debt'));
        $mostlyInvoices->delete();

        $row = collect($all->json('data.data'))->firstWhere('name', 'Qarzdor kafe');
        $this->assertSame('500000.00', $row['debt_balance']);
        $this->assertSame('100000.00', $row['credit_limit']);
        $this->assertTrue($row['over_limit']);
        $this->assertSame('Qarz kredit limitidan oshgan', $row['health']['top_reason']);
        $this->assertSame('Samarqand viloyati', $row['region']['name']);
    }

    public function test_the_card_has_every_section_and_masks_phones_without_pii_view(): void
    {
        $store = $this->makeStore(['phone' => '+998901234567', 'tin' => '305000111'], $this->region());
        $this->makeOwner($store, '+998907654321', '555');
        StoreStatusLog::create(['store_id' => $store->id, 'from_status' => 'pending', 'to_status' => 'active', 'comment' => 'Ulandi']);

        $support = $this->makeMember(PlatformRole::SUPPORT);
        $card = $this->adminJson('GET', "/stores/{$store->id}", $this->signIn($support))->assertOk();
        foreach (['profile', 'application', 'contacts', 'branches', 'status_history', 'plan', 'devices', 'fiscal', 'finance', 'orders', 'complaints', 'health', 'crm', 'audit', 'actions', 'links'] as $section) {
            $this->assertArrayHasKey($section, $card->json('data'), $section);
        }
        $this->assertSame('+998 90 *** ** 67', $card->json('data.profile.phone'));
        $this->assertSame('+998 90 *** ** 21', $card->json('data.contacts.0.phone'));
        $this->assertTrue($card->json('data.contacts.0.telegram_linked'));
        $this->assertSame('Faol', $card->json('data.status_history.0.to_label'));
        // P4-21's store billing: entitlements, the debt split, commission per channel
        $this->assertSame('billing_v2', $card->json('data.plan.source'));
        foreach (['subscription', 'entitlements', 'debt', 'commission', 'history', 'invoices'] as $part) {
            $this->assertArrayHasKey($part, $card->json('data.plan'), $part);
        }
        $this->assertSame('/orders?store_id=' . $store->id, $card->json('data.links.orders'));
        $this->assertFalse($card->json('data.actions.manage'));
        $this->assertFalse(AuditLog::where('action', 'admin.pii.revealed')->exists());

        // The superadmin sees them in full, and the reveal is recorded
        $full = $this->adminJson('GET', "/stores/{$store->id}", $this->signIn($this->makeMember(PlatformRole::SUPERADMIN)))->assertOk();
        $this->assertSame('+998 90 123 45 67', $full->json('data.profile.phone'));
        $this->assertTrue($full->json('data.actions.manage'));
        $this->assertTrue(AuditLog::where('action', 'admin.pii.revealed')->exists());

        $this->adminJson('GET', '/stores/999999', $this->signIn($support))->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    public function test_the_picker_serves_buyurtmalar_too(): void
    {
        $region = $this->region('Buxoro viloyati');
        $this->region('Xorazm viloyati');
        $store = $this->makeStore(['name' => 'Buxoro oshxonasi'], $region);
        $this->makeStore(['name' => 'Boshqa joy']);

        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $token = $this->signIn($operator);
        $found = $this->adminJson('GET', '/stores/lookup?q=buxoro', $token)->assertOk();
        $this->assertSame([['id' => $store->id, 'name' => 'Buxoro oshxonasi', 'status' => 'active', 'region' => 'Buxoro viloyati']], $found->json('data.stores'));
        $this->assertSame(['Buxoro viloyati'], array_column($found->json('data.regions'), 'name'));

        $byId = $this->adminJson('GET', '/stores/lookup?ids[]=' . $store->id, $token)->assertOk();
        $this->assertSame([$store->id], array_column($byId->json('data.stores'), 'id'));
        $this->assertCount(2, $byId->json('data.regions'));

        $this->assertCount(2, $this->adminJson('GET', '/stores/lookup', $token)->json('data.stores'));
        $this->assertSame([], $this->adminJson('GET', '/stores/lookup', $token)->json('data.districts'));
        $district = $this->district($region, 'Kogon tumani');
        $this->assertSame([['id' => $district->id, 'name' => 'Kogon tumani']], $this->adminJson('GET', '/stores/lookup?districts_of=' . $region->id, $token)->json('data.districts'));
    }
}
