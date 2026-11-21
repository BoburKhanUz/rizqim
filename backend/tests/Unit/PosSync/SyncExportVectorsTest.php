<?php

namespace Tests\Unit\PosSync;

use App\Domain\Pos\Sync\PosSyncImportException;
use App\Domain\Pos\Sync\SyncExportFile;
use App\Domain\Pos\Sync\SyncSignature;
use PHPUnit\Framework\TestCase;
use Tests\Feature\PosSync\BuildsExportFiles;

/**
 * Wave M5 S4 (docs/scale/M5_DESIGN.md §5, "As built: the export file"): the till's rescue file is read here as pos_sync
 * writes it, byte for byte. tests/Fixtures/sync_export_vectors.json is read by the Dart test of pos_sync too; it was
 * written by an independent script, never by the code under test. The file holds no key: a test derives the keys from
 * its seed.
 */
class SyncExportVectorsTest extends TestCase
{
    use BuildsExportFiles;

    private const FIXTURE = '/Fixtures/sync_export_vectors.json';

    /**
     * @return array<string, mixed>
     */
    private static function vectors(): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 2) . self::FIXTURE), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * The test key of a device's kid (32 bytes), as the fixture's `test_keys` says.
     */
    private static function key(string $device, int $kid): string
    {
        return hash_hmac('sha256', strtolower($device) . '/kid-' . $kid, (string) self::vectors()['test_keys']['seed'], true);
    }

    public function test_the_fixture_holds_no_key_and_the_derived_ones_are_the_ones_it_was_made_with(): void
    {
        $vectors = self::vectors();
        $text = (string) file_get_contents(dirname(__DIR__, 2) . self::FIXTURE);
        $this->assertSame(1, $vectors['version']);
        $this->assertSame(SyncSignature::FILE_LABEL, $vectors['file_label']);
        $this->assertSame(SyncSignature::LABEL, $vectors['operation_label']);
        $this->assertCount(3, $vectors['test_keys']['sha256_of_key']);
        foreach ($vectors['test_keys']['sha256_of_key'] as $name => $sha) {
            [$device, $kid] = explode('/kid-', $name);
            $key = self::key($device, (int) $kid);
            $this->assertSame(SyncSignature::KEY_BYTES, strlen($key));
            $this->assertSame($sha, hash('sha256', $key));
            $this->assertStringNotContainsString(bin2hex($key), $text);
            $this->assertStringNotContainsString(base64_encode($key), $text);
        }
    }

    public function test_every_file_is_read_and_its_mac_is_the_header_keys(): void
    {
        $vectors = self::vectors();
        $this->assertGreaterThanOrEqual(4, count($vectors['files']));

        foreach ($vectors['files'] as $case) {
            $file = SyncExportFile::read($case['text']);
            $key = self::key($case['header']['device'], (int) $case['header']['kid']);

            $this->assertSame($case['sha256_of_text'], hash('sha256', $case['text']), $case['name']);
            $this->assertSame($case['header']['device'], $file->device, $case['name']);
            $this->assertSame($case['header']['kid'], $file->kid, $case['name']);
            $this->assertSame($case['header'], $file->header, $case['name']);
            $this->assertSame($case['file_mac'], $file->mac, $case['name']);
            $this->assertSame($case['file_mac'], SyncSignature::fileMac($key, $file->signedBytes), $case['name']);
            $this->assertCount($case['header']['count'], $file->lines, $case['name']);
            // The trailer covers everything before it, and nothing of it is a key
            $this->assertSame($case['text'], $file->signedBytes . '{"file_mac":"' . $file->mac . '"}' . "\n", $case['name']);
            $this->assertStringNotContainsString(bin2hex($key), $case['text']);
            $this->assertStringNotContainsString(base64_encode($key), $case['text']);

            // Every signed line is still the operation the till signed, under the key of the device it names
            foreach ($file->lines as $line) {
                $line = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                if (!isset($line['signed'])) {
                    $this->assertArrayHasKey('op_id', $line, $case['name']);
                    continue;
                }
                $lineKey = self::key($line['dev'] ?? $case['header']['device'], (int) $line['kid']);
                $this->assertSame($line['mac'], SyncSignature::mac($lineKey, $line['prev'], $line['signed']), $case['name']);
            }
        }
    }

    public function test_the_test_builder_writes_the_same_bytes_as_the_fixture(): void
    {
        foreach (self::vectors()['files'] as $case) {
            $input = $case['input'];
            $built = self::exportFile($input['device'], (int) $input['kid'], self::key($input['device'], (int) $input['kid']), $input['lines'], $input['created_at']);

            $this->assertSame($case['text'], $built, $case['name']);
        }
    }

    public function test_the_order_and_the_dev_key_of_the_body_lines(): void
    {
        $vectors = self::vectors();
        $case = collect($vectors['files'])->firstWhere('name', 'a queue set aside before the till was paired again');
        $lines = array_map(fn (string $line) => json_decode($line, true), SyncExportFile::read($case['text'])->lines);

        // The earlier pairing's lines first, each naming its device; the till's own lines name none
        $this->assertSame([$vectors['earlier_device'], $vectors['earlier_device'], $vectors['earlier_device'], null, null], array_map(fn (array $line) => $line['dev'] ?? null, $lines));
        $this->assertSame('dev', array_key_last($lines[0]));
        $this->assertSame([1, 3], $case['header']['kids']);
        $this->assertSame([1, 2], [$case['header']['first_seq'], $case['header']['last_seq']]);
    }

    public function test_what_a_reader_accepts_and_refuses(): void
    {
        $vectors = self::vectors();
        $key = self::key($vectors['device'], 1);
        $seen = [];

        foreach ($vectors['readers']['cases'] as $case) {
            try {
                $file = SyncExportFile::read($case['text']);
                $said = hash_equals(SyncSignature::fileMac($key, $file->signedBytes), $file->mac) ? 'ok' : 'file_mac_invalid';
            } catch (PosSyncImportException $e) {
                $said = $e->reason;
                $this->assertSame(422, $e->status, $case['name']);
            }
            $this->assertSame($case['expect'], $said, $case['name']);
            $seen[$said] = true;
        }

        $this->assertEqualsCanonicalizing(['ok', 'file_mac_invalid', 'version_unsupported', 'not_export_file'], array_keys($seen));
    }

    public function test_a_file_past_the_limits_is_refused(): void
    {
        $key = self::key(self::vectors()['device'], 1);
        $lines = array_fill(0, SyncExportFile::MAX_LINES + 1, ['op_id' => '0d5e8f10-0000-4000-8000-000000000001', 'seq' => 1]);

        try {
            SyncExportFile::read(self::exportFile(self::vectors()['device'], 1, $key, $lines, '2026-11-20T11:30:00.000Z'));
            $this->fail('A file with too many lines was read.');
        } catch (PosSyncImportException $e) {
            $this->assertSame('file_too_large', $e->reason);
        }
    }
}
