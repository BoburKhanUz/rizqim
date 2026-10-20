<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Every migration can be rolled back (migrate:reset / rollback) and run again. Not inside a test transaction: some
 * migrations build indexes CONCURRENTLY, which PostgreSQL refuses there. The schema is complete again afterwards.
 */
class MigrationsRollbackTest extends TestCase
{
    public function test_every_migration_rolls_back_and_runs_again(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        $this->artisan('migrate:reset', ['--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('stores'));

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasColumn('stores', 'phone'));
        $this->assertTrue(Schema::hasColumn('orders', 'branch_id'));
    }
}
