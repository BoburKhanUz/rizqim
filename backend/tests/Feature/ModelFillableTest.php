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
 * assignable, and so was any key with a set mutator (`setFooAttribute`, an Attribute with a setter) or a class cast
 * (an enum or a caster class), even when it is not a column; any other key was dropped. A column missing from
 * `$fillable` would now be dropped silently, which on these models is a money bug, so:
 * - `$fillable` must be exactly the table's columns minus the deny-list (the old `$guarded`);
 * - none of these models has a set-mutator or class-cast key that is not a column (one would have passed `$guarded`
 *   and is not in `$fillable`), so the two lists allow the same keys;
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
     * Under `$guarded` a set-mutator or class-cast key passed mass assignment even when it is not a column; under
     * `$fillable` it does not. No such key, so `$fillable` = the columns allows what `$guarded` allowed.
     *
     * @param  class-string<Model>  $class
     */
    #[DataProvider('models')]
    public function test_no_set_mutator_or_class_cast_key_outside_the_columns(string $class): void
    {
        $model = new $class();
        $columns = Schema::getColumnListing($model->getTable());

        $keys = array_keys($model->getCasts());
        foreach ((new \ReflectionClass($model))->getMethods() as $method) {
            // The framework's own helpers (setClassCastableAttribute, ...) are no mutators
            if (str_starts_with($method->getDeclaringClass()->getName(), 'Illuminate\\')) {
                continue;
            }
            if (preg_match('/^set(.+)Attribute$/', $method->getName(), $match) === 1) {
                $keys[] = \Illuminate\Support\Str::snake($match[1]);
            } elseif ((string) $method->getReturnType() === \Illuminate\Database\Eloquent\Casts\Attribute::class) {
                $keys[] = \Illuminate\Support\Str::snake($method->getName());
            }
        }

        $isClassCastable = \Closure::bind(fn (string $key) => $this->isClassCastable($key), $model, $model);
        $passedGuarded = array_filter(array_unique($keys), fn (string $key) => $model->hasSetMutator($key) || $model->hasAttributeSetMutator($key) || $isClassCastable($key));
        $this->assertSame([], array_values(array_diff($passedGuarded, $columns)), "{$class}: a set-mutator or class-cast key that is not a column passed \$guarded but is not in \$fillable");
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
