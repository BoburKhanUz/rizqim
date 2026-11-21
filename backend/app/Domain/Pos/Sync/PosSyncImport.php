<?php

namespace App\Domain\Pos\Sync;

use App\Enums\DeviceType;
use App\Models\Device;
use App\Models\PosSyncOperation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Wave M5 S4-b (docs/scale/M5_DESIGN.md §5, "As built: the import"): the restore path of a till's rescue file
 * (SyncExportFile). A manager or the owner uploads it in the partner panel, RIZQIM in the control centre.
 *
 * Whole-file refusals come first and apply nothing (PosSyncImportException): not such a file, an unknown version, a
 * till that is not the caller's (404), no key to check it with, a wrong file MAC. After that nothing refuses the
 * file: every line is answered by itself.
 *
 * The operations are applied through PosSyncService::sync() AS THEIR DEVICE — the same lock, idempotency (`op_id`),
 * signature and chain check, store mode (observe / enforce) and owner messages as a live sync — in batches of
 * PosSyncService::MAX_BATCH in `seq` order, bound to the device's store. So a file imported twice, or after the till
 * sent a part of it, changes nothing, and a payment is recorded exactly as a live sync would record it, never
 * dropped. The till may be disconnected or its token expired: no token is used here.
 *
 * What only an import meets:
 *  - a line queued under an earlier pairing (`dev`): applied as that device of the same store, its signature checked
 *    with that device's keys; a `dev` the store does not have is skipped (`device_unknown`);
 *  - a disconnected device: an operation dated after `revoked_at` (or without a readable time) is not applied —
 *    recorded rejected `device_revoked`; a payment is still recorded (PosSyncApplier, `refuse`);
 *  - a number another operation of the device already has (`seq_reused`): a live till would renumber its queue; here
 *    that operation and every later one of the device in the file get the next free numbers (`renumbered_from`);
 *  - time: the request works for about $timeBudget seconds, the rest is answered as pending and the same file is
 *    uploaded again.
 *
 * The key never passes through here (SyncSignature::verifiesFile()); the audit record holds counts and numbers only.
 */
final class PosSyncImport
{
    /** The audit action of an import (on the device). */
    public const AUDIT = 'pos.sync_imported';

    public const REASON_REVOKED = 'device_revoked';

    /** One operation of the file that has a row on the server: the sync endpoint's result and where it came from. */
    public const RESULT = 'array{op_id: string, seq: int, type: string, status: string, reason: string|null, message: string|null, duplicate: bool, sig_status: string|null, refs: array<string, mixed>, line: int, device: string, renumbered_from: int|null}';

    public const VIEW = 'array{server_time: string, complete: bool, stopped_reason: string|null, file: array{version: int, device: string, kid: int, created_at: string|null, count: int, first_seq: int|null, last_seq: int|null}, device: array{uuid: string, name: string|null, prefix: string|null, last_seq: int, pending_ops: int, oldest_op_at: string|null, reported_at: string|null, clock_skew_seconds: int|null, clock_warning: bool, revoked_at: string|null}, totals: array{operations: int, applied: int, conflict: int, rejected: int, duplicate: int, skipped: int, pending: int, renumbered: int}, results: list<' . self::RESULT . '>, skipped: list<array{line: int, op_id: string|null, seq: int|null, device: string|null, reason: string, message: string}>}';

    /**
     * How long one request applies operations (seconds); at least one batch is always applied. What is left is
     * answered as pending: the same file is uploaded again.
     */
    public static float $timeBudget = 20.0;

    /** How often one batch is tried again after `seq_reused` before its first operation is skipped. */
    private const RENUMBER_TRIES = 3;

