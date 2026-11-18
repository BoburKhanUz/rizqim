<?php

namespace Tests\Feature\Tenancy;

use App\Support\Tenancy\TenantRls;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * M4 stage 3 (docs/scale/M4_DESIGN.md 4.3): what PostgreSQL itself does on every table of TenantRls::TABLES, with the
 * application out of the way (raw SQL, no tenant bound, no store scope, no switch by the connection class).
 *
 * As the tenant role with store A in `app.store_id`: only A's rows are read, an update or delete of B's rows changes
 * nothing, an insert of a row for B fails with SQLSTATE 42501. As the login role (the tables' owner on an install with
 * one role, a BYPASSRLS role when the roles are split) the same policies change nothing at all.
 * Both stores are the Tenancy world, which seeds every tenant table for both.
 */
class TenantRlsPolicyTest extends TestCase
{
    use RefreshDatabase;
    use TenancyWorld;

    /** A trigger refuses every UPDATE of a visible row (migration 2026_11_01_100600): no own-row update to compare with */
    private const APPEND_ONLY = ['audit_logs'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Http::preventStrayRequests();
        Http::fake();
    }

    /**
     * The role tenant transactions switch to, when this session can take it (the roles are split and
     * scripts/db-roles.sh has run: CI job tests-app-role); null on an install with one role.
     */
    public static function tenantRole(): ?string
    {
        $role = TenantRls::role();
        if ($role === null || DB::getDefaultConnection() !== 'pgsql') {
            return null;
        }
        $can = DB::selectOne("SELECT pg_has_role(current_user, r.oid, 'MEMBER') AND NOT r.rolbypassrls AND NOT r.rolsuper AS ok FROM pg_roles r WHERE r.rolname = ?", [$role]);

        return ($can->ok ?? false) ? $role : null;
    }

    public function test_as_the_tenant_role_postgres_shows_and_accepts_only_the_stores_own_rows(): void
    {
        $role = self::tenantRole();
        if ($role === null) {
            $this->markTestSkipped('Needs the tenant role (split roles, scripts/db-roles.sh): runs in the CI job tests-app-role.');
        }

        $this->buildTenancyWorld();
        $a = (int) $this->world['A']['store']->id;
        $b = (int) $this->world['B']['store']->id;

        $problems = [];
        foreach (TenantRls::TABLES as $table) {
            $own = (int) DB::scalar("SELECT count(*) FROM {$table} WHERE store_id = ?", [$a]);
            $foreign = (int) DB::scalar("SELECT count(*) FROM {$table} WHERE store_id = ?", [$b]);
            $foreignRow = DB::scalar("SELECT to_jsonb(t) FROM {$table} t WHERE store_id = ? ORDER BY id LIMIT 1", [$b]);
            if ($own === 0 || $foreign === 0) {
                $problems[] = "{$table}: the world has no row of " . ($own === 0 ? 'A' : 'B') . ': seed it (TenancyWorld)';

                continue;
            }

            DB::beginTransaction();
            try {
                DB::unprepared("SET LOCAL ROLE \"{$role}\"; SELECT set_config('app.store_id', '{$a}', true)");

                $seen = DB::selectOne("SELECT count(*) AS n, count(*) FILTER (WHERE store_id IS DISTINCT FROM {$a}) AS other FROM {$table}");
                if ((int) $seen->n !== $own || (int) $seen->other !== 0) {
                    $problems[] = "{$table}: the tenant role of store A reads {$seen->n} rows ({$seen->other} of another store), A has {$own}";
                }
                if (($n = DB::update("UPDATE {$table} SET store_id = store_id WHERE store_id = ?", [$b])) !== 0) {
                    $problems[] = "{$table}: an UPDATE by the tenant role of store A changed {$n} rows of store B";
                }
                if (($n = DB::delete("DELETE FROM {$table} WHERE store_id = ?", [$b])) !== 0) {
                    $problems[] = "{$table}: a DELETE by the tenant role of store A removed {$n} rows of store B";
                }
                // Control: the same statement on its own rows does reach them (so 0 above is the policy, not the SQL)
                if (!in_array($table, self::APPEND_ONLY, true) && ($n = DB::update("UPDATE {$table} SET store_id = store_id WHERE store_id = ?", [$a])) !== $own) {
                    $problems[] = "{$table}: the tenant role of store A updated {$n} of its own {$own} rows";
                }

                // B's own row, sent again: the policy is checked before the table's unique indexes, so the refusal is
                // the policy's. The BEFORE INSERT triggers run first: payments' cannot see B's order and leaves the
                // store as sent; fiscal_receipts' writes the key registry (not under the policy: uniqueness across
                // stores is its purpose), hence a fresh uuid and idempotency key, and is undone with the statement
                $forged = json_decode((string) $foreignRow, true);
                foreach (['uuid' => (string) Str::uuid(), 'idempotency_key' => 'rls-forged-' . Str::random(12)] as $column => $fresh) {
                    if (array_key_exists($column, $forged)) {
                        $forged[$column] = $fresh;
                    }
                }
                DB::beginTransaction();
                try {
                    DB::insert("INSERT INTO {$table} SELECT * FROM jsonb_populate_record(NULL::{$table}, ?::jsonb)", [json_encode($forged)]);
                    $problems[] = "{$table}: the tenant role of store A inserted a row of store B";
                } catch (QueryException $e) {
                    if ($e->getCode() !== '42501' || !str_contains($e->getMessage(), 'row-level security')) {
                        $problems[] = "{$table}: the insert of a row of store B failed, but not by the policy: " . mb_substr($e->getMessage(), 0, 200);
                    }
                } finally {
                    DB::rollBack();
                }
            } finally {
                DB::rollBack();
            }

            if ((int) DB::scalar("SELECT count(*) FROM {$table} WHERE store_id = ?", [$b]) !== $foreign) {
                $problems[] = "{$table}: store B's rows changed";
            }
        }

        $this->assertSame([], $problems);
        $this->assertSame(DB::scalar('SELECT session_user'), DB::scalar('SELECT current_user'), 'the role ended with each rollback');
    }

    /**
     * The login role is never bound: on an install with one database role it owns the tables (no FORCE), with split
     * roles it has BYPASSRLS. Whatever `app.store_id` says, it reads and writes every store's rows, as before stage 3.
     */
    public function test_for_the_login_role_the_policies_change_nothing(): void
    {
        $this->buildTenancyWorld();
        $a = (int) $this->world['A']['store']->id;
        $b = (int) $this->world['B']['store']->id;

        $problems = [];
        DB::beginTransaction();
        try {
            DB::unprepared("SELECT set_config('app.store_id', '{$a}', true)");
            foreach (TenantRls::TABLES as $table) {
                if (DB::scalar('SELECT row_security_active(?::regclass)', [$table])) {
                    $problems[] = "{$table}: row-level security binds the login role " . DB::scalar('SELECT current_user');
                }
                $foreign = (int) DB::scalar("SELECT count(*) FROM {$table} WHERE store_id = ?", [$b]);
                $all = (int) DB::scalar("SELECT count(*) FROM {$table}");
                if ($foreign === 0 || $all <= $foreign) {
                    $problems[] = "{$table}: the login role reads {$all} rows, {$foreign} of store B, with store A in app.store_id";
                }
                if (!in_array($table, self::APPEND_ONLY, true) && DB::update("UPDATE {$table} SET store_id = store_id WHERE store_id = ?", [$b]) !== $foreign) {
                    $problems[] = "{$table}: the login role could not update store B's rows";
                }
            }
        } finally {
            DB::rollBack();
        }

        $this->assertSame([], $problems);
    }
}
