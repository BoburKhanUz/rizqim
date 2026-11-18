<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\AccessFixtures;
use Tests\TestCase;

/**
 * M4 stage 2: a streamed body is written when the response is sent, after the `tenant` middleware's binding is gone;
 * a tenant route that streams binds the store again inside the callback, so its queries are scoped too.
 */
class StreamedResponseTenantTest extends TestCase
{
    use AccessFixtures;
    use RefreshDatabase;

    public function test_the_csv_export_body_is_written_bound_to_the_callers_store(): void
    {
        $store = $this->makeStore('S');
        $this->makeBranch($store, 'B');
        $owner = $this->makeStaff(UserRole::OWNER, null, $store);

        foreach (['orders', 'sales', 'products'] as $report) {
            $response = $this->bearerJson('GET', "/api/v1/owner/reports/export?report={$report}&from=2026-10-20&to=2026-10-21", $owner->createToken('t')->plainTextToken)->assertOk();
            $this->assertNull(app(TenantContext::class)->storeId());

            $bound = [];
            $listening = true;
            DB::listen(function (QueryExecuted $query) use (&$bound, &$listening) {
                if ($listening) {
                    $bound[] = app(TenantContext::class)->storeId();
                }
            });
            $response->streamedContent();
            $listening = false;

            $this->assertNotSame([], $bound, "{$report}: the body ran no query");
            $this->assertSame([$store->id], array_values(array_unique($bound)), "{$report}: the body ran unbound");
            $this->assertNull(app(TenantContext::class)->storeId(), "{$report}: the binding outlived the body");
        }
    }
}
