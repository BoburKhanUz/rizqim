<?php

namespace App\Domain\Pos\Sync;

/**
 * Wave M5 S3 (docs/scale/M5_DESIGN.md §4): one device's chain of signed operations while a batch is checked
 * (SyncSignature::chainFor()). No database here: check() says what an operation's signature is worth, recorded() moves
 * the chain once the operation's row exists, head() is what goes back onto the device row (`devices.sync_chain`).
 *
 * The head is kept as `kid:mac`: the key the last VERIFIED operation (`ok` or `chain_gap`: its own mac was right) was
 * under, and its mac. In this order:
 *  - an operation under an older key than the head's is out of order (`chain_gap`), whatever its `prev`: the key
 *    before a rotation cannot go on signing `ok` operations after the new key's first one;
 *  - its number must be the one after the last the server holds (`seq = last + 1`), else `chain_gap`. A device the
 *    server has nothing from yet may start at any number (a re-paired till goes on counting);
 *  - under the head's key its `prev` must be the head's mac;
 *  - the first operation under a newer key starts that key's own chain (SyncSignature::firstPrev): a rotation needs no
 *    reset, and operations queued under the old key before it still follow their own chain.
 *
 * A badly signed operation never moves the head. When its number follows (`seq = last + 1`) the mac it claims is
 * accepted as the `prev` of the operation after it, beside the head: one line changed by hand in its place is one
 * `bad`, not a `bad` and a `chain_gap` for the innocent operation after it, and a till that skips its own damaged line
 * still follows. A `bad` line whose number does not follow vouches for nothing: what was removed before it surfaces as
 * a `chain_gap` on the next signed operation. (A line replaced in its place by other text under the same number, mac
 * and prev is, to the server, a line changed by hand: one `bad`.)
 *
 * A till whose app was rolled back to a build from before signing and updated again lost its chain (the old build
 * rewrites the journal): its first signed operation starts from the key's first prev again. That is `ok` only straight
 * after an unsigned (`missing`) operation with the number before it, under the device's current key.
 *
 * An unsigned operation is `missing` whatever its number: `chain_gap` is only ever said of an operation whose own
 * signature is right.
 */
final class SyncChain
{
    private ?int $headKid = null;

    private ?string $headMac = null;

    /** The mac claimed by the badly signed operation(s) straight before the next one, and the kid it named (null: read back from the rows, unknown). */
    private ?string $claimedMac = null;

    private ?int $claimedKid = null;

    /**
     * @param  array<int, string>  $keys  the key bytes the server holds for the device, by kid (the current one and the one before it)
     * @param  string|null  $head  `devices.sync_chain`
     * @param  int  $lastSeq  the highest number the server holds for the device (0: nothing yet)
     * @param  string|null  $lastMac  the mac of the last verified operation's row, when the device row was not saved after it
     * @param  string|null  $claimed  the mac the last row claims, when that row is `bad` and its number followed
     * @param  bool  $lastMissing  the last row the server holds came unsigned
     */
    public function __construct(
        private readonly string $deviceUuid,
        private readonly array $keys,
        ?string $head = null,
        private int $lastSeq = 0,
        private ?string $lastMac = null,
        ?string $claimed = null,
        private bool $lastMissing = false,
    ) {
        [$kid, $mac] = array_pad(explode(':', (string) $head, 2), 2, null);
        if (ctype_digit((string) $kid) && SyncSignature::isMac($mac)) {
            $this->headKid = (int) $kid;
            $this->headMac = $mac;
        }
        $this->claimedMac = SyncSignature::isMac($claimed) ? $claimed : null;
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

        if (!$this->follows($kid, $key, $prev, $seq) || !$this->numberFollows($seq)) {
            return SyncSignatureStatus::CHAIN_GAP;
        }

        return SyncSignatureStatus::OK;
    }

