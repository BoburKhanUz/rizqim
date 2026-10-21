<?php

namespace Tests\Feature;

use App\Domain\Delivery\ZoneResolver;
use App\Enums\OrderStatus;
use App\Filament\Pages\StoreSettings;
use App\Support\StoreTimezone;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P2-33 (review A, finding 4, and the analytics time zone): one safe reading of the store's time zone everywhere
 * (StoreTimezone: an unknown identifier falls back to Asia/Tashkent instead of a 500), both settings forms validate it,
 * and the Mini App's analytics count the days of the store's time zone, as the reports do.
 */
class StoreTimezoneFallbackTest extends TestCase
{
    use RefreshDatabase;
    use ManagementFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpManagement();
    }

    public function test_an_unknown_stored_time_zone_falls_back_instead_of_failing(): void
    {
        $this->store->forceFill(['timezone' => 'Tashkent'])->save();
        $this->assertSame('Asia/Tashkent', StoreTimezone::of($this->store->fresh()));
        $this->assertSame('Asia/Tashkent', StoreTimezone::of(null));
        $this->assertSame('Europe/Moscow', StoreTimezone::orDefault('Europe/Moscow'));

        // 2026-10-20 20:30 UTC is 2026-10-21 01:30 in Tashkent: the board's date filter uses the fallback
        $order = $this->makeOrder(OrderStatus::NEW);
        $order->forceFill(['created_at' => CarbonImmutable::parse('2026-10-20 20:30:00', 'UTC')])->save();

        $this->as($this->manager, 'GET', '/api/v1/staff/orders?date=2026-10-21')->assertOk()
            ->assertJsonPath('data.0.uuid', $order->uuid);
        $this->as($this->manager, 'GET', '/api/v1/staff/orders?date=2026-10-20')->assertOk()->assertJsonCount(0, 'data');

        $this->as($this->owner, 'GET', '/api/v1/owner/branches')->assertOk()
            ->assertJsonPath('data.0.working_hours.timezone', 'Asia/Tashkent');
        $this->as($this->owner, 'GET', '/api/v1/owner/reports/sales?from=2026-10-21&to=2026-10-21')->assertOk()
            ->assertJsonPath('data.period.timezone', 'Asia/Tashkent');
        $this->as($this->owner, 'GET', '/api/v1/owner/analytics?period=custom&from=2026-10-21&to=2026-10-21')->assertOk()
            ->assertJsonPath('data.period.timezone', 'Asia/Tashkent')
            ->assertJsonPath('data.summary.orders_count', 1);

        // Delivery's opening hours check and the store's own is_open
        $this->branch->forceFill(['open_time' => '09:00:00', 'close_time' => '18:00:00'])->save();
        app(ZoneResolver::class)->isBranchOpen($this->branch->fresh(), CarbonImmutable::now(), $this->store->fresh());
        $store = $this->store->fresh()->forceFill(['open_time' => '09:00:00', 'close_time' => '18:00:00', 'is_open' => true]);
        $this->assertIsBool($store->is_open);
    }

    public function test_both_settings_forms_refuse_an_unknown_time_zone(): void
    {
        $this->as($this->owner, 'POST', '/api/v1/owner/settings', ['timezone' => 'Tashkent'])
            ->assertStatus(422)->assertJsonValidationErrors('timezone');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->owner, 'web');
        Filament::setCurrentPanel('admin');
        Filament::setTenant($this->store->fresh());

        Livewire::test(StoreSettings::class)
            ->set('data.timezone', 'Tashkent')
            ->call('save')
            ->assertHasErrors(['data.timezone']);
        $this->assertNotSame('Tashkent', $this->store->fresh()->timezone);

        Livewire::test(StoreSettings::class)
            ->set('data.timezone', 'Asia/Samarkand')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('Asia/Samarkand', $this->store->fresh()->timezone);
    }

    public function test_analytics_count_the_days_of_the_stores_time_zone_like_the_reports(): void
    {
        $this->store->forceFill(['timezone' => 'America/New_York'])->save();

        // 2026-10-21 03:00 UTC: 2026-10-20 23:00 in New York, 2026-10-21 08:00 in Tashkent
        $order = $this->makeOrder(OrderStatus::DELIVERED, ['total_price' => 70000]);
        $order->forceFill(['created_at' => CarbonImmutable::parse('2026-10-21 03:00:00', 'UTC')])->save();

        $analytics = $this->as($this->owner, 'GET', '/api/v1/owner/analytics?period=custom&from=2026-10-20&to=2026-10-20')->assertOk();
        $reports = $this->as($this->owner, 'GET', '/api/v1/owner/reports/sales?from=2026-10-20&to=2026-10-20')->assertOk();

        $analytics->assertJsonPath('data.period.timezone', 'America/New_York')
            ->assertJsonPath('data.summary.orders_count', 1)
            ->assertJsonPath('data.summary.delivered_count', 1);
        $reports->assertJsonPath('data.summary.orders_count', 1);
        $this->assertEquals((float) $reports->json('data.summary.revenue'), (float) $analytics->json('data.summary.revenue'));

        $this->as($this->owner, 'GET', '/api/v1/owner/analytics?period=custom&from=2026-10-21&to=2026-10-21')->assertOk()
            ->assertJsonPath('data.summary.orders_count', 0);
    }
}
