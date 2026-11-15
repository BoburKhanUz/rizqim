<?php

namespace Tests\Unit;

use PhpToken;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * php-fpm reaches Postgres through PgBouncer in transaction mode (DB_POOLER=pgbouncer, docker-compose.yml): a server
 * connection belongs to a request only for the length of one transaction, and the next statement may run on another
 * one. Anything that keeps state on the session therefore silently breaks or leaks into another request: session
 * advisory locks (use pg_advisory_xact_lock inside DB::transaction), LISTEN/NOTIFY, session SET / set_config(.., false)
 * (use SET LOCAL inside a transaction), temp tables, SQL-level PREPARE/DEALLOCATE, DECLARE ... CURSOR / WITH HOLD,
 * PDO scrollable cursors and persistent PDO connections. This scans the code that runs in the web process for them.
 *
 * database/migrations is not scanned: the entrypoint runs `migrate` on a direct connection (DB_POOLER=direct), and so
 * do the queue workers, scheduler, Reverb and backups. Laravel's own connect-time `set search_path` is the one session
 * SET in use: harmless because every client sets the same value (config/database.php).
 */
class TransactionPoolingSafetyTest extends TestCase
{
    /** Patterns matched against every PHP string literal (case-insensitive). */
    private const SQL_PATTERNS = [
        'session advisory lock' => '/\bpg_(try_)?advisory_lock(_shared)?\s*\(/i',
        'advisory unlock (session lock)' => '/\bpg_advisory_unlock/i',
        'LISTEN/UNLISTEN' => '/^\s*(UN)?LISTEN\s/i',
        'NOTIFY' => '/(^\s*NOTIFY\s|\bpg_notify\s*\()/i',
        'session SET' => '/^\s*SET\s+(?!LOCAL\s)(SESSION\s+)?[a-z_.]+\s*(=|TO\s)/i',
        'set_config' => '/\bset_config\s*\(/i',
        'RESET / DISCARD' => '/^\s*(RESET|DISCARD)\s/i',
        'temp table' => '/\bCREATE\s+(GLOBAL\s+|LOCAL\s+)?(TEMP|TEMPORARY|UNLOGGED\s+TEMP)\s+TABLE/i',
        'SQL PREPARE/DEALLOCATE' => '/^\s*(PREPARE\s+\w+\s*(\(|AS\s)|DEALLOCATE\s)/i',
        'cursor' => '/\bDECLARE\s+\w+.*\bCURSOR\b|\bWITH\s+HOLD\b/i',
    ];

    /** PHP identifiers that are session state on a PDO connection. */
    private const PHP_IDENTIFIERS = [
        'ATTR_PERSISTENT' => 'persistent PDO connection',
        'ATTR_CURSOR' => 'PDO cursor attribute (scrollable cursors are DECLARE ... CURSOR WITH HOLD in pdo_pgsql)',
        'CURSOR_SCROLL' => 'PDO scrollable cursor',
        'pgsqlGetNotify' => 'LISTEN/NOTIFY',
    ];

    public function test_code_on_the_pooled_connection_uses_no_session_scoped_feature(): void
    {
        $base = dirname(__DIR__, 2);
        $offenders = [];

        foreach (['app', 'routes', 'config', 'bootstrap/app.php', 'database'] as $path) {
            foreach ($this->phpFiles("{$base}/{$path}") as $file) {
                $relative = substr($file, strlen($base) + 1);
                if (str_starts_with($relative, 'database/migrations/')) {
                    continue;
                }

                foreach ($this->findings((string) file_get_contents($file)) as [$line, $what]) {
                    $offenders[] = "{$relative}:{$line} ({$what})";
                }
            }
        }

        $this->assertSame([], $offenders, 'Session-scoped Postgres feature on the PgBouncer (transaction mode) connection; see this test\'s docblock for the transaction-scoped replacement: ' . implode(', ', $offenders));
    }

    public function test_the_scan_finds_session_features_and_allows_transaction_scoped_ones(): void
    {
        $code = <<<'PHP'
            <?php
            // pg_advisory_lock(1) in a comment is fine
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['a']);
            DB::statement('SET LOCAL lock_timeout = \'5s\'');
            DB::table('orders')->update(['status' => 'paid']);
            DB::update('update orders set status = ? where id = ?', ['paid', 1]);
            DB::select('select pg_advisory_lock(42)');
            DB::statement('LISTEN orders');
            DB::statement("SET statement_timeout = 1000");
            DB::statement('set search_path to public');
            DB::select("select set_config('app.store', ?, false)", ['1']);
            DB::statement('CREATE TEMP TABLE x (id int)');
            DB::statement('DECLARE c CURSOR WITH HOLD FOR select 1');
            DB::select('select pg_notify(?, ?)', ['a', 'b']);
            $pdo = new PDO($dsn, $u, $p, [PDO::ATTR_PERSISTENT => true]);
            PHP;

        $this->assertSame(
            [[7, 'session advisory lock'], [8, 'LISTEN/UNLISTEN'], [9, 'session SET'], [10, 'session SET'], [11, 'set_config'], [12, 'temp table'], [13, 'cursor'], [14, 'NOTIFY'], [15, 'persistent PDO connection']],
            $this->findings($code),
        );
    }

    /**
     * @return list<array{int, string}>
     */
    private function findings(string $code): array
    {
        $found = [];

        foreach (PhpToken::tokenize($code) as $token) {
            if ($token->is([T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE])) {
                $text = $token->is(T_CONSTANT_ENCAPSED_STRING) ? substr($token->text, 1, -1) : $token->text;
                foreach (self::SQL_PATTERNS as $what => $pattern) {
                    if (preg_match($pattern, $text)) {
                        $found[] = [$token->line, $what];
                    }
                }
            } elseif ($token->is(T_STRING) && isset(self::PHP_IDENTIFIERS[$token->text])) {
                $found[] = [$token->line, self::PHP_IDENTIFIERS[$token->text]];
            }
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $path): array
    {
        if (is_file($path)) {
            return [$path];
        }

        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }
}
