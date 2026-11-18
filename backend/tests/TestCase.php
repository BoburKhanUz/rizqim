<?php

namespace Tests;

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
}
