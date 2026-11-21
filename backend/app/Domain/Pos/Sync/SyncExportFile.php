<?php

namespace App\Domain\Pos\Sync;

use Illuminate\Support\Str;

/**
 * Wave M5 S4 (docs/scale/M5_DESIGN.md §5, "As built: the export file"): the till's rescue file as the server reads
 * it. The till side is pos_sync's `export.dart`; both are pinned to tests/Fixtures/sync_export_vectors.json.
 *
 *  - UTF-8 text, every line one JSON object ending with LF: the header, the body lines, the trailer;
 *  - header: `{"format":"rizqim-sync-export","version":1,"device":"<uuid>","kid":<n>,"kids":[...],"first_seq":...,
 *    "last_seq":...,"count":<n>,"created_at":"..."}`. Only `format`, `version`, `device` and `kid` are acted on;
 *  - body: one operation per line as POST /pos/sync takes it (`{signed, mac, prev, kid}`, or the unsigned operation),
 *    with `dev` when it was queued under another pairing than the header's;
 *  - trailer: `{"file_mac":"<hex>"}`, the MAC of every byte before the trailer line (SyncSignature::fileMac()).
 *
 * Nothing is verified here (the key is SyncSignature's business) and no body line is decoded: read() only takes the
 * file apart, or says why it is not one. The bytes are kept exactly as they came.
 */
final class SyncExportFile
{
    public const FORMAT = 'rizqim-sync-export';

    public const VERSION = 1;

    /** The largest file taken (bytes). */
    public const MAX_BYTES = 8 * 1024 * 1024;

    /** The most body lines (operations) in one file. */
    public const MAX_LINES = 10000;

    /**
     * @param  array<string, mixed>  $header  the header line as written
     * @param  string  $device  the uuid of the till that wrote the file (lower case)
     * @param  int  $kid  the number of the key the file's MAC is made with
     * @param  list<string>  $lines  the body lines, undecoded (the first one is line 2 of the file)
     * @param  string  $signedBytes  every byte before the trailer line: what the MAC is over
     * @param  string  $mac  the trailer's `file_mac`
     */
    private function __construct(
        public readonly array $header,
        public readonly string $device,
        public readonly int $kid,
        public readonly array $lines,
        public readonly string $signedBytes,
        public readonly string $mac,
    ) {}

    /**
     * @throws PosSyncImportException 422 `not_export_file`, `version_unsupported`, `file_too_large`
     */
    public static function read(string $bytes): self
    {
        if (strlen($bytes) > self::MAX_BYTES) {
            throw self::tooLarge();
        }

        // The trailer is the last line; the line break after it may be missing
        $text = str_ends_with($bytes, "\n") ? substr($bytes, 0, -1) : $bytes;
        $cut = strrpos($text, "\n");
        if ($cut === false) {
            throw self::notOurs();
        }
        $signed = substr($text, 0, $cut + 1);
        $trailer = json_decode(substr($text, $cut + 1), true, 4);
        if (!is_array($trailer) || !SyncSignature::isMac($trailer['file_mac'] ?? null)) {
            throw self::notOurs();
        }

        $end = (int) strpos($signed, "\n");
        $header = json_decode(substr($signed, 0, $end), true, 8);
        if (!is_array($header) || ($header['format'] ?? null) !== self::FORMAT) {
            throw self::notOurs();
        }
        if (($header['version'] ?? null) !== self::VERSION) {
            $version = is_scalar($header['version'] ?? null) ? mb_substr((string) $header['version'], 0, 12) : '?';

            throw new PosSyncImportException('version_unsupported', "Fayl versiyasi ({$version}) bu serverga noma'lum: fayl yangiroq kassa ilovasida yozilgan. RIZQIM qo'llab-quvvatlashiga yozing.");
        }
        $device = $header['device'] ?? null;
        $kid = $header['kid'] ?? null;
        if (!is_string($device) || !Str::isUuid($device) || !is_int($kid) || $kid < 1) {
            throw self::notOurs();
        }

        $body = substr($signed, $end + 1);
        $lines = $body === '' ? [] : explode("\n", substr($body, 0, -1));
        if (count($lines) > self::MAX_LINES) {
            throw self::tooLarge();
        }

        return new self($header, strtolower($device), $kid, $lines, $signed, $trailer['file_mac']);
    }

    private static function notOurs(): PosSyncImportException
    {
        return new PosSyncImportException('not_export_file', "Bu fayl kassa navbati fayli emas yoki to'liq emas. Kassadagi «Navbatni faylga saqlash» orqali olingan .rzq faylni yuklang.");
    }

    private static function tooLarge(): PosSyncImportException
    {
        return new PosSyncImportException('file_too_large', 'Fayl juda katta: eng ko\'pi 8 MB va ' . number_format(self::MAX_LINES, 0, '', ' ') . ' ta amal.');
    }
}
