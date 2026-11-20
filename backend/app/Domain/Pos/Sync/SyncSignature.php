<?php

namespace App\Domain\Pos\Sync;

use App\Models\Device;
use App\Models\PosSyncOperation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wave M5 S3 (docs/scale/M5_DESIGN.md §4, owner decision 2): a till signs every operation it queues with a secret it
 * shares with the server, so a journal line changed or removed on the till's disk is noticed when the queue arrives.
 * HMAC-SHA256 only (ext-hash here, `package:crypto` on the till); the Dart side is pos_sync's `signature.dart`, both
 * pinned to tests/Fixtures/sync_signature_vectors.json.
 *
 *  - key: 32 random bytes per device, made here, given to the till once (the pairing answer or
 *    POST /terminal/sync-key) as base64; kept encrypted in `devices.sync_key` with its number `sync_key_id` (kid) and
 *    the one before it in `sync_key_prev` (operations queued before a rotation still verify);
 *  - raw: the operation's JSON text exactly as the till wrote it to its journal and sent it (`signed`). The server
 *    verifies the bytes it received and then decodes them: neither side ever has to write JSON the other's way;
 *  - mac = HMAC(key, prev || raw), `prev` being the mac of the operation before it (its 32 bytes); the first operation
 *    under a key uses firstPrev() = HMAC(key, "rizqim-sync/v1/" + the device's uuid in lower case);
 *  - on the wire mac and prev are 64 lower-case hex characters.
 *
 * The key is never logged, audited or shown again: only this class reads it off the device row.
 */
final class SyncSignature
{
    public const LABEL = 'rizqim-sync/v1/';

    public const KEY_BYTES = 32;

    /** A disconnected device's key is kept this long (a rescue file exported from that till can still be checked). */
    public const KEEP_REVOKED_DAYS = 30;

    /** chainFor() reads this many of a device's last operations (a batch holds at most PosSyncService::MAX_BATCH). */
    public const CHAIN_LOOKBACK = 50;

    /**
     * @param  string  $key  the key's bytes
     * @return string 64 hex characters
     */
    public static function firstPrev(string $key, string $deviceUuid): string
    {
        return hash_hmac('sha256', self::LABEL . strtolower($deviceUuid), $key);
    }

    /**
     * @param  string  $key  the key's bytes
     * @param  string  $prev  64 hex characters (isMac())
     * @param  string  $raw  the operation's JSON text as received
     * @return string 64 hex characters
     */
    public static function mac(string $key, string $prev, string $raw): string
    {
        return hash_hmac('sha256', (string) hex2bin($prev) . $raw, $key);
    }

    /**
     * 64 lower-case hex characters (what a mac or a prev looks like on the wire).
     */
    public static function isMac(mixed $value): bool
    {
        return is_string($value) && strlen($value) === 64 && strspn($value, '0123456789abcdef') === 64;
    }

    /**
     * A new key, as it travels to the till (base64 of 32 random bytes).
     */
    public static function newKey(): string
    {
        return base64_encode(random_bytes(self::KEY_BYTES));
    }

    /**
     * Gives the device a key: its first one, or (it has one already) a new one with the next kid, the old one kept as
     * the previous key. The chain is not reset: the new key's first operation starts its own (SyncChain).
     *
     * @return array{key: string, kid: int, rotated: bool} `key` in base64: handed to the till in this one answer, never again
     */
    public static function issue(Device $device): array
    {
        return self::give($device, false) ?? throw new \LogicException('A key is always given when the queue is not asked about.');
    }

    /**
     * issue() for a till that asks by itself (POST /terminal/sync-key). Only one previous key is kept, so a rotation
     * is refused (null) while the queue the till last reported is not empty (`devices.sync_pending_ops`,
     * `sync_oldest_op_at`): those operations are signed with the key the rotation after this one would push out. A
     * first key is always given. This is all the server knows: a till that is offline cannot report what it queued
     * since (the signature protects the journal on the till's disk, not against whoever holds the terminal's token).
     *
     * @return array{key: string, kid: int, rotated: bool}|null
     */
    public static function issueUnlessQueued(Device $device): ?array
    {
        return self::give($device, true);
    }

    /**
     * @return array{key: string, kid: int, rotated: bool}|null
     */
    private static function give(Device $device, bool $unlessQueued): ?array
    {
        return DB::transaction(function () use ($device, $unlessQueued) {
            $row = Device::whereKey($device->getKey())->lockForUpdate()->firstOrFail();
            $current = self::stored($row, 'sync_key');
            $kid = (int) $row->getAttribute('sync_key_id');
            $rotated = $kid > 0;
            if ($rotated && $unlessQueued && ((int) $row->getAttribute('sync_pending_ops') > 0 || $row->getAttribute('sync_oldest_op_at') !== null)) {
                return null;
            }
            $key = self::newKey();

            $attributes = ['sync_key' => $key, 'sync_key_id' => $kid + 1, 'sync_key_prev' => $rotated ? $current : null];
            $row->forceFill($attributes)->saveQuietly();
            // The caller's instance (the guard's row) follows without a second read
            $device->forceFill($attributes)->syncOriginal();

            return ['key' => $key, 'kid' => $kid + 1, 'rotated' => $rotated];
        });
    }

    /**
     * The chain of a device as the server holds it now, to check a batch against (two small reads per batch).
     */
    public static function chainFor(Device $device): SyncChain
    {
        // Read now, not when the request signed in: the caller holds the till's lock (PosSyncService), and a batch
        // that waited for it must see what the batch before it left
        $device = Device::query()->whereKey($device->getKey())->first(['id', 'uuid', 'sync_key', 'sync_key_id', 'sync_key_prev', 'sync_chain', 'sync_last_seq']) ?? $device;
        $kid = (int) $device->getAttribute('sync_key_id');
        $keys = [];
        foreach ([$kid => 'sync_key', $kid - 1 => 'sync_key_prev'] as $number => $column) {
            $key = $number > 0 ? self::stored($device, $column) : null;
            $bytes = $key !== null ? base64_decode($key, true) : false;
            if (is_string($bytes) && strlen($bytes) === self::KEY_BYTES) {
                $keys[$number] = $bytes;
            }
        }

        // The last operations the server recorded (one bounded read over the unique (device_id, seq) index), newest
        // first: the number to follow, and what the device row may have missed when a process was killed between the
        // operation's row and the device row (that must not read as a removed line)
        $rows = PosSyncOperation::where('device_id', $device->getKey())->orderByDesc('seq')->limit(self::CHAIN_LOOKBACK)->get(['id', 'seq', 'mac', 'sig_status']);
        $newest = $rows->first();
        // ...the mac of the last operation whose own signature was right, past the unsigned ones after it
        $verified = $rows->first(fn (PosSyncOperation $row) => $row->mac !== null && in_array($row->sig_status, [SyncSignatureStatus::OK->value, SyncSignatureStatus::CHAIN_GAP->value], true));
        // ...and the mac a badly signed last line claims, when its number followed the one before it (SyncChain)
        $signed = $rows->search(fn (PosSyncOperation $row) => $row->sig_status !== SyncSignatureStatus::MISSING->value && $row->sig_status !== null);
        $claimed = null;
        if ($signed !== false && $rows[$signed]->sig_status === SyncSignatureStatus::BAD->value && $rows[$signed]->mac !== null) {
            $before = $rows[$signed + 1] ?? null;
            $inPlace = $before !== null ? (int) $rows[$signed]->seq === (int) $before->seq + 1 : $rows->count() < self::CHAIN_LOOKBACK;
            $claimed = $inPlace ? (string) $rows[$signed]->mac : null;
        }

        return new SyncChain(
            (string) $device->uuid,
            $keys,
            is_string($device->getAttribute('sync_chain')) ? $device->getAttribute('sync_chain') : null,
            max((int) $device->getAttribute('sync_last_seq'), (int) $newest?->seq),
            $verified?->mac,
            $claimed,
            $newest?->sig_status === SyncSignatureStatus::MISSING->value,
        );
    }

    /**
     * The nightly job: keys of devices disconnected more than KEEP_REVOKED_DAYS ago are erased.
     */
    public static function eraseExpired(): int
    {
        return Device::query()
            ->whereNotNull('revoked_at')
            ->where('revoked_at', '<', now()->subDays(self::KEEP_REVOKED_DAYS))
            ->where(fn ($query) => $query->whereNotNull('sync_key')->orWhereNotNull('sync_key_prev'))
            ->update(['sync_key' => null, 'sync_key_prev' => null, 'sync_chain' => null]);
    }

    /**
     * A stored key (base64), or null when there is none or it cannot be read (the application key changed): the till's
     * operations then arrive as `bad`, applied all the same; the batch never fails over it.
     */
    private static function stored(Device $device, string $column): ?string
    {
        try {
            $value = $device->getAttribute($column);
        } catch (Throwable) {
            Log::warning('A device sync key cannot be decrypted: its operations will be recorded as badly signed until the till gets a new key.', ['device' => $device->uuid, 'column' => $column]);

            return null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
