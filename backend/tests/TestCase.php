<?php

namespace Tests;

use App\Domain\Realtime\RevisionPostgresConnection;
use App\Support\Database\OwnerConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AsSchemaOwner;
use Tests\Concerns\UsesOwnerConnection;

abstract class TestCase extends BaseTestCase
{
    /**
     * M4 (CI job tests-app-role: DB_USERNAME is the DML-only role, DB_OWNER_USERNAME the schema owner): RefreshDatabase
     * migrates on the owner connection and the test then runs, in its transaction, as the application role; a test
     * that does DDL itself declares UsesOwnerConnection (class) or #[AsSchemaOwner] (method) and runs as the owner.
     * Without DB_OWNER_USERNAME: unchanged.
     */
    protected function setUpTraits()
    {
        self::reportTenantTransactions();
        $uses = $this->traitsUsedByTest ?? array_flip(class_uses_recursive(static::class));
        if (OwnerConnection::prepare()) {
            if (isset($uses[RefreshDatabase::class]) && !RefreshDatabaseState::$migrated && method_exists($this, 'migrateFreshUsing')) {
                $this->artisan('migrate:fresh', ['--database' => OwnerConnection::NAME] + $this->migrateFreshUsing())->run();
                $this->app[\Illuminate\Contracts\Console\Kernel::class]->setArtisan(null);
                DB::disconnect(OwnerConnection::NAME);
                RefreshDatabaseState::$migrated = true;
            }
            if (isset($uses[UsesOwnerConnection::class]) || (new \ReflectionMethod($this, $this->name()))->getAttributes(AsSchemaOwner::class) !== []) {
                DB::setDefaultConnection(OwnerConnection::NAME);
            }
        }

        return parent::setUpTraits();
    }

    private static bool $reportsTenantTransactions = false;

    /**
     * M4 stage 3 (CI job tests-app-role, which runs the suite with TENANT_RLS=1): when the process ends, the number of
     * transactions that took the tenant role is written to TENANT_RLS_COUNT_FILE, and the job fails unless the suite
     * really ran under row-level security (a run that silently never switched would be green and prove nothing).
     */
    private static function reportTenantTransactions(): void
    {
        $file = (string) getenv('TENANT_RLS_COUNT_FILE');
        if ($file === '' || self::$reportsTenantTransactions) {
            return;
        }
        self::$reportsTenantTransactions = true;
        register_shutdown_function(static fn () => file_put_contents($file, RevisionPostgresConnection::$tenantTransactions . "\n"));
    }
}
