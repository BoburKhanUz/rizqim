<?php

namespace Tests\Unit\PosSync;

use App\Domain\Pos\Sync\SyncChain;
use App\Domain\Pos\Sync\SyncSignature;
use PHPUnit\Framework\TestCase;

/**
 * Wave M5 S3 (docs/scale/M5_DESIGN.md §4, test plan 7.1-10): the signature of a queued offline operation is pos_sync's
 * byte for byte. tests/Fixtures/sync_signature_vectors.json is read by the Dart test of pos_sync too; it was written by
 * an independent script, never by the code under test. The file holds no key: a test derives the keys from its seed.
 */
class SyncSignatureVectorsTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function vectors(): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/Fixtures/sync_signature_vectors.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * The test key of a kid (32 bytes), as the fixture's `test_keys` says.
     */
    private static function key(int $kid): string
    {
        return hash_hmac('sha256', 'kid-' . $kid, (string) self::vectors()['test_keys']['seed'], true);
    }

    public function test_the_fixture_holds_no_key_and_the_derived_ones_are_the_ones_it_was_made_with(): void
    {
        $vectors = self::vectors();
        $this->assertSame(1, $vectors['version']);
        $this->assertSame(SyncSignature::LABEL, $vectors['label']);
        foreach ($vectors['test_keys']['kids'] as $kid) {
            $this->assertSame(SyncSignature::KEY_BYTES, strlen(self::key($kid)));
            $this->assertSame($vectors['test_keys']['sha256_of_key'][(string) $kid], hash('sha256', self::key($kid)));
            $text = (string) file_get_contents(dirname(__DIR__, 2) . '/Fixtures/sync_signature_vectors.json');
            $this->assertStringNotContainsString(bin2hex(self::key($kid)), $text);
            $this->assertStringNotContainsString(base64_encode(self::key($kid)), $text);
        }
    }

    public function test_the_first_prev_and_every_mac_of_the_chains_match(): void
    {
        $vectors = self::vectors();
        $this->assertGreaterThanOrEqual(2, count($vectors['chains']));

        foreach ($vectors['chains'] as $chain) {
            $key = self::key((int) $chain['kid']);
            $prev = SyncSignature::firstPrev($key, (string) $vectors['device']);
            $this->assertSame($vectors['first_prev'][(string) $chain['kid']], $prev, $chain['name']);
            // The device uuid counts in lower case, however it is written
            $this->assertSame($prev, SyncSignature::firstPrev($key, strtoupper((string) $vectors['device'])));

            foreach ($chain['operations'] as $k => $operation) {
                $this->assertSame($prev, $operation['prev'], "{$chain['name']} #{$k} prev");
                $this->assertSame($operation['mac'], SyncSignature::mac($key, $prev, (string) $operation['signed']), "{$chain['name']} #{$k} mac");
                $this->assertSame((int) $chain['kid'], $operation['kid']);
                $prev = $operation['mac'];
            }
        }
    }

    public function test_the_signed_text_is_taken_as_bytes_never_re_encoded(): void
    {
        $operations = self::vectors()['chains'][0]['operations'];
        $key = self::key(1);

        // Non-ASCII text and a line break inside the first operation; the last one is not the shape PHP would write
        $this->assertStringContainsString('Ўзбекча', $operations[0]['signed']);
        $odd = $operations[4]['signed'];
        $this->assertNotSame($odd, json_encode(json_decode($odd, true)));
        $this->assertSame($operations[4]['mac'], SyncSignature::mac($key, $operations[4]['prev'], $odd));
        $this->assertNotSame($operations[4]['mac'], SyncSignature::mac($key, $operations[4]['prev'], (string) json_encode(json_decode($odd, true))));
    }

    public function test_the_server_records_what_the_cases_expect(): void
    {
        $vectors = self::vectors();
        $this->assertGreaterThanOrEqual(12, count($vectors['cases']));

        foreach ($vectors['cases'] as $case) {
            $keys = [];
            foreach ($case['keys'] as $kid) {
                $keys[(int) $kid] = self::key((int) $kid);
            }
            $chain = new SyncChain((string) $vectors['device'], $keys);

            $statuses = [];
            foreach ($case['operations'] as $operation) {
                $signed = isset($operation['signed']);
                $seq = (int) ($signed ? json_decode((string) $operation['signed'], true)['seq'] : $operation['seq']);
                $signature = $signed ? ['raw' => $operation['signed'], 'mac' => $operation['mac'], 'prev' => $operation['prev'], 'kid' => $operation['kid']] : null;
                $status = $chain->check($signature, $seq);
                $chain->recorded($signature, $seq);
                $statuses[] = $status->value;
            }

            $this->assertSame($case['expect'], $statuses, $case['name']);
        }
    }

    public function test_a_new_key_is_32_random_bytes_in_base64(): void
    {
        $one = SyncSignature::newKey();
        $two = SyncSignature::newKey();

        $this->assertNotSame($one, $two);
        $this->assertSame(SyncSignature::KEY_BYTES, strlen((string) base64_decode($one, true)));
    }
}
