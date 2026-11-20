<?php

namespace App\Domain\Pos\Sync;

use App\Domain\Pos\Accounts\PosOwnerAlerts;
use App\Enums\DeviceType;
use App\Models\AuditLog;
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
 * | mode | `missing` / `bad`, a concession | `missing` / `bad`, the sale itself | `missing` / `bad` on `bill.pay` | `chain_gap` |
 * |---|---|---|---|---|
 * | observe (default) | applied, recorded | applied, recorded | applied, recorded | applied, recorded |
 * | enforce | refused `signature_invalid` | applied + conflict `signature_invalid` | recorded, conflict `payment_unverified` (never dropped) | applied + conflict `chain_gap` |
 *
 * Enforce never drops a sale (the review after S3, supervisor decision D1): the order, its rounds and lines, its bill
 * and its closing are applied whatever their signature, and shown to the manager as a conflict. Refusing them would
 * throw the whole sale away (the payment that follows has no order to land on) and keep the shift from closing. Only
 * a concession is refused (CONCESSIONS: a discount, a void) — what makes the guest pay less: the bill is then made at
 * the full price and the difference is the manager's to settle, as any refused offline discount (wave M5 S2).
 *
 * In neither mode is a batch refused over a signature (a 4xx would freeze the till's queue), and money is never
 * dropped: an unverified payment goes the way of wave M5 S1 (PosSyncApplier::staffUnverified()) — onto its bill when
 * the bill is open, else recorded unallocated on the order — so the bill is paid, the order closes and the shift
 * closes over it.
 *
 * The kill switch `pos.sync_signing` (env POS_SYNC_SIGNING, default on): off, bootstrap tells the tills not to sign
 * and every store is treated as observe (enforce would flag every sale); what still arrives signed is checked and
 * recorded as before.
 *
 * Who hears what, in both modes:
 *  - RIZQIM: the monitoring rule `pos_sync_signature` (PosSyncSignatureCheck) opens an alert per till that sent a
 *    `bad` or `chain_gap` operation in its window, whose key was rotated more than once in it, or that signed and
 *    then sent unsigned operations (audited `device.sync_signature_missing`, once a day per till, afterBatch());
 *  - the owner: one message per till and hour about a `chain_gap` ("kassa navbatidan amal yo'qolgan"), afterBatch().
 *
 * The key is none of this class's business: it never reads, logs or audits it (SyncSignature alone does).
 */
final class SignaturePolicy
{
    public const REASON_INVALID = 'signature_invalid';

    public const REASON_PAYMENT = 'payment_unverified';

    public const REASON_GAP = 'chain_gap';

    /**
     * What enforce refuses when it comes unsigned or badly signed: the operations that make the guest pay less. (No
     * other concession is a sync operation: an order is not cancelled, a charge not waived and nothing refunded
     * offline.)
     *
     * @var list<PosSyncOperationType>
     */
    public const CONCESSIONS = [PosSyncOperationType::ORDER_DISCOUNT, PosSyncOperationType::ORDER_VOID];

    /** The audit record that the owner was told about a till's lost operations (what the next message is throttled by). */
    public const AUDIT_OWNER_TOLD = 'pos.sync_chain_gap_alerted';

    /** The audit record of a till that signed and then sent unsigned operations (PosSyncSignatureCheck reads it). */
    public const AUDIT_STOPPED_SIGNING = 'device.sync_signature_missing';

    /** A till that signed and then sends unsigned operations is audited at most once in this many hours. */
    public const STOPPED_SIGNING_HOURS = 24;

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
        return self::signingOn() && (bool) Store::query()->withTrashed()->whereKey($storeId)->value('pos_sync_enforce_signatures');
    }

    /**
     * The kill switch (config `pos.sync_signing`): off, tills are told not to sign and nothing is enforced.
     */
    public static function signingOn(): bool
    {
        return (bool) config('pos.sync_signing', true);
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

        if ($type !== null && $type->isMoney()) {
            return SignatureVerdict::PAYMENT_UNVERIFIED;
        }

        // (a type the server does not know is rejected for that, whatever is said here)
        return $type !== null && in_array($type, self::CONCESSIONS, true) ? SignatureVerdict::REJECT : SignatureVerdict::APPLY_UNVERIFIED;
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
     * Enforce mode, an operation that was applied although its signature is missing or wrong (APPLY_UNVERIFIED) or a
     * line is missing before it (APPLY_AS_CONFLICT): a conflict for the manager. One that is a conflict or rejected
     * for its own reason keeps that reason (its `sig_status` still says what the signature was worth).
     */
    public function mark(PosSyncOperation $operation, SignatureVerdict $verdict, SyncSignatureStatus $status): PosSyncOperation
    {
        $reason = match ($verdict) {
            SignatureVerdict::APPLY_UNVERIFIED => self::REASON_INVALID,
            SignatureVerdict::APPLY_AS_CONFLICT => self::REASON_GAP,
            default => null,
        };
        if ($reason === null || $operation->status !== PosSyncStatus::APPLIED->value) {
            return $operation;
        }
        $operation->forceFill([
            'status' => PosSyncStatus::CONFLICT->value,
            'reason' => $reason,
            'error' => self::message($status) . ($verdict === SignatureVerdict::APPLY_UNVERIFIED ? " Amal qo'llandi, menejer tekshirsin." : ''),
        ])->save();

        return $operation;
    }

    /**
     * After a batch (under the till's lock), whatever the mode; never fails the batch:
     *  - the owner hears about operations lost from the till's queue, at most once per till in OWNER_ALERT_MINUTES,
     *    counted from when the owner was last told (a till that loses a line every 50 minutes is reported every
     *    second time, not once for ever);
     *  - a till that has signed under its current key and now sent unsigned operations is audited
     *    (`device.sync_signature_missing`, at most once in STOPPED_SIGNING_HOURS): signing was taken off it, or its
     *    app was rolled back. RIZQIM's monitoring rule reads that record.
     *
     * @param  list<PosSyncOperation>  $recorded  the operations this batch recorded (not the ones answered as duplicates)
     * @param  list<int>  $unsigned  the numbers of the operations that came unsigned after the till had signed under its current key
     */
    public function afterBatch(Device $device, array $recorded, array $unsigned = []): void
    {
        try {
            $this->stoppedSigning($device, $unsigned);
        } catch (Throwable $e) {
            Log::error("The audit record about unsigned operations of device {$device->uuid} failed: {$e->getMessage()}");
        }

        try {
            $gaps = array_values(array_filter($recorded, fn (PosSyncOperation $op) => $op->sig_status === SyncSignatureStatus::CHAIN_GAP->value));
            if ($gaps === [] || $this->auditedSince($device, self::AUDIT_OWNER_TOLD, now()->subMinutes(self::OWNER_ALERT_MINUTES))) {
                return;
            }

            $name = trim((string) $device->name) !== '' ? (string) $device->name : 'Kassa';
            $seqs = implode(', ', array_map(fn (PosSyncOperation $op) => '№' . $op->seq, array_slice($gaps, 0, 5)));
            $this->alerts->send((int) $device->store_id, "⚠️ «{$name}»: kassa navbatidan amal yo'qolgan.\n"
                . "Internetsiz paytda yozilgan amallar serverga ketma-ket kelmadi: {$seqs} amaldan oldingi yozuv(lar) yetishmayapti.\n"
                . "Kelgan amallar qo'llandi. Shu kassaning sotuvlari va smena pulini tekshiring; sababi noma'lum bo'lsa RIZQIM qo'llab-quvvatlashiga yozing.");
            // Only now: a message that could not be handed over does not silence the next one. Kept in the audit log,
            // not in the cache (a deploy or a Redis restart must not send it twice): numbers only
            $this->audit->record(self::AUDIT_OWNER_TOLD, $device, [], [], [
                'operations' => count($gaps),
                'first_seq' => (int) $gaps[0]->seq,
            ], ['actor' => null, 'device' => $device]);
        } catch (Throwable $e) {
            Log::error("The owner alert about a sync chain gap of device {$device->uuid} failed: {$e->getMessage()}");
        }
    }

    /**
     * @param  list<int>  $unsigned
     */
    private function stoppedSigning(Device $device, array $unsigned): void
    {
        if ($unsigned === [] || !self::signingOn() || $this->auditedSince($device, self::AUDIT_STOPPED_SIGNING, now()->subHours(self::STOPPED_SIGNING_HOURS))) {
            return;
        }
        // Numbers only: never the key, a mac or what the operations carried
        $this->audit->record(self::AUDIT_STOPPED_SIGNING, $device, [], [], [
            'kid' => (int) $device->getAttribute('sync_key_id'),
            'operations' => count($unsigned),
            'first_seq' => min($unsigned),
        ], ['actor' => null, 'device' => $device]);
    }

    /**
     * Whether the device has an audit record of that action since then (the (action, created_at) index; a handful of
     * rows: these actions are written at most once an hour or a day per till).
     */
    private function auditedSince(Device $device, string $action, \Illuminate\Support\Carbon $since): bool
    {
        return AuditLog::query()->where('action', $action)->where('created_at', '>=', $since)->where('device_id', $device->id)->exists();
    }

    // --- the owner's switch ------------------------------------------------------------------------------------------

    /**
     * What GET /owner/pos/sync-signing shows: the mode, and the store's active tills that have not shown that they
     * sign: enforce would flag what they send. A till signs once the server holds a verified operation from it under
     * its current key (the head of its chain, `devices.sync_chain`) and the last operation it sent was not unsigned.
     * A key on the server proves nothing by itself (every till gets one when it is paired, an app from before signing
     * too), and neither does a till the server has nothing from.
     *
     * @return array{enforce: bool, signing_tills: int, unsigned_tills: list<array{uuid: string, name: string, branch_id: int|null, has_key: bool, last_sig_status: string|null}>}
     */
    public function present(Store $store): array
    {
        // The last operation the server holds from each till: one index probe per till (the unique (device_id, seq) index)
        $tills = Device::query()->where('store_id', $store->id)->where('type', DeviceType::POS->value)->whereNull('revoked_at')
            ->orderBy('id')
            ->select(['id', 'uuid', 'name', 'branch_id', 'sync_key_id', 'sync_chain'])
            ->selectSub(fn ($query) => $query->from('pos_sync_operations')->select('sig_status')->whereColumn('pos_sync_operations.device_id', 'devices.id')->orderByDesc('seq')->limit(1), 'last_sig_status')
            ->get();

        $unsigned = [];
        foreach ($tills as $till) {
            $kid = (int) $till->getAttribute('sync_key_id');
            $status = $till->getAttribute('last_sig_status');
            $status = is_string($status) ? $status : null;
            $proven = $kid > 0 && SyncChain::headKid($till->getAttribute('sync_chain')) === $kid;
            if ($proven && $status !== null && $status !== SyncSignatureStatus::MISSING->value) {
                continue;
            }
            $unsigned[] = ['uuid' => (string) $till->uuid, 'name' => (string) $till->name, 'branch_id' => $till->branch_id !== null ? (int) $till->branch_id : null, 'has_key' => $kid > 0, 'last_sig_status' => $status];
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
