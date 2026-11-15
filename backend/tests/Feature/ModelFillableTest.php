<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\FiscalReceipt;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\OrderBillItem;
use App\Models\OrderChargeWaiver;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PosApproval;
use App\Models\PosQrPayment;
use App\Models\PosRefund;
use App\Models\PosShift;
use App\Models\PosSyncOperation;
use App\Models\PosTip;
use App\Models\StaffWorkSession;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Wave M1: the hot models went from `$guarded = ['id']` to an explicit `$fillable`, so Eloquent no longer reads the
 * table's column list once per model class per request. With `$guarded = ['id']` every column but id was mass
 * assignable (and nothing else: a key that is not a column, a set mutator or a class cast was dropped). A column
 * missing from `$fillable` would now be dropped silently, which on these models is a money bug, so:
 * - `$fillable` must be exactly the table's columns minus the deny-list (the old `$guarded`);
 * - filling every one of them must keep every one (nothing dropped on the way);
 * - mass assignment must not read the schema any more.
 * A migration that adds a column to one of these tables fails here until the column is added to the model.
 */
class ModelFillableTest extends TestCase
{
    use RefreshDatabase;

    /** Columns that stay out of mass assignment, per model: what the old `$guarded` listed. */
    private const DENIED = ['id'];

    /**
     * @return array<string, array{class-string<Model>}>
     */
    public static function models(): array
    {
        $models = [
            Order::class, OrderItem::class, OrderBill::class, OrderBillItem::class, Payment::class, FiscalReceipt::class,
            IdempotencyKey::class, PosShift::class, OrderStatusHistory::class, PosSyncOperation::class,
            CashMovement::class, PosTip::class, PosRefund::class, OrderChargeWaiver::class, PosApproval::class,
            StaffWorkSession::class, PosQrPayment::class,
        ];

        return collect($models)->mapWithKeys(fn (string $class) => [class_basename($class) => [$class]])->all();
    }

    /**
     * @param  class-string<Model>  $class
     */
    #[DataProvider('models')]
    public function test_fillable_is_every_column_but_the_denied_ones(string $class): void
    {
        $model = new $class();
        $columns = Schema::getColumnListing($model->getTable());
        $expected = array_values(array_diff($columns, self::DENIED));

        $this->assertSame(['*'], $model->getGuarded(), "{$class} must not use \$guarded next to \$fillable");
        $missing = array_diff($expected, $model->getFillable());
        $extra = array_diff($model->getFillable(), $expected);
        $this->assertSame([], array_values($missing), "{$class}: columns missing from \$fillable (mass assignment would drop them)");
        $this->assertSame([], array_values($extra), "{$class}: \$fillable lists what is not a column of {$model->getTable()}");
    }

    /**
     * @param  class-string<Model>  $class
     */
    #[DataProvider('models')]
    public function test_every_previously_assignable_column_is_kept_and_no_schema_is_read(string $class): void
    {
        $model = new $class();
        $keys = array_values(array_diff(Schema::getColumnListing($model->getTable()), self::DENIED));
        $this->assertNotEmpty($keys);

        // Raw values: what is checked is that the key survives fill(), not what its cast makes of it
        $model::preventSilentlyDiscardingAttributes();
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $model->fill(array_fill_keys($keys, null));
        } finally {
            DB::disableQueryLog();
            Model::preventSilentlyDiscardingAttributes(false);
        }

        $this->assertSame([], DB::getQueryLog(), "{$class}: mass assignment read the database");
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $model->getAttributes(), "{$class}: {$key} was dropped by mass assignment");
        }

        // The denied column (id) stays out, as it did under $guarded
        $denied = new $class();
        $denied->fill(['id' => 123]);
        $this->assertArrayNotHasKey('id', $denied->getAttributes());
    }
}
