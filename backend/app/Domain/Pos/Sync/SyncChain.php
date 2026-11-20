<?php

namespace App\Domain\Pos\Sync;

/**
 * Wave M5 S3 (docs/scale/M5_DESIGN.md §4): one device's chain of signed operations while a batch is checked
 * (SyncSignature::chainFor()). No database here: check() says what an operation's signature is worth, recorded() moves
 * the chain once the operation's row exists, head() is what goes back onto the device row (`devices.sync_chain`).
 *
 * The head is kept as `kid:mac`: the key the last signed operation was under, and its mac.
 *  - an operation under the same key must carry that mac as its `prev`;
 *  - the first operation under a newer key starts that key's own chain (SyncSignature::firstPrev): a rotation needs no
 *    reset, and operations queued under the old key before it still follow their own chain;
 *  - an operation under an older key than the head's is out of order (`chain_gap`).
 * A badly signed operation moves the head to the mac it claims (when its kid is a key the server holds), so one line
 * changed by hand is one `bad`, not a `bad` and a `chain_gap` for the innocent operation after it. Whatever is done to
 * the journal without the key leaves at least one `bad` or `chain_gap`.
 *
 * Independently of the MACs every operation must carry the number after the last one the server holds (`seq = last + 1`),
 * else `chain_gap`. A device the server has nothing from yet may start at any number (a re-paired till goes on counting).
 * An unsigned operation is `missing` whatever its number: `chain_gap` is only ever said of an operation whose own
 * signature is right.
 */
final class SyncChain
{
    private ?int $headKid = null;

    private ?string $headMac = null;

    /**
     * @param  array<int, string>  $keys  the key bytes the server holds for the device, by kid (the current one and the one before it)
     * @param  string|null  $head  `devices.sync_chain`
     * @param  int  $lastSeq  the highest number the server holds for the device (0: nothing yet)
     * @param  string|null  $lastMac  the mac of that operation's row, if it was signed
     */
    public function __construct(
        private readonly string $deviceUuid,
        private readonly array $keys,
        ?string $head = null,
        private int $lastSeq = 0,
        private ?string $lastMac = null,
    ) {
        [$kid, $mac] = array_pad(explode(':', (string) $head, 2), 2, null);
        if (ctype_digit((string) $kid) && SyncSignature::isMac($mac)) {
            $this->headKid = (int) $kid;
            $this->headMac = $mac;
        }
    }

    /**
     * @param  array{raw: string, mac: mixed, prev: mixed, kid: mixed}|null  $signature  null: sent in the old unsigned shape
     */
    public function check(?array $signature, int $seq): SyncSignatureStatus
    {
        if ($signature === null) {
            return SyncSignatureStatus::MISSING;
        }

        $kid = self::kid($signature['kid']);
        $key = $kid !== null ? ($this->keys[$kid] ?? null) : null;
        $mac = $signature['mac'];
        $prev = $signature['prev'];
        if ($key === null || !SyncSignature::isMac($mac) || !SyncSignature::isMac($prev)
            || !hash_equals(SyncSignature::mac($key, $prev, $signature['raw']), $mac)) {
            return SyncSignatureStatus::BAD;
        }

        if (!$this->follows($kid, $key, $prev) || ($this->lastSeq > 0 && $seq !== $this->lastSeq + 1)) {
            return SyncSignatureStatus::CHAIN_GAP;
        }

        return SyncSignatureStatus::OK;
    }

    /**
     * The operation's row was written: the next one is checked against it.
     *
     * @param  array{raw: string, mac: mixed, prev: mixed, kid: mixed}|null  $signature
     */
    public function recorded(?array $signature, int $seq): void
    {
        $this->lastSeq = max($this->lastSeq, $seq);
        $this->lastMac = self::storedMac($signature);

        $kid = $signature !== null ? self::kid($signature['kid']) : null;
        if ($kid !== null && isset($this->keys[$kid]) && $this->lastMac !== null) {
            $this->headKid = $kid;
            $this->headMac = $this->lastMac;
        }
    }

    /**
     * What `devices.sync_chain` holds after the batch (null: no signed operation yet).
     */
    public function head(): ?string
    {
        return $this->headMac !== null ? $this->headKid . ':' . $this->headMac : null;
    }

    /**
     * The mac kept on the operation's row (`pos_sync_operations.mac`): the one it came with, when it looks like one.
     *
     * @param  array{raw: string, mac: mixed, prev: mixed, kid: mixed}|null  $signature
     */
    public static function storedMac(?array $signature): ?string
    {
        return $signature !== null && SyncSignature::isMac($signature['mac']) ? $signature['mac'] : null;
    }

    private function follows(int $kid, string $key, string $prev): bool
    {
        // Straight after the last recorded operation, whatever the device row says (see SyncSignature::chainFor())
        if ($this->lastMac !== null && hash_equals($this->lastMac, $prev)) {
            return true;
        }
        if ($this->headKid !== null && $this->headKid > $kid) {
            return false;
        }
        $expected = $this->headKid === $kid ? (string) $this->headMac : SyncSignature::firstPrev($key, $this->deviceUuid);

        return hash_equals($expected, $prev);
    }

    private static function kid(mixed $kid): ?int
    {
        return is_int($kid) && $kid > 0 ? $kid : null;
    }
}
