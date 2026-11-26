<?php

namespace Tests\Feature\AdminCustomers;

use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AccessFixtures;
use Tests\TestCase;

/**
 * A privacy request from the user card: "Statistikasini o'chirish" (POST /admin/users/{id}/forget-analytics) deletes
 * every analytics_events row of the account (AnalyticsPrivacy::forget, nothing else), with admin.support.manage, a
 * step-up, a reason and an Idempotency-Key; audited with the member, the account, the count and the reason. The card
 * shows how many rows are stored.
 */
class AdminForgetAnalyticsTest extends TestCase
{
    use RefreshDatabase;
    use AdminCustomersFixtures;
    use AccessFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLifecycleStore();
    }

    private function insertFor(int $userId, int $count = 1): void
    {
        foreach (range(1, $count) as $i) {
            DB::table('analytics_events')->insert([
                'received_at' => now()->subDays($i * 20)->format('Y-m-d H:i:s'), 'occurred_at' => now()->format('Y-m-d H:i:s'),
                'session_id' => (string) Str::uuid(), 'event_uid' => (string) Str::uuid(), 'user_id' => $userId, 'event' => 1, 'source' => 1, 'platform' => 0,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function forget(User $user, string $token, array $data, ?string $key = null): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $request = $this->withToken($token);
        if ($key !== null) {
            $request = $request->withHeader('Idempotency-Key', $key);
        }

        return $request->json('POST', "/api/v1/admin/users/{$user->id}/forget-analytics", $data);
    }

    public function test_the_card_shows_the_stored_count_and_offers_the_action_to_support_managers(): void
    {
        $customer = $this->makeCustomer();
        $this->insertFor($customer->id, 3);

        $viewer = $this->makeMember(PlatformRole::VIEWER);
        $this->adminJson('GET', "/users/{$customer->id}", $this->signIn($viewer))
            ->assertOk()
            ->assertJsonPath('data.analytics.events', 3)
            ->assertJsonPath('data.actions.forget_analytics', false);

        $support = $this->makeMember(PlatformRole::SUPPORT);
        $this->adminJson('GET', "/users/{$customer->id}", $this->signIn($support))
            ->assertOk()
            ->assertJsonPath('data.actions.forget_analytics', true);
    }

    public function test_forgetting_needs_support_manage_a_step_up_and_a_reason_deletes_only_that_account_and_is_audited(): void
    {
        $customer = $this->makeCustomer();
        $other = $this->makeCustomer();
        $this->insertFor($customer->id, 4);
        $this->insertFor($other->id, 2);

        $viewer = $this->makeMember(PlatformRole::VIEWER);
        $this->forget($customer, $this->confirmedSession($viewer), ['reason' => "Mijoz so'rovi"])->assertForbidden();

        $support = $this->makeMember(PlatformRole::SUPPORT);
        $token = $this->signIn($support);
        $this->forget($customer, $token, ['reason' => "Mijoz so'rovi"])->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->stepUp($support, $token);
        $this->forget($customer, $token, [])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->assertSame(4, DB::table('analytics_events')->where('user_id', $customer->id)->count(), 'nothing deleted by a refused call');

        $this->forget($customer, $token, ['reason' => "Mijoz Telegram orqali so'radi"], (string) Str::uuid())
            ->assertOk()
            ->assertJsonPath('message', "Statistika yozuvlari o'chirildi: 4 ta.")
            ->assertJsonPath('data.analytics.events', 0)
            ->assertJsonPath('data.profile.id', $customer->id);

        $this->assertSame(0, DB::table('analytics_events')->where('user_id', $customer->id)->count());
        $this->assertSame(2, DB::table('analytics_events')->where('user_id', $other->id)->count());

        $entry = AuditLog::where('action', 'admin.users.analytics_forgotten')->sole();
        $this->assertSame($support->id, (int) $entry->user_id);
        $this->assertSame(User::class, $entry->model_type);
        $this->assertSame($customer->id, (int) $entry->model_id);
        $this->assertSame(4, $entry->payload_json['analytics_events_deleted']);
        $this->assertSame("Mijoz Telegram orqali so'radi", $entry->payload_json['reason']);
    }

    public function test_a_repeat_with_the_same_idempotency_key_answers_the_first_result_and_records_once(): void
    {
        $customer = $this->makeCustomer();
        $this->insertFor($customer->id, 2);
        $support = $this->makeMember(PlatformRole::SUPPORT);
        $token = $this->confirmedSession($support);
        $key = (string) Str::uuid();

        $this->forget($customer, $token, ['reason' => "Mijoz so'rovi"], $key)->assertOk()->assertJsonPath('message', "Statistika yozuvlari o'chirildi: 2 ta.");
        $this->forget($customer, $token, ['reason' => "Mijoz so'rovi"], $key)
            ->assertOk()->assertHeader('Idempotent-Replayed', 'true')->assertJsonPath('message', "Statistika yozuvlari o'chirildi: 2 ta.");
        $this->assertSame(1, AuditLog::where('action', 'admin.users.analytics_forgotten')->count());

        // A new key runs again: nothing left, and that is recorded as well (the request was handled)
        $this->forget($customer, $token, ['reason' => 'Yana tekshirildi'], (string) Str::uuid())->assertOk()->assertJsonPath('message', "Statistika yozuvlari o'chirildi: 0 ta.");
        $this->assertSame([2, 0], AuditLog::where('action', 'admin.users.analytics_forgotten')->orderBy('id')->get()->map(fn (AuditLog $e) => $e->payload_json['analytics_events_deleted'])->all());
    }

    public function test_never_on_a_team_account_or_an_unknown_user(): void
    {
        $support = $this->makeMember(PlatformRole::SUPPORT);
        $token = $this->confirmedSession($support);
        $member = $this->makeMember(PlatformRole::FINANCE);

        $this->forget($member, $token, ['reason' => "Mijoz so'rovi"])->assertForbidden()->assertJsonPath('code', 'platform_account');
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withToken($token)->json('POST', '/api/v1/admin/users/999999999/forget-analytics', ['reason' => "Mijoz so'rovi"])->assertNotFound();
        $this->assertSame(0, AuditLog::where('action', 'admin.users.analytics_forgotten')->count());
    }
}