    public function __construct(
        private readonly PosSyncService $sync,
        private readonly AuditLogger $audit,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  string  $bytes  the uploaded file, exactly as it came
     * @param  User  $actor  who uploads it (the audit record's actor)
     * @param  int|null  $storeId  the caller's store; null: any store (the RIZQIM team)
     * @param  int|null  $branchId  the branch a branch-bound caller is held to
     * @return array<string, mixed> VIEW
     *
     * @throws PosSyncImportException the whole file refused: nothing was applied
     */
    public function import(string $bytes, User $actor, ?int $storeId, ?int $branchId = null): array
    {
        $file = SyncExportFile::read($bytes);

        // Tenancy first: a till outside the caller's store (or branch) does not exist for them, whatever the file says
        $device = Device::query()
            ->where('uuid', $file->device)
            ->whereNotNull('store_id')
            ->when($storeId !== null, fn ($query) => $query->where('store_id', $storeId))
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->first();
        if ($device === null) {
            throw new PosSyncImportException('device_not_found', "Kassa topilmadi: fayl bu do'konning kassasiniki emas.", 404);
        }
        if ($device->type !== DeviceType::POS) {
            throw new PosSyncImportException('not_a_till', 'Fayldagi qurilma kassa emas.');
        }

        $verified = SyncSignature::verifiesFile($device, $file->kid, $file->signedBytes, $file->mac);
        if ($verified === null) {
            throw new PosSyncImportException('key_unavailable', "Bu kassaning imzo kaliti serverda yo'q (kassa uzilganiga " . SyncSignature::KEEP_REVOKED_DAYS . " kundan oshgan yoki kalit yangilangan): faylni tekshirib bo'lmaydi. RIZQIM qo'llab-quvvatlashiga yozing.");
        }
        if (!$verified) {
            throw new PosSyncImportException('file_mac_invalid', "Fayl imzosi noto'g'ri: fayl o'zgartirilgan, to'liq emas yoki boshqa kassaniki. Kassadan faylni qaytadan oling.");
        }

        // From here on the file is the till's own: every line is answered, nothing refuses the whole
        return $this->tenant->runAs((int) $device->store_id, fn () => $this->apply($file, $device, $actor, $branchId));
    }

    /**
     * @return array<string, mixed>
     */
    private function apply(SyncExportFile $file, Device $device, User $actor, ?int $branchId): array
    {
        $started = microtime(true);
        $skipped = [];
        $groups = $this->open($file, $device, $branchId, $skipped);

        $results = [];
        $pending = 0;
        $stopped = null;
        $first = true;
        foreach ($groups as $group) {
            if ($stopped !== null) {
                $pending += count($group['entries']);
                continue;
            }
            [$done, $left, $stopped] = $this->run($group['device'], $group['entries'], $started, $first, $skipped);
            $first = false;
            $results = [...$results, ...$done];
            $pending += $left;
            $this->lowerReportedQueue($group['device'], $done);
        }

        usort($results, fn (array $a, array $b) => $a['line'] <=> $b['line']);
        usort($skipped, fn (array $a, array $b) => $a['line'] <=> $b['line']);
        $new = array_values(array_filter($results, fn (array $result) => !$result['duplicate']));
        $count = fn (string $status) => count(array_filter($new, fn (array $result) => $result['status'] === $status));
        $totals = [
            'operations' => count($file->lines),
            'applied' => $count(PosSyncStatus::APPLIED->value),
            'conflict' => $count(PosSyncStatus::CONFLICT->value),
            'rejected' => $count(PosSyncStatus::REJECTED->value),
            'duplicate' => count($results) - count($new),
            'skipped' => count($skipped),
            'pending' => $pending,
            'renumbered' => count(array_filter($new, fn (array $result) => $result['renumbered_from'] !== null)),
        ];

        $own = array_map(fn (array $entry) => (int) $entry['seq'], array_merge(...array_map(fn (array $group) => $group['own'] ? $group['all'] : [], $groups)));
        $described = [
            'version' => SyncExportFile::VERSION,
            'device' => $file->device,
            'kid' => $file->kid,
            'created_at' => is_string($file->header['created_at'] ?? null) ? mb_substr($file->header['created_at'], 0, 40) : null,
            // Counted from the body, whatever the header says
            'count' => count($file->lines),
            'first_seq' => $own !== [] ? min($own) : null,
            'last_seq' => $own !== [] ? max($own) : null,
        ];

        // Numbers only: never a key, a mac or what the operations carried
        $this->audit->record(self::AUDIT, $device, [], [], [
            'kid' => $file->kid,
            'file_created_at' => $described['created_at'],
            'first_seq' => $described['first_seq'],
            'last_seq' => $described['last_seq'],
            'totals' => $totals,
            'complete' => $stopped === null,
            'stopped_reason' => $stopped,
            'revoked' => $device->revoked_at !== null,
            'other_devices' => array_values(array_map(fn (array $group) => (string) $group['device']->uuid, array_filter($groups, fn (array $group) => !$group['own']))),
        ], ['actor' => $actor, 'device' => null, 'store_id' => (int) $device->store_id, 'branch_id' => $device->branch_id]);

        $device->refresh();

        return [
            'server_time' => now()->toJSON(),
            'complete' => $stopped === null,
            'stopped_reason' => $stopped,
            'file' => $described,
            'device' => PosSyncPresenter::device($device) + ['revoked_at' => $device->revoked_at?->toJSON()],
            'totals' => $totals,
            'results' => $results,
            'skipped' => $skipped,
        ];
    }

    /**
     * Opens the body: every line becomes an operation of its device (the header's, or the one its `dev` names) as
     * PosSyncService takes it, or an entry of $skipped. Groups: the other devices first (an earlier pairing's queue
     * is older), the header's device last; each in `seq` order.
     *
     * @param  list<array{line: int, op_id: string|null, seq: int|null, device: string|null, reason: string, message: string}>  $skipped
     * @return list<array{device: Device, own: bool, entries: list<array{line: int, seq: int, op: array<string, mixed>}>, all: list<array{line: int, seq: int, op: array<string, mixed>}>}>
     */
    private function open(SyncExportFile $file, Device $device, ?int $branchId, array &$skipped): array
    {
        // The devices the lines name, by uuid (null: not a till of this store)
        $devices = [$file->device => $device];
        $targets = [];
        $entries = [];
        $seen = [];
        foreach ($file->lines as $k => $text) {
            // (line 1 of the file is the header)
            $line = $k + 2;
            $sent = json_decode($text, true, 64);
            if (!is_array($sent) || array_is_list($sent)) {
                $skipped[] = self::skip($line, null, null, null, 'invalid_payload', "Fayl qatori o'qilmadi. Amal qo'llanmadi.");
                continue;
            }
            $dev = is_string($sent['dev'] ?? null) && Str::isUuid($sent['dev']) ? strtolower($sent['dev']) : (array_key_exists('dev', $sent) ? '' : $file->device);
            unset($sent['dev']);

            // The signed shape: the operation is what the signed text holds; what was sent beside it is nobody's word
            $signature = null;
            $decoded = $sent;
            if (is_string($sent['signed'] ?? null)) {
                $signature = ['raw' => $sent['signed'], 'mac' => $sent['mac'] ?? null, 'prev' => $sent['prev'] ?? null, 'kid' => $sent['kid'] ?? null];
                $decoded = json_decode($sent['signed'], true, 64);
            }
            $operation = is_array($decoded) && !array_is_list($decoded) ? self::asRequestInput($decoded) : [];
            $operation = array_intersect_key($operation, array_flip(['op_id', 'seq', 'type', 'occurred_at', 'staff_id', 'payload', 'origin_device']));
            $opId = is_string($operation['op_id'] ?? null) && Str::isUuid($operation['op_id']) ? strtolower($operation['op_id']) : null;
            $seq = is_int($operation['seq'] ?? null) && $operation['seq'] >= 1 ? $operation['seq'] : null;

            if (!array_key_exists($dev, $devices)) {
                // An earlier pairing of the same till: a POS terminal of the same store (a branch-bound caller: branch)
                $devices[$dev] = $dev === '' ? null : Device::query()
                    ->where('uuid', $dev)
                    ->where('store_id', $device->store_id)
                    ->where('type', DeviceType::POS->value)
                    ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                    ->first();
            }
            $target = $devices[$dev];
            if ($target === null) {
                $skipped[] = self::skip($line, $opId, $seq, $dev !== '' ? $dev : null, 'device_unknown', "Amal yozilgan qurilma bu do'konda topilmadi. Amal qo'llanmadi.");
                continue;
            }

            $failed = Validator::make($operation, self::operationRules());
            if ($failed->fails()) {
                $message = "Navbatdagi yozuv buzilgan: amal ma'lumotlari to'liq emas yoki noto'g'ri (" . implode(', ', $failed->errors()->keys()) . "). Amal qo'llanmadi.";
                if ($opId === null || $seq === null) {
                    $skipped[] = self::skip($line, $opId, $seq, $dev, 'invalid_payload', $message);
                    continue;
                }
                // It names itself: recorded rejected for the manager, as the sync endpoint does (only what a row holds)
                $operation = [
                    'op_id' => $opId,
                    'seq' => $seq,
                    'type' => is_string($operation['type'] ?? null) && $operation['type'] !== '' ? mb_substr($operation['type'], 0, 32) : 'unknown',
                    'occurred_at' => is_string($operation['occurred_at'] ?? null) ? $operation['occurred_at'] : null,
                    'staff_id' => is_int($operation['staff_id'] ?? null) && $operation['staff_id'] >= 1 ? $operation['staff_id'] : null,
                    'payload' => is_array($operation['payload'] ?? null) ? $operation['payload'] : [],
                    'invalid' => $message,
                ];
            }
            // (a number sent as text is the same number, as in a live batch)
            $seq = (int) $operation['seq'];
            if (isset($seen[$opId])) {
                $skipped[] = self::skip($line, $opId, $seq, $dev, 'repeated_line', "Bu amal faylda ikki marta yozilgan ({$seen[$opId]}-qator): birinchisi olindi.");
                continue;
            }
            $seen[$opId] = $line;

            $operation['op_id'] = $opId;
            if ($signature !== null) {
                $operation['signature'] = $signature;
            }
            if ($target->revoked_at !== null && !self::before($operation['occurred_at'] ?? null, $target->revoked_at)) {
                // Never applied; a payment is still recorded (PosSyncApplier)
                $operation['refuse'] = ['reason' => self::REASON_REVOKED, 'message' => "Amal kassa uzilganidan keyin yozilgan (yoki vaqti noma'lum): qo'llanmadi, menejer tekshirsin."];
            }
            $targets[$dev] = $target;
            $entries[$dev][] = ['line' => $line, 'seq' => $seq, 'op' => $operation + ['index' => $line]];
        }

        $groups = [];
        foreach ($entries as $dev => $list) {
            // (a stable sort: two lines with one number stay in the file's order)
            usort($list, fn (array $a, array $b) => $a['seq'] <=> $b['seq']);
            $groups[] = ['device' => $targets[$dev], 'own' => $dev === $file->device, 'entries' => $list, 'all' => $list];
        }
        usort($groups, fn (array $a, array $b) => (int) $a['own'] <=> (int) $b['own']);

        return $groups;
    }

    /**
     * One device's operations, batch by batch, until they are through, the time is up or the till's own sync holds
     * the lock.
     *
     * @param  list<array{line: int, seq: int, op: array<string, mixed>}>  $queue  in `seq` order
     * @param  bool  $first  nothing was applied in this request yet (one batch is always applied)
     * @param  list<array<string, mixed>>  $skipped
     * @return array{0: list<array<string, mixed>>, 1: int, 2: string|null} the results, how many operations are left, why it stopped
     */
    private function run(Device $device, array $queue, float $started, bool $first, array &$skipped): array
    {
        $uuid = strtolower((string) $device->uuid);
        $results = [];
        $renumber = false;
        $tries = 0;
        while ($queue !== []) {
            if (!$first && microtime(true) - $started > self::$timeBudget) {
                return [$results, count($queue), 'time_budget'];
            }
            $first = false;

            $batch = array_splice($queue, 0, PosSyncService::MAX_BATCH);
            $ids = array_map(fn (array $entry) => (string) $entry['op']['op_id'], $batch);
            // What the server held before this batch: answered as duplicates
            $known = array_flip(PosSyncOperation::where('device_id', $device->id)->whereIn('op_id', $ids)->pluck('op_id')->all());
            if ($renumber) {
                // The numbers after the last one the server holds for the device, in the file's order (an operation
                // the server already has keeps the number it was recorded under)
                $next = max((int) Device::query()->whereKey($device->getKey())->value('sync_last_seq'), (int) PosSyncOperation::where('device_id', $device->id)->max('seq'));
                foreach ($batch as $k => $entry) {
                    if (isset($known[(string) $entry['op']['op_id']])) {
                        continue;
                    }
                    $batch[$k]['from'] ??= $entry['seq'];
                    $batch[$k]['seq'] = ++$next;
                    $batch[$k]['op']['seq'] = $next;
                }
            }

            $failure = null;
            $outcome = null;
            try {
                $outcome = $this->sync->sync($device, array_map(fn (array $entry) => $entry['op'], $batch), null, null);
            } catch (PosSyncException $e) {
                $failure = $e;
            }

            if ($failure !== null && $failure->reason === 'sync_in_progress') {
                // The till itself is sending right now: nothing of this batch was touched
                return [$results, count($batch) + count($queue), 'sync_in_progress'];
            }

            $rows = $outcome !== null
                ? collect($outcome['results'])->keyBy('op_id')
                : PosSyncOperation::where('device_id', $device->id)->whereIn('op_id', $ids)->get()->keyBy('op_id');
            $unreadable = collect($outcome['unreadable'] ?? [])->keyBy('index');
            // Where the batch stopped: at the operation a refusal names, or (a database failure) at the first one without a row
            $at = $failure !== null ? self::failedAt($batch, $failure) : null;

            foreach ($batch as $k => $entry) {
                if ($at !== null && $k >= $at) {
                    break;
                }
                $opId = (string) $entry['op']['op_id'];
                $row = $rows->get($opId);
                if ($row instanceof PosSyncOperation) {
                    $results[] = PosSyncPresenter::result($row, isset($known[$opId])) + ['line' => $entry['line'], 'device' => $uuid, 'renumbered_from' => !isset($known[$opId]) && isset($entry['from']) && $entry['from'] !== (int) $row->seq ? $entry['from'] : null];
                    continue;
                }
                if ($unreadable->has($entry['line']) || is_string($entry['op']['invalid'] ?? null)) {
                    // A line that holds no usable operation and whose id or number belongs to something else
                    $skipped[] = self::skip($entry['line'], $opId, $entry['from'] ?? $entry['seq'], $uuid, 'invalid_payload', (string) ($unreadable->get($entry['line'])['message'] ?? $entry['op']['invalid']));
                    continue;
                }
                // The database failed at this operation (`stopped_at`): it and what follows it wait for the next upload
                return [$results, count($batch) - $k + count($queue), 'database'];
            }

            if ($failure === null) {
                $tries = 0;
                continue;
            }

            // A refusal that would stop a live till's queue (409): here it is one operation's answer
            $rest = array_slice($batch, (int) $at);
            if ($failure->reason === 'seq_reused' && $tries < self::RENUMBER_TRIES) {
                // Its number belongs to another operation: it and everything after it get the next free numbers
                $renumber = true;
                $tries++;
                $queue = [...$rest, ...$queue];
                continue;
            }
            $tries = 0;
            $head = array_shift($rest);
            if ($head !== null) {
                $skipped[] = self::skip($head['line'], (string) $head['op']['op_id'], $head['from'] ?? $head['seq'], $uuid, $failure->reason, $failure->getMessage());
            }
            $queue = [...$rest, ...$queue];
        }

        return [$results, 0, null];
    }

    /**
     * The place in the batch of the operation a refusal of PosSyncService names (`seq_reused`: its number,
     * `op_id_conflict`: its id). The operations before it were answered; it and the ones after it were not touched.
     *
     * @param  list<array{line: int, seq: int, op: array<string, mixed>}>  $batch
     */
    private static function failedAt(array $batch, PosSyncException $e): int
    {
        foreach ($batch as $k => $entry) {
            if (is_string($entry['op']['invalid'] ?? null)) {
                // (such a line never refuses a batch)
                continue;
            }
            if (($e->reason === 'seq_reused' && (int) $entry['op']['seq'] === (int) ($e->context['seq'] ?? 0) && !PosSyncOperation::where('op_id', $entry['op']['op_id'])->exists())
                || ($e->reason === 'op_id_conflict' && $entry['op']['op_id'] === ($e->context['op_id'] ?? null))) {
                return $k;
            }
        }

        // Not one this class knows: nothing after the rows that exist can be vouched for
        return 0;
    }

    /**
     * After an import the queue the till last reported is lowered by what was recorded from the file, so that a
     * rescued till's shift can close; a till that is alive reports its real queue at its next sync.
     *
     * @param  list<array<string, mixed>>  $results
     */
    private function lowerReportedQueue(Device $device, array $results): void
    {
        $recorded = count(array_filter($results, fn (array $result) => !$result['duplicate']));
        $row = Device::query()->whereKey($device->getKey())->first(['id', 'sync_pending_ops', 'sync_oldest_op_at']);
        if ($recorded === 0 || $row === null || (int) $row->getAttribute('sync_pending_ops') === 0) {
            return;
        }
        $left = max(0, (int) $row->getAttribute('sync_pending_ops') - $recorded);
        try {
            $row->forceFill(['sync_pending_ops' => $left] + ($left === 0 ? ['sync_oldest_op_at' => null] : []))->saveQuietly();
        } catch (Throwable $e) {
            // The operations are in: the report is corrected by the till's next sync
            report($e);
        }
    }

    /**
     * Whether the till's own time of an operation is at or before $limit. No readable time: no.
     */
    private static function before(mixed $occurredAt, Carbon $limit): bool
    {
        if (!is_string($occurredAt) || trim($occurredAt) === '') {
            return false;
        }
        try {
            return Carbon::parse($occurredAt)->lte($limit);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{line: int, op_id: string|null, seq: int|null, device: string|null, reason: string, message: string}
     */
    private static function skip(int $line, ?string $opId, ?int $seq, ?string $device, string $reason, string $message): array
    {
        return ['line' => $line, 'op_id' => $opId, 'seq' => $seq, 'device' => $device, 'reason' => $reason, 'message' => $message];
    }

    /**
     * What POST /pos/sync requires of one operation (PosSyncController::signedOperationRules()): a line of a file is
     * checked on its own, so that one unusable line is that line's answer.
     *
     * @return array<string, mixed>
     */
    private static function operationRules(): array
    {
        return [
            'op_id' => 'required|uuid',
            'seq' => 'required|integer|min:1',
            'type' => ['required', 'string', Rule::in(array_map(fn (PosSyncOperationType $type) => $type->value, PosSyncOperationType::cases()))],
            'occurred_at' => 'nullable|date',
            'staff_id' => 'required|integer|min:1',
            'payload' => 'present|array',
            'origin_device' => 'nullable|uuid',
        ];
    }

    /**
     * What the request middleware does to a live batch (TrimStrings, ConvertEmptyStringsToNull), done to an operation
     * read from a file: both ways in reach the applier alike (PosSyncController::asRequestInput()).
     */
    private static function asRequestInput(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(self::asRequestInput(...), $value);
        }
        if (is_string($value)) {
            $value = Str::trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }
}
