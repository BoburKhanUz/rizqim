<?php

namespace Tests\Feature\PaymentStore;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * M4 stage 2: what the APPLICATION sends in each INSERT INTO payments (column => bound value), read from the query log.
 * The database trigger would fill or correct payments.store_id anyway, so the stored row alone cannot show that a
 * creation site sets the column itself.
 */
trait CapturesPaymentInserts
{
    /**
     * @return list<array<string, mixed>>
     */
    protected function paymentInserts(callable $callback): array
    {
        $inserts = [];
        DB::listen(function (QueryExecuted $query) use (&$inserts) {
            if (preg_match('/^insert into "payments" \(([^)]*)\) values \((.*)\)/is', $query->sql, $match) !== 1) {
                return;
            }
            $columns = array_map(fn (string $column) => trim($column, ' "'), explode(',', $match[1]));
            $inserts[] = array_combine($columns, array_slice($query->bindings, 0, count($columns)));
        });
        $callback();

        return $inserts;
    }

    /**
     * Every insert carried store_id = $storeId, and the stored rows hold it.
     *
     * @param  list<array<string, mixed>>  $inserts
     */
    protected function assertInsertsCarryStore(array $inserts, int $storeId, int $expected = 1): void
    {
        $this->assertCount($expected, $inserts, 'payment inserts seen');
        foreach ($inserts as $insert) {
            $this->assertArrayHasKey('store_id', $insert, 'the creation site does not set payments.store_id itself');
            $this->assertSame($storeId, (int) $insert['store_id']);
        }
        $this->assertSame(0, DB::table('payments')->where(fn ($query) => $query->whereNull('store_id')->orWhere('store_id', '!=', $storeId))
            ->where(fn ($query) => $query->whereIn('order_id', DB::table('orders')->where('store_id', $storeId)->select('id')))->count());
    }
}
