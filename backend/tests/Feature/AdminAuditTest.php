<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * P4-11: every admin write lands in the harakatlar jurnali through one mechanism (RecordAdminAudit), with the member,
 * subject, before/after, reason, IP and user agent; entries can never be changed or deleted; GET /admin/audit-logs
 * shows the whole platform with filters and cursor pages.
 */
class AdminAuditTest extends TestCase
{
    use RefreshDatabase;
    use AdminFixtures;
    use OrderLifecycleFixtures;

    public function test_every_admin_route_is_named_and_audited(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/admin'));
        $this->assertGreaterThan(15, $routes->count());

        foreach ($routes as $route) {
            $this->assertNotNull($route->getName(), "{$route->uri()} has no name (its audit action)");
            $this->assertStringStartsWith('admin.', $route->getName());
            $this->assertContains('admin.audit', $route->gatherMiddleware(), "{$route->uri()} is not audited");
        }

        // Nothing in the admin API changes or removes audit entries
        $writes = $routes->filter(fn ($route) => str_contains($route->uri(), 'audit') && array_diff($route->methods(), ['GET', 'HEAD']) !== []);
        $this->assertCount(0, $writes);
    }

    public function test_each_admin_write_is_recorded_with_actor_subject_changes_ip_and_user_agent(): void
    {
        $superadmin = $this->makeMember();
        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $token = $this->confirmedSession($superadmin);
        $this->withHeader('User-Agent', 'RizqimAdmin/1.0 (test)');

        $this->withHeader('User-Agent', 'RizqimAdmin/1.0 (test)')->withToken($token)
            ->patchJson("/api/v1/admin/team/{$operator->id}", ['platform_role' => 'support', 'reason' => 'Yordam bo\'limiga o\'tdi'])->assertOk();

        $entry = AuditLog::where('action', 'admin.team.updated')->sole();
        $this->assertSame($superadmin->id, $entry->user_id);
        $this->assertSame(User::class, $entry->model_type);
        $this->assertSame($operator->id, (int) $entry->model_id);
        $this->assertSame(['platform_role' => 'operator'], $entry->before);
        $this->assertSame(['platform_role' => 'support'], $entry->after);
        $this->assertSame("Yordam bo'limiga o'tdi", $entry->payload_json['reason']);
        $this->assertSame('127.0.0.1', $entry->ip);
        $this->assertSame('RizqimAdmin/1.0 (test)', $entry->user_agent);

        // The rest of the writes, one each
        $this->adminJson('PUT', '/roles', $token, ['role' => 'viewer', 'permission' => 'admin.export', 'allowed' => true])->assertOk();
        $invite = $this->adminJson('POST', '/team', $token, ['name' => 'Kamola', 'email' => 'kamola@rizqim.test', 'platform_role' => 'viewer'])->assertCreated();
        $this->adminJson('POST', '/team/' . $invite->json('data.member.id') . '/invite', $token)->assertOk();
        $this->adminJson('POST', "/team/{$operator->id}/reset-2fa", $token)->assertOk();
        $this->adminJson('POST', '/auth/2fa/recovery-codes', $token)->assertOk();
        $other = $this->signIn($superadmin);
        $otherId = $this->adminJson('GET', '/auth/sessions', $other)->json('data.0.id');
        $this->stepUp($superadmin, $token);
        $this->adminJson('DELETE', "/auth/sessions/{$otherId}", $token)->assertOk();
        $this->adminJson('POST', '/auth/logout', $token)->assertOk();

        foreach ([
            'admin.roles.updated', 'admin.team.invited', 'admin.team.invite_reissued', 'admin.team.2fa_reset',
            'admin.auth.recovery_codes_regenerated', 'admin.auth.session_revoked', 'admin.auth.logged_out',
            'admin.auth.reauthenticated', 'admin.auth.login_succeeded', 'admin.auth.password_accepted',
        ] as $action) {
            $this->assertGreaterThanOrEqual(1, AuditLog::where('action', $action)->where('user_id', $superadmin->id)->count(), "no {$action} entry");
        }

        // No secrets anywhere in the log
        $log = AuditLog::all()->toJson();
        $this->assertStringNotContainsString($token, $log);
        $this->assertStringNotContainsString($this->secrets[$superadmin->id], $log);
        $this->assertStringNotContainsString($invite->json('data.invite_token'), $log);
    }

    public function test_behind_the_admin_apps_proxy_the_client_address_and_browser_are_recorded(): void
    {
        $superadmin = $this->makeMember();
        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $token = $this->confirmedSession($superadmin);

        // The admin app's server-side proxy (a private address) forwards the browser's address and user agent
        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.7', 'User-Agent' => 'Mozilla/5.0 (Android) Chrome'])
            ->withToken($token)
            ->patchJson("/api/v1/admin/team/{$operator->id}", ['platform_role' => 'viewer'])
            ->assertOk();

        $entry = AuditLog::where('action', 'admin.team.updated')->sole();
        $this->assertSame('203.0.113.7', $entry->ip);
        $this->assertSame('Mozilla/5.0 (Android) Chrome', $entry->user_agent);

        // A public address cannot pass itself off as another
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.7'])
            ->postJson('/api/v1/admin/auth/login', ['email' => 'nobody@rizqim.test', 'password' => 'wrong-password-1']);
        $this->assertSame('198.51.100.9', AuditLog::where('action', 'admin.auth.login_failed')->latest('id')->first()->ip);
    }

