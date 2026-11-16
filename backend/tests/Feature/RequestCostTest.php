<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Enums\UserRole;
use App\Models\Device;
use App\Providers\ApiDocsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * M2-4 (PHP cost per request): the per-request work that was removed must not change who gets in or what is shown.
 * A terminal's own token reuses the device row its guard just read instead of a second query, and still dies with
 * the device; the floor map no longer asks the catalogue whether the bookings tables exist; Scramble is only
 * registered where documents are built; the OPcache preload script runs cleanly and can be switched off.
 */
class RequestCostTest extends TestCase
{
    use PosHallFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosHall();
    }

    /** @return list<string> */
    private function queriesDuring(callable $request): array
    {
        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = $query->sql;
        });
        $request();

        return $sql;
    }

    public function test_a_terminal_token_is_checked_against_the_device_row_already_read(): void
    {
        $terminal = $this->pairTerminal($this->owner, $this->branch, DeviceType::POS);

        $sql = $this->queriesDuring(fn () => $this->bearerJson('GET', '/api/v1/terminal', $terminal['token'])->assertOk());
        $this->assertSame([], array_values(array_filter($sql, fn ($q) => str_contains($q, 'exists') && str_contains($q, '"devices"'))),
            'the terminal token must not query its own device a second time');

        // Revoked while its token row still exists (e.g. revoked from another session): refused at once
        Device::whereKey($terminal['device']->id)->update(['revoked_at' => now()]);
        $this->app['auth']->forgetGuards();
        $this->bearerJson('GET', '/api/v1/terminal', $terminal['token'])->assertUnauthorized();
        $this->bearerJson('GET', '/api/v1/pos/sync/status', $terminal['token'])->assertUnauthorized();
    }

    public function test_a_staff_session_on_a_revoked_terminal_still_checks_the_device(): void
    {
        $terminal = $this->pairTerminal($this->owner, $this->branch, DeviceType::KDS);
        $cook = $this->makeStaff(UserRole::COOK, $this->branch);
        $session = $this->pinSession($terminal, $cook);
        $this->app['auth']->forgetGuards();

        $sql = $this->queriesDuring(fn () => $this->bearerJson('GET', '/api/v1/staff/kitchen/tickets', $session)->assertOk());
        $this->assertNotEmpty(array_filter($sql, fn ($q) => str_contains($q, 'exists') && str_contains($q, '"devices"') && str_contains($q, 'revoked_at')),
            'a user token bound to a device keeps its device check');

        Device::whereKey($terminal['device']->id)->update(['revoked_at' => now()]);
        $this->app['auth']->forgetGuards();
        $this->bearerJson('GET', '/api/v1/staff/kitchen/tickets', $session)->assertUnauthorized();
    }

    public function test_the_floor_map_asks_no_catalogue_question_and_still_shows_bookings(): void
    {
        DB::table('bookings')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'dining_table_id' => $this->vipRoom->id,
            'starts_at' => now()->addMinutes(30),
            'ends_at' => now()->addHours(3),
            'guests_planned' => 9,
            'contact_name' => 'Dilnoza',
            'status' => 'requested',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = null;
        $sql = $this->queriesDuring(function () use (&$response) {
            $response = $this->pos($this->cashier, 'GET', '/pos/floor')->assertOk();
        });

        $this->assertSame([], array_values(array_filter($sql, fn ($q) => str_contains($q, 'pg_class') || str_contains($q, 'information_schema'))));
        $places = collect($response->json('data.halls'))->flatMap(fn ($hall) => $hall['tables'])->keyBy('name');
        $this->assertSame('reserved_soon', $places['VIP-1']['status']);
        $this->assertSame(9, $places['VIP-1']['reservation']['guests']);
    }

    public function test_scramble_is_registered_only_for_the_console_and_the_docs_routes(): void
    {
        $app = $this->app;
        $console = new \ReflectionProperty($app, 'isRunningInConsole');
        $wasConsole = $console->getValue($app);
        $request = $app['request'];
        $servesDocs = function (bool $inConsole, string $path) use ($app, $console): bool {
            $console->setValue($app, $inConsole);
            $app->instance('request', Request::create($path));

            return (fn () => $this->servesDocs())->call(new ApiDocsServiceProvider($app));
        };

        $this->assertTrue($servesDocs(true, '/api/v1/pos/floor'));
        $this->assertTrue($servesDocs(false, '/docs/api'));
        $this->assertTrue($servesDocs(false, '/docs/api.json'));
        $this->assertFalse($servesDocs(false, '/api/v1/pos/floor'));
        $this->assertFalse($servesDocs(false, '/docs/api/extra'));
        $console->setValue($app, $wasConsole);
        $app->instance('request', $request);
        // In this (console) process it is registered: the docs routes and api-spec:export keep working
        $this->assertTrue($this->app->providerIsLoaded(\Dedoc\Scramble\ScrambleServiceProvider::class));
    }

    public function test_the_preload_script_declares_the_listed_classes_and_can_be_switched_off(): void
    {
        if (!extension_loaded('Zend OPcache') || !function_exists('posix_geteuid')) {
            $this->markTestSkipped('needs OPcache and posix');
        }

        $user = posix_getpwuid(posix_geteuid())['name'] ?? 'root';
        $run = function (array $env) use ($user): Process {
            $process = new Process([PHP_BINARY, '-d', 'opcache.enable_cli=1',
                '-d', 'opcache.validate_timestamps=0', '-d', 'opcache.preload='.base_path('docker/preload.php'), '-d', 'opcache.preload_user='.$user,
                '-r', 'echo class_exists("Illuminate\\\\Foundation\\\\Application", false) ? "preloaded" : "not-preloaded";'], base_path(), $env);
            $process->run();

            return $process;
        };

        $on = $run(['PHP_PRELOAD' => '1']);
        $this->assertSame(0, $on->getExitCode(), $on->getErrorOutput());
        $this->assertSame('preloaded', $on->getOutput());
        $this->assertMatchesRegularExpression('/preload: \d{3,} classes preloaded/', $on->getErrorOutput());
        $this->assertStringNotContainsString('preload: skipped', $on->getErrorOutput());

        $off = $run(['PHP_PRELOAD' => '0']);
        $this->assertSame(0, $off->getExitCode(), $off->getErrorOutput());
        $this->assertSame('not-preloaded', $off->getOutput());
    }
}
