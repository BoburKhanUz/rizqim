<?php

namespace App\Domain\Pos\Sync;

use App\Domain\Pos\Accounts\PosOwnerAlerts;
use App\Enums\DeviceType;
use App\Models\Device;
use App\Models\PosSyncOperation;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wave M5 S3-d (docs/scale/M5_DESIGN.md §4, owner answer 4): what a store does with the signature of an offline
 * operation. Two modes, the store's own switch (`stores.pos_sync_enforce_signatures`, an owner setting, audited
 * `settings.pos_sync_signatures_changed`):
 *
 * | mode | `missing` / `bad`, not money | `missing` / `bad` on `bill.pay` | `chain_gap` |
 * |---|---|---|---|
 * | observe (default) | applied, recorded | applied, recorded | applied, recorded |
 * | enforce | rejected `signature_invalid` | recorded, conflict `payment_unverified` (never dropped) | applied + conflict `chain_gap` |
 *
 * In neither mode is a batch refused over a signature (a 4xx would freeze the till's queue), and money is never
 * dropped: an unverified payment goes the way of wave M5 S1 (PosSyncApplier::staffUnverified()) — onto its bill when
 * the bill is open, else recorded unallocated on the order — so the bill is paid, the order closes and the shift
 * closes over it.
 *
 * Who hears what, in both modes:
 *  - RIZQIM: the monitoring rule `pos_sync_signature` (PosSyncSignatureCheck) opens an alert per till that sent a
 *    `bad` or `chain_gap` operation in its window;
 *  - the owner: one message per till and hour about a `chain_gap` ("kassa navbatidan amal yo'qolgan"), afterBatch().
 *
 * The key is none of this class's business: it never reads, logs or audits it (SyncSignature alone does).
 */
final class SignaturePolicy
{
    public const REASON_INVALID = 'signature_invalid';

    public const REASON_PAYMENT = 'payment_unverified';

    public const REASON_GAP = 'chain_gap';

    /** The owner is told about a till's lost operations at most once in this many minutes. */
    public const OWNER_ALERT_MINUTES = 60;

    public const VIEW = 'array{enforce: bool, signing_tills: int, unsigned_tills: list<array{uuid: string, name: string, branch_id: int|null, has_key: bool, last_sig_status: string|null}>}';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PosOwnerAlerts $alerts,
    ) {}

    /**
     * Whether the store enforces signatures (read once per batch by PosSyncService).
     */
    public function enforced(int $storeId): bool
    {
        return (bool) Store::query()->withTrashed()->whereKey($storeId)->value('pos_sync_enforce_signatures');
    }

    /**
     * The modes table above.
     */
    public static function verdict(bool $enforce, ?PosSyncOperationType $type, SyncSignatureStatus $status): SignatureVerdict
    {
        if (!$enforce || $status === SyncSignatureStatus::OK) {
            return SignatureVerdict::APPLY;
        }
        if ($status === SyncSignatureStatus::CHAIN_GAP) {
            return SignatureVerdict::APPLY_AS_CONFLICT;
        }

        return $type !== null && $type->isMoney() ? SignatureVerdict::PAYMENT_UNVERIFIED : SignatureVerdict::REJECT;
    }

    /**
     * The Uzbek text of a refusal or a flag (what "Sinxronlash xatolari" shows).
     */
    public static function message(SyncSignatureStatus $status): string
    {
        return match ($status) {
            SyncSignatureStatus::MISSING => "Amal imzosiz keldi (kassa ilovasi eski yoki kassada imzo kaliti yo'q).",
            SyncSignatureStatus::BAD => "Amal imzosi noto'g'ri: kassa navbatidagi yozuv o'zgartirilgan yoki kalit bu kassaniki emas.",
            SyncSignatureStatus::CHAIN_GAP => "Bu amaldan oldingi amal(lar) serverga kelmagan: kassa navbatidan amal yo'qolgan. Amal qo'llandi, menejer tekshirsin.",
            SyncSignatureStatus::OK => '',
        };
    }

    /**
     * Enforce mode, an operation that was applied although a line is missing before it: a conflict for the manager.
     * One that is a conflict or rejected for its own reason keeps that reason (its `sig_status` still says chain_gap).
     */
    public function markChainGap(PosSyncOperation $operation): PosSyncOperation
    {
        if ($operation->status !== PosSyncStatus::APPLIED->value) {
            return $operation;
        }
        $operation->forceFill([
            'status' => PosSyncStatus::CONFLICT->value,
            'reason' => self::REASON_GAP,
            'error' => self::message(SyncSignatureStatus::CHAIN_GAP),
        ])->save();

        return $operation;
    }

    /**
     * After a batch (under the till's lock): the owner hears about operations lost from the till's queue, once per
     * till and hour, whatever the mode. Never fails the batch.
     *
     * @param  list<PosSyncOperation>  $recorded  the operations this batch recorded (not the ones answered as duplicates)
     */
    public function afterBatch(Device $device, array $recorded): void
    {
        try {
            $gaps = array_values(array_filter($recorded, fn (PosSyncOperation $op) => $op->sig_status === SyncSignatureStatus::CHAIN_GAP->value));
            if ($gaps === []) {
                return;
            }
            $told = PosSyncOperation::query()
                ->where('device_id', $device->id)
                ->where('sig_status', SyncSignatureStatus::CHAIN_GAP->value)
                ->where('received_at', '>=', now()->subMinutes(self::OWNER_ALERT_MINUTES))
                ->whereNotIn('id', array_map(fn (PosSyncOperation $op) => $op->id, $gaps))
                ->exists();
            if ($told) {
                return;
            }

            $name = trim((string) $device->name) !== '' ? (string) $device->name : 'Kassa';
            $seqs = implode(', ', array_map(fn (PosSyncOperation $op) => '№' . $op->seq, array_slice($gaps, 0, 5)));
            $this->alerts->send((int) $device->store_id, "⚠️ «{$name}»: kassa navbatidan amal yo'qolgan.\n"
                . "Internetsiz paytda yozilgan amallar serverga ketma-ket kelmadi: {$seqs} amaldan oldingi yozuv(lar) yetishmayapti.\n"
                . "Kelgan amallar qo'llandi. Shu kassaning sotuvlari va smena pulini tekshiring; sababi noma'lum bo'lsa RIZQIM qo'llab-quvvatlashiga yozing.");
        } catch (Throwable $e) {
            Log::error("The owner alert about a sync chain gap of device {$device->uuid} failed: {$e->getMessage()}");
        }
    }

    // --- the owner's switch ------------------------------------------------------------------------------------------

    /**
     * What GET /owner/pos/sync-signing shows: the mode, and the store's active tills that do not sign yet (no key on
     * the server, or the last operation the server holds from them came unsigned): enforce would reject what they send.
     *
     * @return array{enforce: bool, signing_tills: int, unsigned_tills: list<array{uuid: string, name: string, branch_id: int|null, has_key: bool, last_sig_status: string|null}>}
     */
    public function present(Store $store): array
    {
        $tills = Device::query()->where('store_id', $store->id)->where('type', DeviceType::POS->value)->whereNull('revoked_at')
            ->orderBy('id')->get(['id', 'uuid', 'name', 'branch_id', 'sync_key_id', 'sync_last_seq']);
        // The last operation the server holds from each (the unique (device_id, seq) index)
        $last = $tills->isEmpty() ? collect() : PosSyncOperation::query()
            ->whereIn(DB::raw('(device_id, seq)'), fn ($query) => $query->from('pos_sync_operations')->selectRaw('device_id, max(seq)')->whereIn('device_id', $tills->pluck('id'))->groupBy('device_id'))
            ->pluck('sig_status', 'device_id');

        $unsigned = [];
        foreach ($tills as $till) {
            $hasKey = (int) $till->getAttribute('sync_key_id') > 0;
            $status = $last[$till->id] ?? null;
            if ($hasKey && $status !== SyncSignatureStatus::MISSING->value) {
                continue;
            }
            $unsigned[] = ['uuid' => (string) $till->uuid, 'name' => (string) $till->name, 'branch_id' => $till->branch_id !== null ? (int) $till->branch_id : null, 'has_key' => $hasKey, 'last_sig_status' => $status];
        }

        return [
            'enforce' => (bool) $store->getAttribute('pos_sync_enforce_signatures'),
            'signing_tills' => $tills->count() - count($unsigned),
            'unsigned_tills' => $unsigned,
        ];
    }

    /**
     * The owner turns enforce on or off. Audited (`settings.pos_sync_signatures_changed`) when it changes.
     */
    public function setEnforce(Store $store, bool $enforce, User $actor): void
    {
        $before = (bool) $store->getAttribute('pos_sync_enforce_signatures');
        if ($before === $enforce) {
            return;
        }

        DB::transaction(function () use ($store, $enforce, $before, $actor) {
            $store->forceFill(['pos_sync_enforce_signatures' => $enforce])->save();
            $this->audit->record('settings.pos_sync_signatures_changed', $store, ['enforce' => $before], ['enforce' => $enforce], [
                'unsigned_tills' => array_column($this->present($store)['unsigned_tills'], 'uuid'),
            ], ['actor' => $actor, 'store_id' => (int) $store->id, 'branch_id' => null]);
        });
    }
}
