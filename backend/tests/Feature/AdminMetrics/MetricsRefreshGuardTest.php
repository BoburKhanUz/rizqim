<?php

namespace Tests\Feature\AdminMetrics;

use App\Enums\PlatformRole;
use App\Services\Admin\Metrics\MetricsReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Review W3 (minor): GET /admin/metrics?refresh=1 rebuilds the numbers at most once a minute per member; more often,
 * and without refresh, the cached report is served.
 */
class MetricsRefreshGuardTest extends TestCase
{
    use MetricsFixtures;
    use RefreshDatabase;

    public function test_a_member_refreshes_at_most_once_a_minute(): void
    {
        Cache::flush();
        $stale = fn () => Cache::put(MetricsReport::cacheKey(4), ['cards' => ['mrr' => 'stale']], 600);
        $mrr = fn (string $token, string $query) => $this->metricsJson('GET', '/metrics?months=4' . $query, $token)->assertOk()->json('data.cards.mrr');
        $finance = $this->memberToken(PlatformRole::FINANCE);

        $stale();
        $this->assertSame('stale', $mrr($finance, ''), 'a plain read is served from the cache');
        $this->assertNotSame('stale', $mrr($finance, '&refresh=1'));

        $stale();
        $this->assertSame('stale', $mrr($finance, '&refresh=1'), 'a second refresh within the minute reads the cache');

        // Another member has their own minute
        $this->assertNotSame('stale', $mrr($this->memberToken(PlatformRole::SALES), '&refresh=1'));

        $this->travel(61)->seconds();
        $stale();
        $this->assertNotSame('stale', $mrr($finance, '&refresh=1'));
    }
}
