<?php

namespace App\Console\Commands;

use App\OpenApi\ApiSpec;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\File;

/**
 * Writes the API's OpenAPI document to packages/api-spec/openapi.json, or with --check (CI) fails when the
 * committed file is not what the code generates. Model schemas are read from the database columns, so the database
 * must be migrated first.
 */
class ExportApiSpecCommand extends Command
{
    protected $signature = 'api-spec:export
        {--path= : Where to write (default: packages/api-spec/openapi.json)}
        {--check : Do not write; fail when the file differs from the generated document}';

    protected $description = 'Export the OpenAPI document of the API (packages/api-spec/openapi.json)';

    public function handle(ApiSpec $spec, Migrator $migrator): int
    {
        if ($pending = $this->pendingMigrations($migrator)) {
            $this->error('Ma\'lumotlar bazasida bajarilmagan migratsiyalar bor (' . count($pending) . ' ta). Model sxemalari '
                . 'jadval ustunlaridan olinadi: avval `php artisan migrate` ni ishga tushiring.');

            return self::FAILURE;
        }

        $path = (string) ($this->option('path') ?: ApiSpec::exportPath());
        $json = ApiSpec::encode($spec->document());

        if ($this->option('check')) {
            if (is_file($path) && File::get($path) === $json) {
                $this->info("OpenAPI spetsifikatsiyasi kod bilan mos: {$path}");

                return self::SUCCESS;
            }

            $this->error("{$path} kod yaratadigan spetsifikatsiyadan farq qiladi (yoki yo'q).");
            $this->line('Yangilash: cd backend && php artisan migrate && php artisan api-spec:export');
            $this->line("So'ng TypeScript klient: cd packages/ts/api-client && npm run generate, va o'zgarishlarni commit qiling.");

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $json);
        $this->info("OpenAPI spetsifikatsiyasi yozildi: {$path}");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function pendingMigrations(Migrator $migrator): array
    {
        if (!$migrator->repositoryExists()) {
            return ['migrations'];
        }

        $files = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));

        return array_values(array_diff(array_keys($files), $migrator->getRepository()->getRan()));
    }
}
