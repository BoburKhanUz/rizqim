<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Pdo\Pgsql;
use Tests\TestCase;

/**
 * The connection mode docker-compose.yml ships for php-fpm (DB_POOLER=pgbouncer, config/database.php): PgBouncer in
 * transaction mode with PDO's PGSQL_ATTR_DISABLE_PREPARES. Every statement goes out as one PQexecParams call (unnamed
 * statement), so a pooler may hand consecutive statements to different server connections, and parameters are still
 * typed by the server the way native prepares type them. These tests run the bindings the app relies on through a
 * connection with exactly that PDO setting. With DB_POOLER=pgbouncer in the environment (the full suite was run that
 * way, docs/OPERATIONS.md) the connection really goes through PgBouncer; otherwise straight to Postgres.
 */
class DatabaseConnectionPoolingTest extends TestCase
{
    use RefreshDatabase;

    private const CONNECTION = 'pgsql_pooled_mode';

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Postgres only');
        }

        $base = config('database.connections.pgsql');
        config(['database.connections.' . self::CONNECTION => array_merge($base, [
            'options' => [self::disablePreparesAttribute() => true] + ($base['options'] ?? []),
        ])]);
    }

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);

        parent::tearDown();
    }

    public function test_the_pooled_mode_is_what_the_config_ships_for_db_pooler_pgbouncer(): void
    {
        $pooled = $this->configWith(['DB_POOLER' => 'pgbouncer', 'DB_POOL_HOST' => 'pgbouncer', 'DB_POOL_PORT' => '6432', 'DB_HOST' => 'postgres', 'DB_PORT' => '5432']);
        $this->assertSame('pgbouncer', $pooled['host']);
        $this->assertSame('6432', $pooled['port']);
        $this->assertSame('pgbouncer', $pooled['pooler']);
        $this->assertTrue($pooled['options'][self::disablePreparesAttribute()], 'pooled mode must never create named prepared statements');

        // DB_DISABLE_PREPARES=false cannot switch it back on under the pooler
        $forced = $this->configWith(['DB_POOLER' => 'pgbouncer', 'DB_DISABLE_PREPARES' => 'false']);
        $this->assertTrue($forced['options'][self::disablePreparesAttribute()]);

        // Without DB_POOLER (any deployment that does not set it): direct to DB_HOST with native prepares, as before
        $direct = $this->configWith(['DB_POOLER' => null, 'DB_HOST' => 'postgres', 'DB_PORT' => '5432']);
        $this->assertSame('postgres', $direct['host']);
        $this->assertSame('5432', $direct['port']);
        $this->assertSame('direct', $direct['pooler']);
        $this->assertFalse($direct['options'][self::disablePreparesAttribute()]);
    }

    public function test_no_named_prepared_statement_is_left_on_the_server(): void
    {
        $db = DB::connection(self::CONNECTION);
        $db->beginTransaction();

        try {
            // A statement object still alive keeps a native prepared statement (pdo_stmt_N) on the server
            $statement = $db->getPdo()->prepare('select ?::int + 1');
            $statement->execute([41]);
            $this->assertSame(42, (int) $statement->fetchColumn());
            $db->select('select id from users where id = ?', [1]);

            // Same transaction = same server connection, also under PgBouncer
            $this->assertSame(0, (int) $db->scalar('select count(*) from pg_prepared_statements'));
        } finally {
            $db->rollBack();
        }
    }

    public function test_representative_bindings_round_trip_in_the_pooled_mode(): void
    {
        $db = DB::connection(self::CONNECTION);
        $db->beginTransaction();

        try {
            // A real table, created and dropped by the rollback (a temp table is session state and is not allowed
            // under transaction pooling). The column types are the ones the schema uses.
            $db->statement('create table m1_pooling_probe (
                id bigserial primary key,
                flag boolean not null,
                maybe boolean null,
                qty integer not null,
                amount numeric(14,2) not null,
                note text null,
                meta jsonb null,
                raw json null,
                payload bytea null,
                tags text[] null,
                created_at timestamp(0) null
            )');

            $rows = [];
            for ($i = 1; $i <= 1200; $i++) {
                $rows[] = [
                    'flag' => $i % 2 === 0,
                    'maybe' => null,
                    'qty' => $i,
                    'amount' => $i === 1 ? '12345678.90' : '0.10',
                    'note' => $i === 1 ? "it's a \"quoted\" ? ; -- note" : null,
                    'meta' => $i === 1 ? json_encode(['a' => 'x\'y', 'n' => 1, 'k' => ['p' => true]]) : null,
                    'raw' => $i === 1 ? '{"z": [1, 2]}' : null,
                    'payload' => null,
                    'tags' => null,
                    'created_at' => $i === 1 ? now()->startOfSecond() : null,
                ];
            }
            foreach (array_chunk($rows, 400) as $chunk) {
                $db->table('m1_pooling_probe')->insert($chunk);
            }

            $table = fn () => $db->table('m1_pooling_probe');

            // bool: Laravel turns true/false into 1/0 (Connection::prepareBindings); the server must still read it as boolean
            $this->assertSame(600, $table()->where('flag', true)->count());
            $this->assertSame(600, $table()->where('flag', false)->count());
            $this->assertSame(1200, $table()->whereNull('maybe')->count());
            $table()->where('qty', 1)->update(['maybe' => false]);
            $this->assertSame(1, $table()->where('maybe', false)->count());
            $this->assertFalse($table()->where('qty', 1)->value('maybe'));
            $this->assertTrue($table()->where('qty', 2)->value('flag'));

            // int, decimal string (money stays an exact string, never a float), arithmetic on bound numbers
            $this->assertSame('12345678.90', $table()->where('qty', 1)->value('amount'));
            $this->assertSame('12345798.80', (string) $table()->sum('amount'));
            $this->assertSame('12345679.00', $db->scalar('select amount + ? from m1_pooling_probe where qty = ?', ['0.10', 1]));
            $this->assertSame(3, (int) $db->scalar('select length(?)', [123]));
            $this->assertSame(1, $table()->where('amount', '>', '1000000')->count());

            // text with quotes and a literal ? inside a value
            $this->assertSame("it's a \"quoted\" ? ; -- note", $table()->where('note', "it's a \"quoted\" ? ; -- note")->value('note'));

            // jsonb / json: arrow operators, containment, the jsonb ? operator (Laravel's whereJsonContainsKey)
            $this->assertSame(1, $table()->where('meta->a', 'x\'y')->count());
            $this->assertSame(1, $table()->where('meta->k->p', true)->count());
            $this->assertSame(1, $table()->whereJsonContains('meta', ['n' => 1])->count());
            $this->assertSame(1, $table()->whereJsonContainsKey('meta->k')->count());
            $this->assertSame(1, (int) $db->scalar('select count(*) from m1_pooling_probe where meta ?? ?', ['a']));
            $this->assertSame('x\'y', json_decode((string) $table()->where('qty', 1)->value('meta'), true)['a']);
            $this->assertSame(2, (int) $db->scalar("select (raw->'z'->>1)::int from m1_pooling_probe where qty = ?", [1]));

            // whereIn with many values (one placeholder each) and an array binding
            $ids = range(1, 1100);
            $this->assertSame(1100, $table()->whereIn('qty', $ids)->count());
            $this->assertSame(100, $table()->whereNotIn('qty', $ids)->count());
            $this->assertSame(3, (int) $db->scalar('select count(*) from m1_pooling_probe where qty = any(?::int[])', ['{1,2,3}']));
            $table()->where('qty', 3)->update(['tags' => '{"a","b c"}']);
            $this->assertSame(1, $table()->whereRaw('? = any(tags)', ['b c'])->count());

            // bytea (not used by the schema today) as a PDO LOB and as text input
            $stream = fopen('php://memory', 'r+');
            fwrite($stream, "\x00\x01binary\xff");
            rewind($stream);
            $db->update('update m1_pooling_probe set payload = ? where qty = ?', [$stream, 4]);
            $this->assertSame(bin2hex("\x00\x01binary\xff"), $db->scalar('select encode(payload, \'hex\') from m1_pooling_probe where qty = ?', [4]));

            // null, timestamps, insertGetId (insert ... returning id, no lastval() on another server connection)
            $this->assertTrue((bool) $db->scalar('select ?::text is null', [null]));
            $this->assertNotNull($table()->where('qty', 1)->where('created_at', '<=', now())->value('id'));
            $id = $table()->insertGetId(['flag' => true, 'qty' => 9999, 'amount' => '1.00']);
            $this->assertSame(9999, $table()->where('id', $id)->value('qty'));

            // A savepoint (nested DB::transaction) and a transaction-scoped advisory lock, both as the app uses them
            $db->transaction(function () use ($db, $table) {
                $db->select('select pg_advisory_xact_lock(hashtext(?))', ['m1-pooling-probe']);
                $table()->where('qty', 5)->update(['qty' => 50005]);
            });
            $this->assertSame(1, $table()->where('qty', 50005)->count());
        } finally {
            $db->rollBack();
        }

        $this->assertFalse((bool) DB::connection(self::CONNECTION)->scalar("select to_regclass('m1_pooling_probe') is not null"));
    }

    public function test_every_statement_runs_without_a_named_prepare_under_concurrent_connections(): void
    {
        // Two connections interleaving statements outside a transaction: through PgBouncer they share server
        // connections, which is where named prepared statements fail ("prepared statement pdo_stmt_N does not exist")
        $second = self::CONNECTION . '_2';
        config(['database.connections.' . $second => config('database.connections.' . self::CONNECTION)]);

        try {
            $a = DB::connection(self::CONNECTION);
            $b = DB::connection($second);
            for ($i = 0; $i < 50; $i++) {
                $this->assertSame($i + 1, (int) $a->scalar('select ?::int + 1', [$i]));
                $this->assertSame($i + 2, (int) $b->scalar('select ?::int + 2', [$i]));
            }
        } finally {
            DB::purge($second);
        }
    }

    /**
     * config/database.php's pgsql connection evaluated with these environment variables (null = unset).
     *
     * @param  array<string, string|null>  $vars
     * @return array<string, mixed>
     */
    private function configWith(array $vars): array
    {
        $saved = [];
        foreach ($vars as $name => $value) {
            $saved[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null, getenv($name)];
            $this->setEnv($name, $value);
        }

        try {
            return (require base_path('config/database.php'))['connections']['pgsql'];
        } finally {
            foreach ($saved as $name => [$env, $server, $putenv]) {
                $this->setEnv($name, $env ?? $server ?? ($putenv === false ? null : $putenv));
            }
        }
    }

    private function setEnv(string $name, ?string $value): void
    {
        if ($value === null) {
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);

            return;
        }
        $_ENV[$name] = $_SERVER[$name] = $value;
        putenv("{$name}={$value}");
    }

    private static function disablePreparesAttribute(): int
    {
        return PHP_VERSION_ID >= 80400 ? Pgsql::ATTR_DISABLE_PREPARES : PDO::PGSQL_ATTR_DISABLE_PREPARES;
    }
}
