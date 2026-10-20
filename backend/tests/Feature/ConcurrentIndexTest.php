<?php

namespace Tests\Feature;

use App\Support\ConcurrentIndex;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A failed CREATE INDEX CONCURRENTLY leaves an INVALID index that IF NOT EXISTS would skip forever; running
 * ConcurrentIndex::create() again rebuilds it. Not inside a test transaction: PostgreSQL refuses CONCURRENTLY there.
 */
class ConcurrentIndexTest extends TestCase
{
    private const TABLE = 'concurrent_index_probe';

    private const INDEX = 'concurrent_index_probe_v_index';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists(self::TABLE);
        Schema::create(self::TABLE, fn ($table) => $table->integer('v'));
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists(self::TABLE);

        parent::tearDown();
    }

    public function test_an_invalid_index_left_by_a_failed_build_is_rebuilt(): void
    {
        // A unique build over duplicates fails half way, exactly like a deadlock or a cancelled deploy would
        DB::table(self::TABLE)->insert([['v' => 1], ['v' => 1]]);
        try {
            DB::statement(sprintf('CREATE UNIQUE INDEX CONCURRENTLY %s ON %s (v)', self::INDEX, self::TABLE));
            $this->fail('The unique build over duplicates should have failed.');
        } catch (QueryException) {
        }
        $this->assertTrue(ConcurrentIndex::isInvalid(self::INDEX));

        ConcurrentIndex::create(self::INDEX, self::TABLE, ['v']);

        $this->assertFalse(ConcurrentIndex::isInvalid(self::INDEX));
        $this->assertTrue(Schema::hasIndex(self::TABLE, self::INDEX));

        // A valid index is left as it is
        ConcurrentIndex::create(self::INDEX, self::TABLE, ['v']);
        $this->assertTrue(Schema::hasIndex(self::TABLE, self::INDEX));

        ConcurrentIndex::drop(self::INDEX, self::TABLE);
        $this->assertFalse(Schema::hasIndex(self::TABLE, self::INDEX));
    }
}
