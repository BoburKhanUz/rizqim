<?php

namespace Tests\Feature;

use App\Support\Database\OwnerConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithProcessEnv;
use Tests\TestCase;

/**
 * M4 (docs/scale/M4_DESIGN.md section 6, stage 1 acceptance): app:check-production refuses an application connection
 * that is a superuser or owns the tables once the roles are split, warns only on an install still on one role, and
 * refuses a config cache holding the owner's password. Works with one role (local, the normal CI job) and with split
 * roles (CI job tests-app-role): the "owner" below is DB_OWNER_USERNAME when set, else the one role itself.
 */
class CheckProductionDatabaseRoleTest extends TestCase
{
    use RefreshDatabase;
    use WithProcessEnv;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.env' => 'staging', 'app.debug' => false, 'telegram.allow_mock_auth' => false, 'services.telegram.webhook_secret' => 'valid_secret-1',
            'services.telegram.webapp_url' => 'https://staging.rizqim.example/', 'app.key' => 'base64:' . base64_encode(str_repeat('k', 32)),
            'broadcasting.default' => 'null']);
    }

    /**
     * The command's default connection becomes a separate connection logged in as the table owner.
     */
    private function connectAsOwner(): string
    {
        $base = config('database.connections.pgsql');
        $owner = OwnerConnection::prepare()
            ? ['username' => config('database.connections.' . OwnerConnection::NAME . '.username'), 'password' => config('database.connections.' . OwnerConnection::NAME . '.password')]
            : [];
        config(['database.connections.m4_owner_probe' => array_merge($base, $owner, ['host' => config('database.connections.pgsql_owner.host'), 'port' => config('database.connections.pgsql_owner.port')])]);
        DB::setDefaultConnection('m4_owner_probe');

        return (string) DB::selectOne('SELECT current_user AS u')->u;
    }

    protected function tearDown(): void
    {
        DB::disconnect('m4_owner_probe');
        parent::tearDown();
    }

    public function test_an_application_role_that_owns_the_tables_is_refused_once_the_roles_are_split(): void
    {
        $owner = $this->connectAsOwner();

        $this->withEnv(['DB_OWNER_USERNAME' => OwnerConnection::username() ?? $owner], function () use ($owner) {
            $this->artisan('app:check-production')
                ->expectsOutputToContain("Ilova bazaga {$owner} roli bilan ulanmoqda (jadvallar egasi)")
                ->assertFailed();
        });
    }

    public function test_an_install_still_on_one_role_only_gets_a_warning(): void
    {
        $owner = $this->connectAsOwner();

        $this->withEnv(['DB_OWNER_USERNAME' => null, 'DB_OWNER_PASSWORD' => null], function () use ($owner) {
            $this->artisan('app:check-production')
                ->expectsOutputToContain("OGOHLANTIRISH: ilova bazaga {$owner} roli bilan ulanmoqda (jadvallar egasi)")
                ->assertSuccessful();
        });
    }

    public function test_the_owner_password_in_the_config_cache_is_refused(): void
    {
        $cache = tempnam(sys_get_temp_dir(), 'm4cfg');
        $password = 'm4-owner-' . bin2hex(random_bytes(6));
        try {
            file_put_contents($cache, '<?php return ' . var_export(['database' => ['connections' => ['pgsql' => ['password' => $password]]]], true) . ';');
            $this->withEnv(['APP_CONFIG_CACHE' => $cache, 'DB_OWNER_PASSWORD' => $password], function () {
                $this->artisan('app:check-production')->expectsOutputToContain('bootstrap/cache/config.php ga tushgan')->assertFailed();
            });

            // A cache without it passes that line
            file_put_contents($cache, '<?php return [];');
            $this->withEnv(['APP_CONFIG_CACHE' => $cache, 'DB_OWNER_PASSWORD' => $password], function () {
                $this->artisan('app:check-production')->doesntExpectOutputToContain('bootstrap/cache/config.php ga tushgan');
            });
        } finally {
            @unlink($cache);
        }
    }
}
