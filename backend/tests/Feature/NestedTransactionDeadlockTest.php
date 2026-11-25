<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Database\DeadlockException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Review H1: Laravel answers a deadlock or serialization failure (SQLSTATE 40P01 / 40001) inside a nested
 * DB::transaction() by throwing a DeadlockException WITHOUT rolling back to that level's savepoint (MySQL rolls the
 * whole transaction back on a deadlock; PostgreSQL does not). PostgreSQL then leaves the caller's transaction aborted:
 * a caller that catches the exception and goes on (SettlementRecoveryService::book) fails at its next statement, or —
 * with nothing more to say — "commits" a transaction PostgreSQL has rolled back. RevisionPostgresConnection rolls back
 * to the savepoint first, so the caller's transaction stays usable and keeps what it did outside the savepoint.
 *
 * The deadlock is a real one as PostgreSQL reports it: RAISE ... USING ERRCODE '40P01' with PostgreSQL's own message.
 */
class NestedTransactionDeadlockTest extends TestCase
{
    use RefreshDatabase;

    public static function raiseDeadlock(): void
    {
        DB::statement("DO \$\$ BEGIN RAISE EXCEPTION 'deadlock detected' USING ERRCODE = '40P01'; END \$\$");
    }

    private function category(string $name): Category
    {
        return Category::create(['name' => $name, 'slug' => Str::slug($name) . '-' . Str::random(5), 'is_active' => true]);
    }

    public function test_a_deadlock_in_a_nested_transaction_rolls_back_only_its_savepoint(): void
    {
        $caught = null;
        $ids = DB::transaction(function () use (&$caught) {
            $before = $this->category('Oldin');
            try {
                DB::transaction(function () {
                    $this->category('Ichida');
                    self::raiseDeadlock();
                });
            } catch (DeadlockException $e) {
                $caught = $e;
            }
            // The caller's transaction is still usable
            $after = $this->category('Keyin');

            return [$before->id, $after->id];
        });

        $this->assertInstanceOf(DeadlockException::class, $caught);
        $this->assertStringContainsString('deadlock detected', $caught->getMessage());
        $this->assertSame('40P01', (string) $caught->getPrevious()?->getCode());
        $this->assertSame(['Keyin', 'Oldin'], Category::whereIn('name', ['Oldin', 'Ichida', 'Keyin'])->orderBy('name')->pluck('name')->all());
        $this->assertSame($ids, Category::whereIn('id', $ids)->orderBy('id')->pluck('id')->all());
    }

    public function test_a_deadlock_deeper_down_is_rolled_back_level_by_level(): void
    {
        DB::transaction(function () {
            $this->category('Tashqi');
            try {
                DB::transaction(function () {
                    $this->category('Orta');
                    DB::transaction(function () {
                        $this->category('Ichki');
                        self::raiseDeadlock();
                    });
                });
            } catch (DeadlockException) {
                // both inner levels are gone, the outer one goes on
            }
            $this->category('Oxirgi');
        });

        $this->assertSame(['Oxirgi', 'Tashqi'], Category::whereIn('name', ['Tashqi', 'Orta', 'Ichki', 'Oxirgi'])->orderBy('name')->pluck('name')->all());
        $this->assertSame(1, DB::transactionLevel()); // RefreshDatabase's own
    }
}
