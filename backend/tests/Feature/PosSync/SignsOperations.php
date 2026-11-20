<?php

namespace Tests\Feature\PosSync;

use App\Domain\Pos\Sync\SyncSignature;

/**
 * Wave M5 S3: what a signing till does with its queue (pos_sync `signature.dart`), for the server's tests: each
 * operation serialised once (`signed`), mac = HMAC(key, prev || signed), chained from the key's first prev.
 */
trait SignsOperations
{
    /** The mac of the last operation signed (null: the chain has not started). */
    protected ?string $chainPrev = null;

    /**
     * @param  list<array<string, mixed>>  $operations
     * @param  array{key: string, kid: int}  $key  as the pairing answer gave it
     * @return list<array{signed: string, mac: string, prev: string, kid: int}>
     */
    protected function signed(array $operations, array $key, string $deviceUuid): array
    {
        $bytes = (string) base64_decode($key['key'], true);
        $out = [];
        foreach ($operations as $operation) {
            $raw = json_encode($operation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $prev = $this->chainPrev ?? SyncSignature::firstPrev($bytes, $deviceUuid);
            $this->chainPrev = SyncSignature::mac($bytes, $prev, $raw);
            $out[] = ['signed' => $raw, 'mac' => $this->chainPrev, 'prev' => $prev, 'kid' => $key['kid']];
        }

        return $out;
    }
}
