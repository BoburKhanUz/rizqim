<?php

namespace Tests\Feature\PosSync;

/**
 * Wave M5 S4 (docs/scale/M5_DESIGN.md §5, "As built: the export file"): what the till's export writes (pos_sync
 * `export.dart`), for the server's tests. Pinned to tests/Fixtures/sync_export_vectors.json by
 * tests/Unit/PosSync/SyncExportVectorsTest.php, so a file built here is a file the till would build.
 */
trait BuildsExportFiles
{
    /**
     * @param  string  $key  the bytes of the header device's key number $kid
     * @param  list<array<string, mixed>>  $lines  operations as POST /pos/sync takes them (signed or not), each
     *                                             optionally with `dev` (the device uuid it was queued under)
     * @param  array<string, mixed>  $header  header keys to replace (tests of what a reader refuses)
     */
    protected static function exportFile(string $device, int $kid, string $key, array $lines, ?string $createdAt = null, array $header = []): string
    {
        $device = strtolower($device);
        $operation = fn (array $line): array => is_string($line['signed'] ?? null) ? (array) json_decode($line['signed'], true) : $line;
        $lines = array_map(function (array $line) use ($device): array {
            $dev = isset($line['dev']) ? strtolower((string) $line['dev']) : null;
            unset($line['dev']);

            return $dev !== null && $dev !== $device ? $line + ['dev' => $dev] : $line;
        }, $lines);
        usort($lines, fn (array $a, array $b) => [isset($a['dev']) ? 0 : 1, $a['dev'] ?? '', (int) ($operation($a)['seq'] ?? 0), (string) ($operation($a)['op_id'] ?? '')]
            <=> [isset($b['dev']) ? 0 : 1, $b['dev'] ?? '', (int) ($operation($b)['seq'] ?? 0), (string) ($operation($b)['op_id'] ?? '')]);

        $own = array_map(fn (array $line) => (int) ($operation($line)['seq'] ?? 0), array_values(array_filter($lines, fn (array $line) => !isset($line['dev']))));
        $kids = array_values(array_unique(array_map(fn (array $line) => (int) $line['kid'], array_filter($lines, fn (array $line) => is_string($line['signed'] ?? null)))));
        sort($kids);

        $header += [
            'format' => 'rizqim-sync-export',
            'version' => 1,
            'device' => $device,
            'kid' => $kid,
            'kids' => $kids,
            'first_seq' => $own !== [] ? min($own) : null,
            'last_seq' => $own !== [] ? max($own) : null,
            'count' => count($lines),
            'created_at' => $createdAt ?? now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
        ];

        return self::exportSealed(implode('', array_map(fn (array $line) => self::exportJson($line) . "\n", [$header, ...$lines])), $key);
    }

    /**
     * The trailer under $key after the header and body lines as they are ($signed ends with a line break).
     */
    protected static function exportSealed(string $signed, string $key): string
    {
        return $signed . self::exportJson(['file_mac' => hash_hmac('sha256', "rizqim-sync-export/v1\n" . $signed, $key)]) . "\n";
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected static function exportJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR);
    }
}
