<?php

namespace Tests\Feature\PosFastFood;

use App\Actions\Order\CreateOrderAction;
use App\Domain\Pos\Orders\PosOrderService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Review M2 (LR-4): a deadlock or serialization failure that outlived its retries reaches neither the Mini App nor the
 * till as SQL text or a bare 500: both get the retryable 503 `server_busy` with Retry-After. Another database error on
 * POST /orders is logged and answered with a plain message.
 */
class ServerBusyResponseTest extends TestCase
{
    use ComboFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCombo();
    }

    private static function failure(string $sqlState, string $message): QueryException
    {
        $pdo = new \PDOException("SQLSTATE[{$sqlState}]: {$message}");
        (new \ReflectionProperty(\Exception::class, 'code'))->setValue($pdo, $sqlState); // as PDO sets it: the SQLSTATE

        return new QueryException('pgsql', 'select * from "product_variants" where "uuid" in (?) for no key update', ['x'], $pdo);
    }

    private function assertServerBusy(TestResponse $response): void
    {
        $response->assertStatus(503)
            ->assertHeader('Retry-After', '2')
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'server_busy')
            ->assertJsonPath('data.reason', 'server_busy');
        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
        $this->assertStringNotContainsString('product_variants', (string) $response->getContent());
    }

    public function test_a_deadlock_on_a_mini_app_order_is_a_retryable_server_busy_without_sql(): void
    {
        // One mock for the three requests (the route keeps its controller and so the action it was given)
        $failures = [
            self::failure('40P01', 'Deadlock detected: 7 ERROR:  deadlock detected'),
            self::failure('40001', 'Serialization failure: 7 ERROR:  could not serialize access'),
            self::failure('23505', 'Unique violation: 7 ERROR:  duplicate key'),
        ];
        $this->mock(CreateOrderAction::class)->shouldReceive('execute')->andReturnUsing(function () use (&$failures) {
            throw array_shift($failures);
        });
        $this->assertServerBusy($this->order([['variant_id' => $this->pepsi->uuid, 'quantity' => 1]]));
        $this->assertServerBusy($this->order([['variant_id' => $this->pepsi->uuid, 'quantity' => 1]]));

        // Any other database error: a plain message, never the SQL
        $response = $this->order([['variant_id' => $this->pepsi->uuid, 'quantity' => 1]])->assertStatus(500)->assertJsonPath('success', false);
        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
    }

    public function test_a_deadlock_on_a_till_sale_is_a_retryable_server_busy_without_sql(): void
    {
        $this->mock(PosOrderService::class)->shouldReceive('openOrder')->andThrow(self::failure('40P01', 'Deadlock detected: 7 ERROR:  deadlock detected'));

        $this->assertServerBusy($this->counterOrder([['variant_id' => $this->pepsi->uuid, 'quantity' => 1]]));
    }
}