    /**
     * The operation's row was written: the next one is checked against it.
     *
     * @param  array{raw: string, mac: mixed, prev: mixed, kid: mixed}|null  $signature
     * @param  SyncSignatureStatus  $status  what check() said of it
     */
    public function recorded(?array $signature, int $seq, SyncSignatureStatus $status): void
    {
        $followed = $this->numberFollows($seq);
        $this->lastSeq = max($this->lastSeq, $seq);
        $this->lastMissing = $status === SyncSignatureStatus::MISSING;
        if ($status === SyncSignatureStatus::MISSING) {
            return;
        }

        $kid = $signature !== null ? self::kid($signature['kid']) : null;
        $mac = self::storedMac($signature);
        if ($status === SyncSignatureStatus::BAD) {
            // Never the head. Its claim counts only in its own place and under a key the server holds
            if ($followed && $mac !== null && $kid !== null && isset($this->keys[$kid])) {
                $this->claimedMac = $mac;
                $this->claimedKid = $kid;
            } elseif (!$followed) {
                $this->claimedMac = null;
                $this->claimedKid = null;
            }

            return;
        }

        $this->headKid = $kid;
        $this->headMac = $mac;
        $this->lastMac = null;
        $this->claimedMac = null;
        $this->claimedKid = null;
    }

    /**
     * What `devices.sync_chain` holds after the batch (null: no verified operation yet).
     */
    public function head(): ?string
    {
        return $this->headMac !== null ? $this->headKid . ':' . $this->headMac : null;
    }

    /**
     * The device has sent a verified operation under its current key: it signs (an unsigned operation from it now is
     * a step back: SignaturePolicy::afterBatch()).
     */
    public function signsUnderCurrentKey(): bool
    {
        return $this->headKid !== null && $this->keys !== [] && $this->headKid === max(array_keys($this->keys));
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

    /**
     * The key number of a `devices.sync_chain` value (null: none).
     */
    public static function headKid(?string $head): ?int
    {
        [$kid, $mac] = array_pad(explode(':', (string) $head, 2), 2, null);

        return ctype_digit((string) $kid) && SyncSignature::isMac($mac) ? (int) $kid : null;
    }

    private function numberFollows(int $seq): bool
    {
        return $this->lastSeq === 0 || $seq === $this->lastSeq + 1;
    }

    private function follows(int $kid, string $key, string $prev, int $seq): bool
    {
        // The key order first: nothing makes an older key's operation follow a newer key's
        if ($this->headKid !== null && $this->headKid > $kid) {
            return false;
        }

        if ($this->headKid === null || $this->headKid === $kid) {
            if ($this->headMac !== null && hash_equals($this->headMac, $prev)) {
                return true;
            }
            // Straight after the last verified operation the server recorded, when the device row was not saved after
            // it (SyncSignature::chainFor()). The row does not say its key: taken only where no key changed
            if ($this->lastMac !== null && hash_equals($this->lastMac, $prev)) {
                return true;
            }
        }
        // ...or after the mac a badly signed line in its place claimed, under the same key
        if ($this->claimedMac !== null && hash_equals($this->claimedMac, $prev)
            && ($this->claimedKid ?? $this->headKid ?? $kid) === $kid) {
            return true;
        }

        $first = hash_equals(SyncSignature::firstPrev($key, $this->deviceUuid), $prev);
        if ($this->headKid === $kid) {
            // The chain started again under the same key: only a till that came back from a build that does not sign
            // (its unsigned operation is the one before this) and holds the device's current key
            return $first && $this->lastMissing && $seq === $this->lastSeq + 1 && $kid === max(array_keys($this->keys));
        }

        return $first;
    }

    private static function kid(mixed $kid): ?int
    {
        // (a number sent as text, "2", is the same number: a form-encoded or loosely typed client)
        if (is_string($kid) && $kid !== '' && strlen($kid) <= 9 && ctype_digit($kid)) {
            $kid = (int) $kid;
        }

        return is_int($kid) && $kid > 0 ? $kid : null;
    }
}
