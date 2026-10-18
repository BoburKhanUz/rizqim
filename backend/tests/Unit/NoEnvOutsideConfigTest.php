<?php

namespace Tests\Unit;

use PhpToken;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * env() returns null once `php artisan config:cache` has run, so only config/ may read the environment.
 */
class NoEnvOutsideConfigTest extends TestCase
{
    public function test_env_is_only_called_from_config_files(): void
    {
        $base = dirname(__DIR__, 2);
        $offenders = [];

        foreach (['app', 'routes', 'database'] as $dir) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$base}/{$dir}", RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                foreach ($this->envCallLines(file_get_contents($file->getPathname())) as $line) {
                    $offenders[] = substr($file->getPathname(), strlen($base) + 1) . ":{$line}";
                }
            }
        }

        $this->assertSame([], $offenders, 'env() outside config/ — add a config key and read it with config(): ' . implode(', ', $offenders));
    }

    public function test_the_scan_finds_calls_and_ignores_comments_strings_and_methods(): void
    {
        $code = "<?php\n// env('A')\n\$a = 'env(\"B\")';\n\$b = \$this->env('C');\n\$c = env('D');\n\$d = \\env('E');\n";

        $this->assertSame([5, 6], $this->envCallLines($code));
    }

    /**
     * Lines calling the global env() function.
     *
     * @return list<int>
     */
    private function envCallLines(string $code): array
    {
        $tokens = array_values(array_filter(PhpToken::tokenize($code), fn (PhpToken $token) => !$token->isIgnorable()));
        $lines = [];

        foreach ($tokens as $i => $token) {
            if (!$token->is([T_STRING, T_NAME_FULLY_QUALIFIED]) || strtolower(ltrim($token->text, '\\')) !== 'env') {
                continue;
            }

            $previous = ($tokens[$i - 1] ?? null)?->text;
            if (($tokens[$i + 1] ?? null)?->text === '(' && !in_array($previous, ['->', '?->', '::', 'function', 'new', 'const'], true)) {
                $lines[] = $token->line;
            }
        }

        return $lines;
    }
}
