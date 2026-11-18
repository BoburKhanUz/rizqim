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
 * call in app/ that takes the store scope off a query (SCOPE_REMOVAL) says why on the same or the previous line.
 */
class StoreScopeInventoryTest extends TestCase
{
    use RefreshDatabase;

    /** Models with a NOT NULL store_id that are not scoped, with the reason. */
    public const EXCEPTIONS = [
        \App\Models\StoreCrmProfile::class => 'RIZQIM CRM row about a store, read by the admin panel only and never shown to the store',
        \App\Models\StoreHealthScore::class => 'RIZQIM CRM row about a store, read by the admin panel only and never shown to the store',
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

    /**
     * A call that takes the store scope off a query: the scope named in the call's argument (its class constant, its
     * class name as a string, an instance; the argument may start on a later line), every global scope (plural, also
     * "all but these"), or a query built without them. Not seen: a scope held in a variable. Not listed: ->toBase()
     * applies the global scopes (Eloquent\Builder::toBase = applyScopes()->getQuery()); a base builder from getQuery()
     * or DB::table() never had them (raw queries: the route suite covers them).
     */
    private const SCOPE_REMOVAL = '/\b(?:withoutGlobalScope|newQueryWithoutScope)\s*\([^)]*?\bStoreScope\b|\b(?:withoutGlobalScopes(?:Except)?|newQueryWithoutScopes)\s*\(/';

    /**
     * Every place in a PHP source where the store scope is taken off a query, as [line, says why]: on the line of the
     * call (or, for a call split across lines, any of its lines) or on the line before it.
     *
     * @return list<array{0: int, 1: bool}>
     */
    public static function scopeRemovals(string $source): array
    {
        $lines = explode("\n", $source);
        $found = [];
        preg_match_all(self::SCOPE_REMOVAL, $source, $hits, PREG_OFFSET_CAPTURE);
        foreach ($hits[0] as [$call, $offset]) {
            $first = substr_count($source, "\n", 0, $offset);
            // From the call to the end of its last line
            $text = substr($source, $offset, (int) strcspn($source, "\n", $offset + strlen($call)) + strlen($call));
            $previous = trim($lines[$first - 1] ?? '');
            $found[] = [$first + 1, str_contains($text, '//')
                || str_starts_with($previous, '//') || str_starts_with($previous, '*') || str_starts_with($previous, '/*')];
        }

        return $found;
    }

    public function test_every_scope_removal_says_why(): void
    {
        $problems = [];
        $found = 0;
        foreach ((new Finder())->files()->in(app_path())->name('*.php') as $file) {
            foreach (self::scopeRemovals($file->getContents()) as [$line, $explained]) {
                $found++;
                if (!$explained) {
                    $problems[] = $file->getRelativePathname() . ':' . $line . ': say why on this or the previous line';
                }
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
        $this->addToAssertionCount($found);
    }

    /**
     * The check itself, on sample sources: none of the rarer forms is in app/ today, so app/ cannot prove it sees them.
     */
    public function test_the_say_why_check_sees_every_way_to_take_the_store_scope_off(): void
    {
        $unexplained = [
            'by class' => '$q = Order::query()->withoutGlobalScope(StoreScope::class)->get();',
            'by qualified class' => '$q = Order::query()->withoutGlobalScope(\App\Support\Tenancy\StoreScope::class)->get();',
            'by an instance' => '$q = Order::query()->withoutGlobalScope(new StoreScope())->get();',
            'by a string name' => '$q = Order::query()->withoutGlobalScope(\'App\\\\Support\\\\Tenancy\\\\StoreScope\')->get();',
            'by a double-quoted string name' => '$q = Order::query()->withoutGlobalScope("\\\\App\\\\Support\\\\Tenancy\\\\StoreScope")->get();',
            'every scope' => '$q = Order::query()->withoutGlobalScopes()->get();',
            'every scope but some' => '$q = Order::query()->withoutGlobalScopesExcept([SoftDeletingScope::class])->get();',
            'a new query without scopes' => '$q = (new Order())->newQueryWithoutScopes()->get();',
            'a new query without the scope' => '$q = (new Order())->newQueryWithoutScope(StoreScope::class)->get();',
            'a new query without the scope, by string' => '$q = (new Order())->newQueryWithoutScope(\'App\Support\Tenancy\StoreScope\')->get();',
            'split across lines' => "\$q = Order::query()\n    ->withoutGlobalScope(\n        StoreScope::class\n    )\n    ->get();",
            'split across lines, by string' => "\$q = (new Order())->newQueryWithoutScope(\n    'App\\\\Support\\\\Tenancy\\\\StoreScope',\n)->get();",
            'a comment two lines above is too far' => "// The pairing code is unique across stores\n\n\$q = Order::query()->withoutGlobalScope(StoreScope::class)->get();",
        ];
        foreach ($unexplained as $what => $source) {
            $at = substr_count(explode('ithout', $source)[0], "\n") + 2;
            $this->assertSame([[$at, false]], self::scopeRemovals("<?php\n" . $source . "\n"), $what);
        }

        $explained = [
            'on the same line' => '$q = Order::query()->withoutGlobalScope(StoreScope::class)->get(); // unique across stores',
            'on the previous line' => "// Unique across stores\n\$q = (new Order())->newQueryWithoutScope(StoreScope::class)->get();",
            'in a docblock above' => "/**\n * Unique across stores\n */\n\$q = Order::query()->withoutGlobalScopesExcept([])->get();",
            'inside a split call' => "\$q = Order::query()\n    ->withoutGlobalScope(\n        StoreScope::class, // unique across stores\n    )\n    ->get();",
        ];
        foreach ($explained as $what => $source) {
            $this->assertSame([true], array_column(self::scopeRemovals("<?php\n" . $source . "\n"), 1), $what);
        }

        $other = [
            'another scope' => '$q = OrderItem::query()->withoutGlobalScope(self::COMBO_PARTS_SCOPE)->get();',
            'another scope by class' => '$q = Order::query()->withoutGlobalScope(SoftDeletingScope::class)->get();',
            'another scope, the store scope later in the chain' => '$q = Order::query()->withoutGlobalScope(SoftDeletingScope::class)->withGlobalScope(\'store\', new StoreScope())->get();',
            'no removal' => '$q = (new Order())->newModelQuery()->withoutTrashed()->get();',
            'prose without a call' => '// Saving goes through newQueryWithoutScopes): the scope filters reads only',
        ];
        foreach ($other as $what => $source) {
            $this->assertSame([], self::scopeRemovals("<?php\n" . $source . "\n"), $what);
        }
    }
}
