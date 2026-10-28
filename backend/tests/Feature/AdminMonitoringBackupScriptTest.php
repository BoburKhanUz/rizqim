<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * P4-15 review fix: scripts/backup.sh tells the control center "a verified backup exists" only when the dump AND the
 * off-site copy (when one is configured) worked. Runs the real script with stub pg_dump / pg_restore / psql / rclone.
 */
class AdminMonitoringBackupScriptTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/rizqim-backup-test-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/bin', 0777, true);
        mkdir($this->dir . '/backups', 0777, true);

        $this->stub('pg_dump', 'for a in "$@"; do case "$a" in --file=*) printf dump > "${a#--file=}";; esac; done');
        $this->stub('pg_restore', 'exit 0');
        $this->stub('psql', 'printf "%s\n" "$*" >> "' . $this->dir . '/psql.log"');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));

        parent::tearDown();
    }

    private function stub(string $name, string $body): void
    {
        file_put_contents("{$this->dir}/bin/{$name}", "#!/bin/sh\n{$body}\n");
        chmod("{$this->dir}/bin/{$name}", 0755);
    }

    /**
     * @param  array<string, string>  $env
     * @return array{0: int, 1: bool} exit code, whether the marker was written
     */
    private function runBackup(array $env = []): array
    {
        $variables = array_merge([
            'PATH' => "{$this->dir}/bin:/usr/bin:/bin",
            'HOME' => $this->dir,
            'BACKUP_DIR' => "{$this->dir}/backups",
            'PGUSER' => 'u', 'PGDATABASE' => 'd', 'PGHOST' => 'h',
        ], $env);
        $prefix = implode(' ', array_map(fn ($key, $value) => $key . '=' . escapeshellarg($value), array_keys($variables), $variables));
        exec('cd ' . escapeshellarg($this->dir) . " && env -i {$prefix} sh " . escapeshellarg(dirname(__DIR__, 3) . '/scripts/backup.sh') . ' 2>&1', $output, $code);

        return [$code, is_file("{$this->dir}/psql.log") && str_contains((string) file_get_contents("{$this->dir}/psql.log"), 'monitoring_markers')];
    }

    public function test_without_an_off_site_copy_the_marker_is_written_after_the_dump(): void
    {
        [$code, $marker] = $this->runBackup();

        $this->assertSame(0, $code);
        $this->assertTrue($marker);
    }

    public function test_a_failed_off_site_command_leaves_the_marker_alone_and_exits_2(): void
    {
        [$code, $marker] = $this->runBackup(['BACKUP_OFFSITE_CMD' => 'exit 1']);

        $this->assertSame(2, $code);
        $this->assertFalse($marker, 'the control center must not read a local-only dump as a finished backup');
        $this->assertNotEmpty(glob("{$this->dir}/backups/rizqim-d-*.dump"), 'the local dump is kept');
    }

    public function test_a_failed_s3_upload_leaves_the_marker_alone(): void
    {
        $this->stub('rclone', 'exit 1');
        [$code, $marker] = $this->runBackup([
            'BACKUP_S3_ENDPOINT' => 'https://s3.example', 'BACKUP_S3_BUCKET' => 'b', 'BACKUP_S3_ACCESS_KEY' => 'k', 'BACKUP_S3_SECRET' => 's',
        ]);

        $this->assertSame(2, $code);
        $this->assertFalse($marker);
    }

    public function test_a_successful_off_site_copy_records_the_marker(): void
    {
        $this->stub('rclone', 'exit 0');
        [$code, $marker] = $this->runBackup([
            'BACKUP_OFFSITE_CMD' => 'true',
            'BACKUP_S3_ENDPOINT' => 'https://s3.example', 'BACKUP_S3_BUCKET' => 'b', 'BACKUP_S3_ACCESS_KEY' => 'k', 'BACKUP_S3_SECRET' => 's',
        ]);

        $this->assertSame(0, $code);
        $this->assertTrue($marker);
    }
}
