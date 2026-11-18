<?php

namespace Tests\Feature\Tenancy;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * M4 stage 2, the scope's behaviour for every scoped model (the inventory's list): inside a tenant request of store A,
 * an Eloquent read of store B's row by primary key finds nothing, while A's own row of the same model is found (so the
 * miss is the scope's, not a wrong id). Both stores are the Tenancy world, which seeds every tenant table for both.
 */
class StoreScopeIsolationTest extends TestCase
{
    use RefreshDatabase;
    use TenancyWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Http::preventStrayRequests();
        Http::fake();
    }

    public function test_a_tenant_request_reads_no_row_of_another_store_by_primary_key(): void
    {
        $this->buildTenancyWorld();
        $storeA = $this->world['A']['store']->id;
        $storeB = $this->world['B']['store']->id;

        $rows = [];
        $problems = [];
        foreach (StoreScopeInventoryTest::scopedModels() as $model) {
            $instance = new $model();
            $table = $instance->getTable();
            $key = $instance->getKeyName();
            $rowOf = fn (int $store) => DB::table($table)->where('store_id', $store)
                ->when(Schema::hasColumn($table, 'deleted_at'), fn ($q) => $q->orderByRaw('deleted_at IS NOT NULL'))
                ->orderBy($key)->value($key);
            [$a, $b] = [$rowOf($storeA), $rowOf($storeB)];
            if ($a === null || $b === null) {
                $problems[] = "{$model}: the world has no row of " . ($a === null ? 'A' : 'B') . " in {$table}: seed it (TenancyWorld)";
                continue;
            }
            $rows[$model] = [$a, $b];
        }

        Route::middleware(['api', 'auth:sanctum', 'tenant'])->post('api/test-tenant/read', function (Request $request) {
            $seen = ['store' => app(TenantContext::class)->storeId()];
            foreach ($request->input('rows') as $model => [$a, $b]) {
                $query = fn () => $model::query()->withoutGlobalScope(SoftDeletingScope::class); // soft-deleted rows too: only the store counts here
                $seen[$model] = [
                    'own' => $query()->find($a) !== null,
                    'find' => $query()->find($b) !== null,
                    'where' => $query()->whereKey($b)->exists(),
                    'first' => $query()->where($query()->getModel()->getQualifiedKeyName(), $b)->first() !== null,
                ];
            }

            return response()->json($seen);
        });

        $seen = $this->withBearer($this->world['A']['owner_token'])->postJson('/api/test-tenant/read', ['rows' => $rows])->assertOk()->json();
        $this->assertSame($storeA, $seen['store']);
        foreach ($rows as $model => $ids) {
            if (!$seen[$model]['own']) {
                $problems[] = "{$model}: A's own row {$ids[0]} not found in A's request (the check proves nothing)";
            }
            foreach (['find', 'where', 'first'] as $how) {
                if ($seen[$model][$how]) {
                    $problems[] = "{$model}: B's row {$ids[1]} READ in A's tenant request ({$how})";
                }
            }
        }

        // The same reads outside a tenant request find B's rows: nothing changes there
        foreach ($rows as $model => [, $b]) {
            if ($model::query()->withoutGlobalScope(SoftDeletingScope::class)->find($b) === null) {
                $problems[] = "{$model}: B's row {$b} not found outside a tenant request";
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
        $this->assertGreaterThan(80, count($rows));
    }
}
