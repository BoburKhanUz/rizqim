<?php

namespace Tests\Feature\Tenancy;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * M4 stage 2 (docs/scale/M4_DESIGN.md 3.2), from the migrated schema: every Eloquent model whose table has a NOT NULL
 * `store_id` uses BelongsToStore or is an exception here with its reason; no model uses it without such a column; every
 * `withoutGlobalScope(StoreScope...)` in app/ says why on the same or the previous line.
 */
class StoreScopeInventoryTest extends TestCase
{
    use RefreshDatabase;

    /** Models with a NOT NULL store_id that are not scoped, with the reason. */
    public const EXCEPTIONS = [
        \App\Models\StoreCrmProfile::class => 'RIZQIM CRM row about a store, read by the admin panel only and never shown to the store',
        \App\Models\StoreHealthScore::class => 'RIZQIM CRM row about a store, read by the admin panel only and never shown to the store',
        // TODO(M4 stage 2): remove once payments.store_id (agent B) is committed and Payment uses BelongsToStore
        \App\Models\Payment::class => 'payments.store_id is new in stage 2: scoped in a second step, after the column has landed',
    ];

    /** Tables with a NOT NULL store_id and no model (nothing to scope: raw queries, covered by the route suite). */
    public const NO_MODEL = [
        'fiscal_receipt_keys' => 'registry whose purpose is uniqueness across stores (M3 A2): never filtered',
        'pos_bill_print_requests' => 'written and claimed with raw queries by store_id and branch_id of the device (BillPrintInbox)',
    ];

    /**
     * Every Eloquent model class under app/.
     *
     * @return list<class-string<Model>>
     */
    public static function models(): array
    {
        $models = [];
        foreach ((new Finder())->files()->in(app_path())->name('*.php')->contains('/^(final |abstract )?class \w+ extends /m') as $file) {
            $source = $file->getContents();
            if (!preg_match('/^namespace ([^;]+);/m', $source, $ns) || !preg_match('/^(?:final |abstract )?class (\w+) extends/m', $source, $class)) {
                continue;
            }
            $name = $ns[1] . '\\' . $class[1];
            if (class_exists($name) && is_subclass_of($name, Model::class) && !(new \ReflectionClass($name))->isAbstract()) {
                $models[] = $name;
            }
        }
        sort($models);

        return $models;
    }

    /**
     * Tables (partitioned parents included, partitions not) with their `store_id` NOT NULL flag.
     *
     * @return array<string, bool>
     */
    public static function storeIdColumns(): array
    {
        $rows = DB::select("SELECT c.relname, a.attnotnull FROM pg_attribute a JOIN pg_class c ON c.oid = a.attrelid
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = current_schema() AND a.attname = 'store_id' AND NOT a.attisdropped AND c.relkind IN ('r', 'p') AND NOT c.relispartition");

        return collect($rows)->mapWithKeys(fn ($row) => [$row->relname => (bool) $row->attnotnull])->all();
    }

    /**
     * The scoped models (uses BelongsToStore).
     *
     * @return list<class-string<Model>>
     */
    public static function scopedModels(): array
    {
        return array_values(array_filter(self::models(), fn ($model) => in_array(BelongsToStore::class, class_uses_recursive($model), true)));
    }

    public function test_every_model_with_a_not_null_store_id_is_scoped_or_excepted(): void
    {
        $columns = self::storeIdColumns();
        $problems = [];
        $tablesWithModel = [];
        foreach (self::models() as $model) {
            $table = (new $model())->getTable();
            $tablesWithModel[$table] = true;
            $notNull = $columns[$table] ?? false;
            $scoped = in_array(BelongsToStore::class, class_uses_recursive($model), true);
            $excepted = isset(self::EXCEPTIONS[$model]);
            if ($notNull && !$scoped && !$excepted) {
                $problems[] = "{$model} ({$table}.store_id NOT NULL): use BelongsToStore, or list it in EXCEPTIONS with the reason";
            } elseif ($scoped && !$notNull) {
                $problems[] = "{$model} uses BelongsToStore but {$table} has no NOT NULL store_id";
            } elseif ($excepted && ($scoped || !$notNull)) {
                $problems[] = "{$model}: a stale exception";
            }
        }
        foreach ($columns as $table => $notNull) {
            if ($notNull && !isset($tablesWithModel[$table]) && !isset(self::NO_MODEL[$table])) {
                $problems[] = "{$table}: NOT NULL store_id and no model: list it in NO_MODEL with the reason";
            }
        }
        foreach (array_keys(self::NO_MODEL) as $table) {
            if (isset($tablesWithModel[$table]) || !($columns[$table] ?? false)) {
                $problems[] = "{$table}: a stale NO_MODEL entry";
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
        $this->assertGreaterThan(80, count(self::scopedModels()));
    }

    public function test_every_scope_removal_says_why(): void
    {
        $problems = [];
        $found = 0;
        foreach ((new Finder())->files()->in(app_path())->name('*.php') as $file) {
            $lines = explode("\n", $file->getContents());
            foreach ($lines as $i => $line) {
                if (!preg_match('/withoutGlobalScope\(\s*\\\\?(App\\\\Support\\\\Tenancy\\\\)?StoreScope::class/', $line)) {
                    continue;
                }
                $found++;
                $previous = trim($lines[$i - 1] ?? '');
                $commented = str_contains(substr($line, (int) strpos($line, 'withoutGlobalScope')), '//')
                    || str_starts_with($previous, '//') || str_starts_with($previous, '*') || str_starts_with($previous, '/*');
                if (!$commented) {
                    $problems[] = $file->getRelativePathname() . ':' . ($i + 1) . ': say why on this or the previous line';
                }
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
        $this->addToAssertionCount($found);
    }
}
