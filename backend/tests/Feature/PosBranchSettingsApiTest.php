<?php

namespace Tests\Feature;

use App\Domain\Pos\Setup\PosSettings;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P3-13: the branch's POS settings (business day start, mode, printing till, screen lock, queue numbers, alert
 * thresholds, holidays): defaults, validation, merging, isolation.
 */
class PosBranchSettingsApiTest extends TestCase
{
    use RefreshDatabase;
    use PosSetupFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpManagement();
    }

    private function url(?int $branchId = null): string
    {
        return '/api/v1/owner/branches/' . ($branchId ?? $this->branch->id) . '/pos-settings';
    }

    public function test_a_branch_without_settings_reads_the_defaults(): void
    {
        $response = $this->as($this->owner, 'GET', $this->url())->assertOk()
            ->assertJsonPath('data.branch_id', $this->branch->id)
            ->assertJsonPath('data.timezone', 'Asia/Tashkent')
            ->assertJsonPath('data.business_day_starts_at', '05:00')
            ->assertJsonPath('data.pos_settings.mode', 'hybrid')
            ->assertJsonPath('data.pos_settings.printing_device_id', null)
            ->assertJsonPath('data.pos_settings.screen_lock_minutes', 5)
            ->assertJsonPath('data.pos_settings.queue_numbers', false)
            ->assertJsonPath('data.pos_settings.holidays', []);

        $this->assertSame(['open_room_hours' => 6, 'void_amount' => '200000.00', 'discount_percent' => '10.00', 'unaccepted_online_minutes' => 3], $response->json('data.pos_settings.alerts'));
        $this->assertNull($this->branch->fresh()->pos_settings);
        $this->assertSame('05:00:00', $this->branch->fresh()->business_day_starts_at);
    }

    public function test_the_settings_are_saved_merged_and_audited(): void
    {
        $terminal = $this->pairTerminal($this->owner, $this->branch)['device'];

        $this->as($this->owner, 'PUT', $this->url(), [
            'business_day_starts_at' => '04:00',
            'pos_settings' => [
                'mode' => 'table',
                'printing_device_id' => $terminal->uuid,
                'screen_lock_minutes' => 0,
                'queue_numbers' => true,
                'alerts' => ['open_room_hours' => 8, 'void_amount' => '150000.5', 'discount_percent' => null],
                'holidays' => ['2027-01-01', '2026-12-31', '2027-01-01'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.business_day_starts_at', '04:00')
            ->assertJsonPath('data.pos_settings.mode', 'table')
            ->assertJsonPath('data.pos_settings.printing_device_id', $terminal->uuid)
            ->assertJsonPath('data.pos_settings.screen_lock_minutes', 0)
            ->assertJsonPath('data.pos_settings.queue_numbers', true)
            ->assertJsonPath('data.pos_settings.alerts', ['open_room_hours' => 8, 'void_amount' => '150000.50', 'discount_percent' => null, 'unaccepted_online_minutes' => 3])
            ->assertJsonPath('data.pos_settings.holidays', ['2026-12-31', '2027-01-01']);
        $this->assertSame('04:00:00', $this->branch->fresh()->business_day_starts_at);

        // A later change touches only what it names: alerts merge key by key, the rest stays
        $this->as($this->owner, 'PUT', $this->url(), ['pos_settings' => ['alerts' => ['unaccepted_online_minutes' => 10], 'mode' => 'counter']])
            ->assertOk()
            ->assertJsonPath('data.business_day_starts_at', '04:00')
            ->assertJsonPath('data.pos_settings.mode', 'counter')
            ->assertJsonPath('data.pos_settings.printing_device_id', $terminal->uuid)
            ->assertJsonPath('data.pos_settings.queue_numbers', true)
            ->assertJsonPath('data.pos_settings.alerts', ['open_room_hours' => 8, 'void_amount' => '150000.50', 'discount_percent' => null, 'unaccepted_online_minutes' => 10]);

        // null clears the printing till; an empty list clears the holidays
        $this->as($this->owner, 'PUT', $this->url(), ['pos_settings' => ['printing_device_id' => null, 'holidays' => []]])
            ->assertOk()->assertJsonPath('data.pos_settings.printing_device_id', null)->assertJsonPath('data.pos_settings.holidays', []);

        // The booking length P3-17 reads from the branch's settings
        $this->as($this->owner, 'PUT', $this->url(), ['pos_settings' => ['booking_slot_minutes' => 240]])->assertOk()->assertJsonPath('data.pos_settings.booking_slot_minutes', 240);
        $this->assertSame(240, \App\Domain\Pos\Booking\PosSettingsReader::slotMinutes($this->branch->fresh()));
        $this->as($this->owner, 'PUT', $this->url(), ['pos_settings' => ['booking_slot_minutes' => null]])->assertOk()->assertJsonPath('data.pos_settings.booking_slot_minutes', null);

        // An empty request changes nothing; the stored document holds only known keys
        $this->as($this->owner, 'PUT', $this->url(), [])->assertOk()->assertJsonPath('data.pos_settings.mode', 'counter');
        $keys = array_keys($this->branch->fresh()->pos_settings);
        sort($keys);
        $expected = PosSettings::KEYS;
        sort($expected);
        $this->assertSame($expected, $keys);
        $this->assertSame(6, AuditLog::where('action', 'settings.pos_settings_changed')->count());
    }

    public function test_values_are_validated(): void
    {
        $put = fn (array $payload) => $this->as($this->owner, 'PUT', $this->url(), $payload);

        foreach (['12:00', '5:00', '24:00', '04:60', 'abc', '18:30'] as $bad) {
            $put(['business_day_starts_at' => $bad])->assertStatus(422)->assertJsonValidationErrors('business_day_starts_at');
        }
        foreach (['00:00', '04:00', '05:30', '11:59'] as $good) {
            $put(['business_day_starts_at' => $good])->assertOk();
        }

        $put(['pos_settings' => ['mode' => 'kiosk']])->assertStatus(422)->assertJsonValidationErrors('pos_settings.mode');
        $put(['pos_settings' => ['screen_lock_minutes' => 241]])->assertStatus(422)->assertJsonValidationErrors('pos_settings.screen_lock_minutes');
        $put(['pos_settings' => ['screen_lock_minutes' => -1]])->assertStatus(422)->assertJsonValidationErrors('pos_settings.screen_lock_minutes');
        $put(['pos_settings' => ['queue_numbers' => 'maybe']])->assertStatus(422)->assertJsonValidationErrors('pos_settings.queue_numbers');
        $put(['pos_settings' => ['alerts' => ['open_room_hours' => 0]]])->assertStatus(422)->assertJsonValidationErrors('pos_settings.alerts.open_room_hours');
        $put(['pos_settings' => ['alerts' => ['open_room_hours' => 73]]])->assertStatus(422)->assertJsonValidationErrors('pos_settings.alerts.open_room_hours');
        $put(['pos_settings' => ['alerts' => ['void_amount' => -5]]])->assertStatus(422)->assertJsonValidationErrors('pos_settings.alerts.void_amount');
        $put(['pos_settings' => ['alerts' => ['void_amount' => '10.123']]])->assertStatus(422)->assertJsonValidationErrors('pos_settings.alerts.void_amount');
        $put(['pos_settings' => ['alerts' => ['discount_percent' => 101]]])->assertStatus(422)->assertJsonValidationErrors('pos_settings.alerts.discount_percent');
        $put(['pos_settings' => ['alerts' => ['unaccepted_online_minutes' => 121]]])->assertStatus(422)->assertJsonValidationErrors('pos_settings.alerts.unaccepted_online_minutes');
        $put(['pos_settings' => ['holidays' => ['2026-02-30']]])->assertStatus(422)->assertJsonValidationErrors('pos_settings.holidays.0');
        $put(['pos_settings' => ['holidays' => ['01.01.2027']]])->assertStatus(422)->assertJsonValidationErrors('pos_settings.holidays.0');
        $put(['pos_settings' => ['holidays' => array_map(fn (int $day) => Carbon::parse('2027-01-01')->addDays($day)->format('Y-m-d'), range(0, PosSettings::MAX_HOLIDAYS))]])->assertStatus(422)->assertJsonValidationErrors('pos_settings.holidays');
        $put(['pos_settings' => ['booking_slot_minutes' => 29]])->assertStatus(422)->assertJsonValidationErrors('pos_settings.booking_slot_minutes');
        $put(['pos_settings' => ['booking_slot_minutes' => 1441]])->assertStatus(422)->assertJsonValidationErrors('pos_settings.booking_slot_minutes');
        $put(['pos_settings' => ['printing_device_id' => 'not-a-uuid']])->assertStatus(422)->assertJsonValidationErrors('pos_settings.printing_device_id');
        $put(['pos_settings' => 'table'])->assertStatus(422)->assertJsonValidationErrors('pos_settings');

        // Unknown keys are refused, never stored
        $put(['pos_settings' => ['theme' => 'dark']])->assertStatus(422)->assertJsonValidationErrors('pos_settings.theme');
        $put(['pos_settings' => ['alerts' => ['void_count' => 3]]])->assertStatus(422)->assertJsonValidationErrors('pos_settings.alerts.void_count');
        $this->assertNull($this->branch->fresh()->pos_settings);
    }

    public function test_the_printing_till_must_be_a_pos_terminal_of_this_branch(): void
    {
        $put = fn (string $uuid) => $this->as($this->owner, 'PUT', $this->url(), ['pos_settings' => ['printing_device_id' => $uuid]]);

        $put((string) Str::uuid())->assertStatus(422)->assertJsonValidationErrors('pos_settings.printing_device_id');
        $put($this->pairTerminal($this->owner, $this->branch, \App\Enums\DeviceType::KDS)['device']->uuid)->assertStatus(422);
        $put($this->pairTerminal($this->owner, $this->otherBranch)['device']->uuid)->assertStatus(422);
        $foreign = $this->pairTerminal($this->foreignOwner, $this->foreignBranch);
        $put($foreign['device']->uuid)->assertStatus(422);

        $revoked = $this->pairTerminal($this->owner, $this->branch)['device'];
        $revoked->forceFill(['revoked_at' => now()])->save();
        $put($revoked->uuid)->assertStatus(422);

        // A terminal paired to the whole store (no branch) may be any branch's printing till
        $storeWide = $this->pairTerminal($this->owner, null)['device'];
        $put($storeWide->uuid)->assertOk();
    }

    public function test_settings_endpoints_respect_permissions_and_stores(): void
    {
        $this->as($this->manager, 'GET', $this->url())->assertForbidden();
        $this->as($this->manager, 'PUT', $this->url(), ['pos_settings' => ['mode' => 'table']])->assertForbidden();
        $this->as($this->customer, 'GET', $this->url())->assertForbidden();
        $this->as(null, 'GET', $this->url())->assertUnauthorized();

        // Another store's branch: 404 on read and write; nothing changes
        $this->as($this->owner, 'GET', $this->url($this->foreignBranch->id))->assertNotFound();
        $this->as($this->owner, 'PUT', $this->url($this->foreignBranch->id), ['business_day_starts_at' => '04:00'])->assertNotFound();
        $this->as($this->foreignOwner, 'PUT', $this->url(), ['business_day_starts_at' => '04:00'])->assertNotFound();
        $this->assertSame('05:00:00', $this->foreignBranch->fresh()->business_day_starts_at);
        $this->assertSame('05:00:00', $this->branch->fresh()->business_day_starts_at);

        // Each branch keeps its own settings
        $this->as($this->owner, 'PUT', $this->url($this->otherBranch->id), ['business_day_starts_at' => '04:00', 'pos_settings' => ['mode' => 'counter']])->assertOk();
        $this->as($this->owner, 'GET', $this->url())->assertJsonPath('data.business_day_starts_at', '05:00')->assertJsonPath('data.pos_settings.mode', 'hybrid');
    }

    public function test_the_business_date_follows_the_branch_day_start(): void
    {
        $tz = 'Asia/Tashkent';
        // Friday 02:00 in Tashkent is still Thursday's business day, Friday 05:00 is Friday's
        $friday2am = Carbon::parse('2026-10-23 02:00:00', $tz);
        $this->assertSame('2026-10-22', PosSettings::businessDate($friday2am, $tz, '05:00:00'));
        $this->assertSame('2026-10-23', PosSettings::businessDate(Carbon::parse('2026-10-23 05:00:00', $tz), $tz, '05:00'));
        $this->assertSame('2026-10-23', PosSettings::businessDate($friday2am, $tz, '00:00'));
        $this->assertSame('2026-10-23', PosSettings::businessDate($friday2am, $tz, '02:00'));
        $this->assertSame('2026-10-22', PosSettings::businessDate(Carbon::parse('2026-10-23 03:59:00', $tz), $tz, '04:00'));
        // Given in UTC: 21:30 UTC on Thursday is 02:30 in Tashkent on Friday
        $this->assertSame('2026-10-22', PosSettings::businessDate(Carbon::parse('2026-10-22 21:30:00', 'UTC'), $tz, '05:00:00'));
    }

    public function test_a_stored_document_that_does_not_fit_reads_as_the_defaults(): void
    {
        $settings = PosSettings::resolve([
            'mode' => 'kiosk', 'screen_lock_minutes' => 9999, 'queue_numbers' => 'yes',
            'alerts' => ['open_room_hours' => -1, 'void_amount' => 'many', 'discount_percent' => null],
            'holidays' => ['2026-13-01', '2027-03-08', 5], 'theme' => 'dark',
        ]);

        $this->assertSame(PosSettings::defaults()['mode'], $settings['mode']);
        $this->assertSame(5, $settings['screen_lock_minutes']);
        $this->assertFalse($settings['queue_numbers']);
        $this->assertSame(6, $settings['alerts']['open_room_hours']);
        $this->assertSame('200000.00', $settings['alerts']['void_amount']);
        $this->assertNull($settings['alerts']['discount_percent']);
        $this->assertSame(['2027-03-08'], $settings['holidays']);
        $this->assertArrayNotHasKey('theme', $settings);
    }
}
