<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Enums\UserRole;
use App\Models\Device;
use App\Models\Store;
use App\Models\User;
use App\Services\Auth\StaffPhoneLoginService;
use Database\Seeders\E2EStackSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * P2-31: the data of the cross-app end-to-end test (scripts/e2e-stack.sh) is what the test needs, and the seeder cannot
 * run anywhere but a test environment.
 */
class E2EStackSeederTest extends TestCase
{
    use RefreshDatabase;

    private string $seedFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFile = sys_get_temp_dir() . '/e2e-seed-' . uniqid() . '.json';
        putenv("E2E_SEED_FILE={$this->seedFile}");
    }

    protected function tearDown(): void
    {
        putenv('E2E_SEED_FILE');
        @unlink($this->seedFile);
        parent::tearDown();
    }

    public function test_it_seeds_a_store_its_staff_a_product_and_a_paired_kitchen_terminal(): void
    {
        $this->seed(E2EStackSeeder::class);

        $seed = json_decode((string) file_get_contents($this->seedFile), true);
        $store = Store::findOrFail($seed['store']['id']);
        $this->assertSame(1, $store->branches()->count());

        $owner = User::findOrFail($seed['owner']['id']);
        $cook = User::findOrFail($seed['cook']['id']);
        $this->assertSame(UserRole::OWNER, $owner->role);
        $this->assertSame(UserRole::COOK, $cook->role);
        $this->assertSame($store->id, $cook->store_id);
        $this->assertTrue($cook->hasPin());

        // The owner and the manager sign in by SMS code (a login phone); the customer is not staff
        $login = app(StaffPhoneLoginService::class);
        $this->assertCount(1, $login->accountsFor($seed['owner']['phone']));
        $this->assertCount(1, $login->accountsFor($seed['manager']['phone']));
        $this->assertCount(0, $login->accountsFor($seed['customer']['phone']));

        $this->assertCount(2, $seed['products']);
        $terminal = Device::where('uuid', $seed['terminal']['uuid'])->firstOrFail();
        $this->assertSame(DeviceType::KDS, $terminal->type);
        $this->assertSame($seed['branch']['id'], $terminal->branch_id);

        // The terminal token works: it lists the people who can sign in at this terminal
        $this->withToken($seed['terminal']['token'])->getJson('/api/v1/terminal/staff')->assertOk();
    }

    public function test_it_refuses_to_run_in_production_and_in_staging(): void
    {
        foreach (['production', 'staging'] as $environment) {
            $this->app['env'] = $environment;

            try {
                // Directly: `db:seed` itself asks for confirmation in production
                app(E2EStackSeeder::class)->run();
                $this->fail("The seeder ran in {$environment}.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('test databases only', $e->getMessage());
            }
        }

        $this->app['env'] = 'testing';
        $this->assertSame(0, Store::count());
        $this->assertFileDoesNotExist($this->seedFile);
    }
}