    public function test_a_refused_write_is_not_recorded_as_done(): void
    {
        $superadmin = $this->makeMember();
        $token = $this->signIn($superadmin);
        $operator = $this->makeMember(PlatformRole::OPERATOR);

        $this->adminJson('PATCH', "/team/{$operator->id}", $token, ['platform_role' => 'viewer'])->assertForbidden();
        $this->assertSame(0, AuditLog::where('action', 'admin.team.updated')->count());
    }

    public function test_audit_entries_cannot_be_changed_or_deleted(): void
    {
        $entry = app(AuditLogger::class)->record('admin.test', null, [], [], ['note' => 'asl'], ['actor' => null, 'device' => null]);

        try {
            $entry->update(['action' => 'changed']);
            $this->fail('An audit entry was updated through the model.');
        } catch (\LogicException) {
        }

        try {
            $entry->delete();
            $this->fail('An audit entry was deleted through the model.');
        } catch (\LogicException) {
        }

        foreach ([
            fn () => DB::table('audit_logs')->where('id', $entry->id)->update(['action' => 'changed']),
            fn () => DB::table('audit_logs')->where('id', $entry->id)->update(['payload_json' => json_encode(['note' => 'boshqa'])]),
            fn () => DB::table('audit_logs')->where('id', $entry->id)->update(['user_id' => User::factory()->create()->id]),
            fn () => DB::table('audit_logs')->where('id', $entry->id)->delete(),
            fn () => AuditLog::query()->whereKey($entry->id)->delete(),
        ] as $attempt) {
            try {
                DB::transaction($attempt);
                $this->fail('An audit entry was changed by a query.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('append-only', $e->getMessage());
            }
        }

        $this->assertSame('admin.test', AuditLog::find($entry->id)->action);
        $this->assertSame(['note' => 'asl'], AuditLog::find($entry->id)->payload_json);

        // Deleting the actor keeps the entry, without the link (ON DELETE SET NULL is the one change allowed)
        $user = User::factory()->create();
        $linked = app(AuditLogger::class)->record('admin.test_linked', null, [], [], [], ['actor' => $user, 'device' => null]);
        $user->delete();
        $this->assertNull(AuditLog::find($linked->id)->user_id);
        $this->assertSame('admin.test_linked', AuditLog::find($linked->id)->action);
    }

    public function test_the_platform_log_lists_every_store_with_filters_and_cursor_pages(): void
    {
        $this->setUpLifecycleStore();
        $otherStore = $this->makeStore('Boshqa');
        $superadmin = $this->makeMember();
        $viewer = $this->makeMember(PlatformRole::VIEWER);
        $support = $this->makeMember(PlatformRole::SUPPORT);

        $logger = app(AuditLogger::class);
        $logger->record('order.cancelled', $this->store, [], [], ['reason' => 'Mijoz rad etdi'], ['actor' => $this->owner, 'device' => null]);
        $logger->record('order.cancelled', $otherStore, [], [], [], ['actor' => null, 'device' => null, 'store_id' => $otherStore->id]);
        $logger->record('menu.changed', $this->store, ['price' => 1], ['price' => 2], [], ['actor' => $this->owner, 'device' => null]);

        $token = $this->signIn($viewer);
        $this->adminJson('GET', '/audit-logs', $this->signIn($support))->assertForbidden();

        $all = $this->adminJson('GET', '/audit-logs', $token)->assertOk();
        $actions = array_column($all->json('data.data'), 'action');
        $this->assertContains('order.cancelled', $actions);
        $this->assertContains('admin.auth.login_succeeded', $actions);

        $this->adminJson('GET', '/audit-logs?action=order.*', $token)->assertJsonCount(2, 'data.data');
        $this->adminJson('GET', '/audit-logs?action=order', $token)->assertJsonCount(2, 'data.data');
        $this->adminJson('GET', "/audit-logs?store_id={$otherStore->id}", $token)->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.store.name', 'Boshqa');
        $this->adminJson('GET', "/audit-logs?actor_id={$this->owner->id}", $token)->assertJsonCount(2, 'data.data');
        $this->adminJson('GET', "/audit-logs?subject_type=Store&subject_id={$this->store->id}", $token)->assertJsonCount(2, 'data.data');
        $this->adminJson('GET', '/audit-logs?q=rad+etdi', $token)->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.reason', 'Mijoz rad etdi');
        $this->adminJson('GET', '/audit-logs?to=' . now()->subDay()->toDateString(), $token)->assertJsonCount(0, 'data.data');
        $this->adminJson('GET', '/audit-logs?q=x', $token)->assertStatus(422);

        $first = $this->adminJson('GET', '/audit-logs?per_page=2', $token)->assertJsonCount(2, 'data.data');
        $cursor = $first->json('data.next_cursor');
        $this->assertNotNull($cursor);
        $second = $this->adminJson('GET', '/audit-logs?per_page=2&cursor=' . urlencode($cursor), $token)->assertOk();
        $this->assertLessThan($first->json('data.data.1.id'), $second->json('data.data.0.id'));
    }
}
