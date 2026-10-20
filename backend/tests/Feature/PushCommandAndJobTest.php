<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Jobs\SendPushNotification;
use App\Services\Push\PushContent;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class PushCommandAndJobTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use PushFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPush();
        $this->setUpLifecycleStore();
    }

    public function test_push_test_sends_to_every_device_of_a_user_by_id_or_phone(): void
    {
        $this->fakeFirebase();
        $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'phone-token-12345678');
        $this->makeDevice($this->customer, DeviceType::WEB, 'web-token-1234567890');
        $this->makeDevice($this->customer, DeviceType::WEB, null);

        $this->assertSame(0, Artisan::call('push:test', ['target' => (string) $this->customer->id, '--title' => 'Salom']));
        $output = Artisan::output();
        $this->assertSame(2, substr_count($output, ': yuborildi.'));
        $this->assertStringContainsString("push token yo'q", $output);

        $this->assertEqualsCanonicalizing(['phone-token-12345678', 'web-token-1234567890'], $this->fcmTokens());
        $this->assertSame('Salom', $this->fcmRequests()->first()['message']['notification']['title']);

        $this->artisan('push:test', ['target' => $this->customer->phone])->assertSuccessful();
        $this->assertCount(4, $this->fcmRequests());
    }

    public function test_push_test_can_target_one_device_and_never_prints_the_token(): void
    {
        $this->fakeFirebase();
        $device = $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'super-secret-token-xyz');

        $this->artisan('push:test', ['target' => $device->uuid, '--dry-run' => true])
            ->doesntExpectOutputToContain('super-secret-token-xyz')
            ->expectsOutputToContain('...en-xyz')
            ->assertSuccessful();

        $this->assertCount(0, $this->fcmRequests(), 'dry run sends nothing');

        $this->artisan('push:test', ['target' => $device->uuid])->doesntExpectOutputToContain('super-secret-token-xyz')->assertSuccessful();
        $this->assertCount(1, $this->fcmRequests());
    }

    public function test_push_test_reports_fcm_errors_and_unknown_targets(): void
    {
        $this->fakeFirebase($this->fcmError(404, 'UNREGISTERED'));
        $device = $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'dead-token-1234567');

        $this->artisan('push:test', ['target' => $device->uuid])->expectsOutputToContain('invalid_token')->assertFailed();
        $this->assertNull($device->fresh()->push_token);

        $this->artisan('push:test', ['target' => '999999'])->expectsOutputToContain('topilmadi')->assertFailed();
        $this->artisan('push:test', ['target' => '11111111-2222-3333-4444-555555555555'])->assertFailed();
    }

    public function test_push_test_without_credentials_says_how_to_set_them_up(): void
    {
        $this->setUpPush(configured: false);
        Http::fake();
        $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'phone-token-12345678');

        $this->artisan('push:test', ['target' => (string) $this->customer->id])
            ->expectsOutputToContain('FIREBASE_CREDENTIALS')
            ->assertFailed();
        Http::assertNothingSent();
    }

    public function test_the_job_is_released_with_backoff_when_fcm_is_unavailable_and_dropped_when_it_runs_out_of_attempts(): void
    {
        Log::spy();
        $this->fakeFirebase(Http::response(['error' => ['code' => 503, 'status' => 'UNAVAILABLE', 'message' => 'down']], 503));
        $device = $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'phone-token-12345678');

        $run = function (int $attempt) use ($device) {
            $queueJob = Mockery::mock(Job::class);
            $queueJob->shouldReceive('attempts')->andReturn($attempt);
            $job = new SendPushNotification($device->id, new PushContent('A', 'B'), 'order_updates');
            $job->setJob($queueJob);

            return [$job, $queueJob];
        };

        [$job, $queueJob] = $run(1);
        $queueJob->shouldReceive('release')->once()->with(15);
        $job->handle(app(\App\Services\Push\PushSender::class));

        [$job, $queueJob] = $run(2);
        $queueJob->shouldReceive('release')->once()->with(60);
        $job->handle(app(\App\Services\Push\PushSender::class));

        [$job, $queueJob] = $run(4);
        $queueJob->shouldNotReceive('release');
        $job->handle(app(\App\Services\Push\PushSender::class));
        Log::shouldHaveReceived('warning')->once();

        $this->assertNotNull($device->fresh()->push_token, 'an outage does not cost the device its token');
    }

    public function test_the_job_skips_a_device_that_lost_its_token_or_was_revoked_meanwhile(): void
    {
        $this->fakeFirebase();
        $cleared = $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, null);
        $revoked = $this->makeDevice($this->customer, DeviceType::CUSTOMER_MOBILE, 'revoked-token-12345');
        $revoked->forceFill(['revoked_at' => now()])->save();

        foreach ([$cleared->id, $revoked->id, 987654] as $id) {
            (new SendPushNotification($id, new PushContent('A', 'B')))->handle(app(\App\Services\Push\PushSender::class));
        }

        $this->assertCount(0, $this->fcmRequests());
    }
}
