<?php

namespace Tests\Unit;

use App\Support\Tenancy\TenantRls;
use PhpToken;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * M4 stage 3 (security review L1, L2): row-level security reaches a single write outside a transaction only when the
 * connection class recognises it (RevisionPostgresConnection::asTenantWrite: the statement starts with INSERT INTO /
 * UPDATE / DELETE FROM and names one of the tables under the policy), and it binds a partitioned journal only through
 * its parent. The query builder and Eloquent always send that shape. Raw SQL can send others the wrap would let
 * through as the login role: a write behind WITH or a comment, a write whose rows come back through DB::select
 * (RETURNING), any hand-written write to a table under the policy, and a partition named directly. This scans every
 * PHP string literal of app/ and routes/ for them, so such a statement cannot appear unnoticed.
 * (DB::insert/update/delete/unprepared/affectingStatement themselves are refused in app/ by RevisionMapCoverageTest.)
 */
class TenantRlsStatementShapesTest extends TestCase
{
    /**
     * Raw SQL on the journals' partitions and the copy between an old and a new journal table: the schema owner's
     * maintenance (db:partitions:*, the conversion), never a tenant request.
     */
    private const ALLOWED = [
        'app/Support/Partitions/PartitionManager.php',
        'app/Support/Partitions/PartitionConverter.php',
        'app/Support/Partitions/PartitionArchiver.php',
    ];

    /** @return array<string, string> */
    private static function patterns(): array
    {
        $tables = implode('|', TenantRls::TABLES);
        $write = '(?:insert\s+into|update|delete\s+from)';

        return [
            'raw write to a table under the policy' => '/\b' . $write . '\s+(?:only\s+)?(?:"?public"?\.)?"?(?:' . $tables . ')"?(?![a-z0-9_])/i',
            'write behind WITH' => '/^\s*with\b[\s\S]*\b' . $write . '\s/i',
            'write behind a comment' => '/^\s*(?:--|\/\*)[\s\S]*\b' . $write . '\s/i',
            'write with RETURNING' => '/^\s*' . $write . '\s[\s\S]*\breturning\b/i',
            'partition named directly' => '/\b(?:audit_logs|fiscal_receipts)_(?:\d{4}_\d{2}|default)\b/i',
        ];
    }

    public function test_no_raw_statement_escapes_the_wrap_or_names_a_partition(): void
    {
        $base = dirname(__DIR__, 2);
        $offenders = [];
        foreach (['app', 'routes'] as $path) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$base}/{$path}", RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                $relative = substr($file->getPathname(), strlen($base) + 1);
                if ($file->getExtension() !== 'php' || in_array($relative, self::ALLOWED, true)) {
                    continue;
                }
                foreach (self::findings((string) file_get_contents($file->getPathname())) as [$line, $what]) {
                    $offenders[] = "{$relative}:{$line} ({$what})";
                }
            }
        }

        $this->assertSame([], $offenders, 'Raw SQL that row-level security would not see as a tenant write (use the query builder inside DB::transaction): ' . implode(', ', $offenders));
    }

    public function test_the_scan_finds_the_shapes_and_leaves_the_query_builder_alone(): void
    {
        $code = <<<'PHP'
            <?php
            // update orders set status = 'x' in a comment is fine
            DB::table('orders')->where('id', 1)->update(['status' => 'paid']);
            Order::whereKey($id)->update(['notes' => DB::raw("'x' || current_user")]);
            DB::select('select * from orders where id = ?', [1]);
            Log::info('The store updated its settings');
            DB::update('update orders set status = ? where id = ?', ['paid', 1]);
            DB::statement("UPDATE ONLY \"payments\" SET amount = 0");
            DB::select('WITH moved AS (DELETE FROM order_items WHERE id = ? RETURNING *) INSERT INTO archive SELECT * FROM moved', [1]);
            DB::statement("/* fast */ update stores set name = 'x'");
            DB::selectOne("insert into {$table} (store_id) values (?) returning id", [1]);
            DB::select('select count(*) from audit_logs_2026_11');
            DB::statement('DELETE FROM public.audit_logs WHERE id = 1');
            DB::table('order_bills_archive')->insert(['id' => 1]);
            PHP;

        $this->assertSame([
            [7, 'raw write to a table under the policy'],
            [8, 'raw write to a table under the policy'],
            [9, 'write behind WITH'],
            [10, 'write behind a comment'],
            [11, 'write with RETURNING'],
            [12, 'partition named directly'],
            [13, 'raw write to a table under the policy'],
        ], self::findings($code));
    }

    /**
     * @return list<array{int, string}>
     */
    private static function findings(string $code): array
    {
        $found = [];
        $literal = '';
        $line = 0;
        $flush = function () use (&$found, &$literal, &$line): void {
            foreach (self::patterns() as $what => $pattern) {
                if ($literal !== '' && preg_match($pattern, $literal) === 1) {
                    $found[] = [$line, $what];
                    break;
                }
            }
            $literal = '';
        };
        $inString = false;
        foreach (PhpToken::tokenize($code) as $token) {
            if ($token->is(T_CONSTANT_ENCAPSED_STRING)) {
                $literal = str_replace(['\\"', "\\'"], ['"', "'"], substr($token->text, 1, -1));
                $line = $token->line;
                $flush();
            } elseif ($token->is('"') || $token->is([T_START_HEREDOC, T_END_HEREDOC])) {
                // An interpolated string or heredoc: its pieces are one statement, with `?` where a variable stands
                if ($inString) {
                    $flush();
                } else {
                    $line = $token->line;
                }
                $inString = !$inString;
            } elseif ($inString) {
                $literal .= $token->is(T_ENCAPSED_AND_WHITESPACE) ? $token->text : '?';
            }
        }

        return $found;
    }
}
