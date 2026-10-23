<?php

namespace App\Domain\Pos\Sync;

use App\Models\Device;
use App\Models\PosSyncOperation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * POST /pos/sync (P3-31, docs/POS_SPEC.md §5.2): a batch of operations from one till's offline queue, applied one by
 * one in the device's `seq` order (PosSyncApplier), each idempotent by its `op_id`: an operation already received is
 * answered from pos_sync_operations and never applied again, so the same batch sent twice changes nothing.
 *
 * One batch per till at a time (a lock); a database failure (deadlock, lost connection) stops the batch at that
 * operation, which stays in the device's queue to be sent again. Review P3-35: an operation that fails on its own
 * content is answered (rejected `invalid_payload`, money recorded unallocated) and never stops the queue. The device's clock is compared with the server's at
 * send time (`sent_at`): more than 5 minutes apart is a warning. What the device says about its queue (`queue.size`,
 * `queue.oldest_at`) is kept on the device row: the shift does not close while it has operations waiting.
 */
final class PosSyncService
{
    /** Operations in one batch at most. */
    public const MAX_BATCH = 50;

    /** The device's clock this far from the server's is a warning (POS_SPEC 5.3). */
    public const CLOCK_SKEW_WARN_SECONDS = 300;

    public function __construct(
        private readonly PosSyncApplier $applier,
    ) {}

    /**
     * @param  list<array{op_id: string, seq: int, type: string, occurred_at?: ?string, staff_id?: ?int, payload?: array<string, mixed>}>  $operations
     * @param  array{size?: int|null, oldest_at?: string|null}|null  $queue
     * @return array{results: list<PosSyncOperation>, duplicates: list<string>, stopped_at: int|null, clock_skew_seconds: int|null}
     *
     * @throws PosSyncException 409 `sync_in_progress` while another batch of this till is being applied
     */
    public function sync(Device $device, array $operations, ?Carbon $sentAt, ?array $queue): array
    {
        $lock = Cache::lock("pos-sync:device:{$device->id}", 120);
        if (!$lock->block(10)) {
            throw new PosSyncException('sync_in_progress', "Bu kassaning boshqa sinxronlash so'rovi hali tugamadi. Birozdan keyin qayta yuboring.");
        }

        try {
            $skew = $sentAt !== null ? (int) round($sentAt->diffInSeconds(now(), false)) : null;
            usort($operations, fn (array $a, array $b) => (int) $a['seq'] <=> (int) $b['seq']);

            $results = [];
            $duplicates = [];
            $stoppedAt = null;
            foreach ($operations as $op) {
                $opId = strtolower((string) $op['op_id']);
                $existing = PosSyncOperation::where('op_id', $opId)->first();
                if ($existing !== null) {
                    if ((int) $existing->device_id !== (int) $device->id) {
                        // Somebody else's operation id: never shown, never applied
                        throw new PosSyncException('op_id_conflict', "Amal raqami boshqa qurilmaga tegishli: ilovani qayta o'rnating.", ['op_id' => $opId]);
                    }
                    $results[] = $existing;
                    $duplicates[] = $opId;
                    continue;
                }
                if (PosSyncOperation::where('device_id', $device->id)->where('seq', (int) $op['seq'])->exists()) {
                    throw new PosSyncException('seq_reused', "Navbat raqami ({$op['seq']}) boshqa amalga berilgan: kassa navbati buzilgan, administratorga ayting.", ['seq' => (int) $op['seq']]);
                }

                try {
                    $results[] = $this->applier->apply($device, $op, $skew);
                } catch (QueryException|\PDOException $e) {
                    // The database, not the operation (the applier answers the operation's own errors, review
                    // P3-35): it stays queued on the till and comes again
                    report($e);
                    $stoppedAt = (int) $op['seq'];
                    break;
                }
            }

            $this->report($device, $results, $queue, $skew);

            return ['results' => $results, 'duplicates' => $duplicates, 'stopped_at' => $stoppedAt, 'clock_skew_seconds' => $skew];
        } finally {
            $lock->release();
        }
    }

    /**
     * What the device said about its queue, less what this batch took off it, and its clock.
     *
     * @param  list<PosSyncOperation>  $results
     * @param  array{size?: int|null, oldest_at?: string|null}|null  $queue
     */
    public function report(Device $device, array $results, ?array $queue, ?int $skew): void
    {
        $lastSeq = max((int) $device->sync_last_seq, ...array_map(fn (PosSyncOperation $op) => (int) $op->seq, $results ?: [new PosSyncOperation(['seq' => 0])]));
        $attributes = ['sync_last_seq' => $lastSeq, 'sync_reported_at' => now()];
        if ($queue !== null && isset($queue['size'])) {
            $left = max(0, (int) $queue['size'] - count($results));
            $attributes['sync_pending_ops'] = $left;
            $attributes['sync_oldest_op_at'] = $left > 0 && !empty($queue['oldest_at']) ? rescue(fn () => Carbon::parse((string) $queue['oldest_at']), null, false) : null;
        }
        if ($skew !== null) {
            $attributes['sync_clock_skew_seconds'] = $skew;
        }

        $device->forceFill($attributes)->saveQuietly();
    }

    public static function clockWarning(?int $skew): bool
    {
        return $skew !== null && abs($skew) > self::CLOCK_SKEW_WARN_SECONDS;
    }
}
