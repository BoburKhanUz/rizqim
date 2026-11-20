<?php

namespace App\Services\Admin\Monitoring\Checks;

use App\Domain\Pos\Sync\SyncSignatureStatus;
use App\Models\AlertRule;
use App\Models\Device;
use App\Models\Store;
use App\Services\Admin\Monitoring\AlertFinding;
use Illuminate\Support\Facades\DB;

/**
 * Wave M5 S3-d (docs/scale/M5_DESIGN.md §4): a till sent at least `threshold` offline operations in the last
 * `window_minutes` whose signature was `bad` (the journal line was changed, or the key is not the device's) or that
 * came after a lost line (`chain_gap`). One subject per device (its uuid); the alert resolves by itself once the
 * window holds no such operation. Unsigned operations (`missing`: an app from before signing) are not a finding: the
 * control centre's device row counts them.
 *
 * Review after S3, two more signals for the same till and the same alert, both read from the audit log (the
 * (action, created_at) index), neither counted against `threshold`:
 *  - its queue signing key was rotated more than once in the window (`device.sync_key_rotated`): a till rotates
 *    rarely and only with an empty queue; two in a day is a broken app or somebody else holding its token, and the
 *    second rotation drops the key its waiting operations were signed with;
 *  - it signed under its current key and then sent unsigned operations (`device.sync_signature_missing`, written
 *    at most once a day per till by SignaturePolicy): signing was taken off the till, or its app was rolled back.
 *
 * Reads the small partial index over the `bad` / `chain_gap` rows (pos_sync_operations_sig_problem_index). Details
 * carry names and counts only: never a key, a mac or a payload.
 */
class PosSyncSignatureCheck implements AlertCheck
{
    public const MAX_FINDINGS = 100;

    public const ROTATED = 'device.sync_key_rotated';

    public const STOPPED_SIGNING = \App\Domain\Pos\Sync\SignaturePolicy::AUDIT_STOPPED_SIGNING;

    /** This many key rotations of one till in the window is a finding ("more than once"). */
    public const ROTATIONS = 2;

    public function findings(AlertRule $rule): ?array
    {
        $window = max(1, (int) ($rule->window_minutes ?? 1440));
        $problems = [SyncSignatureStatus::BAD->value, SyncSignatureStatus::CHAIN_GAP->value];

        $rows = DB::table('pos_sync_operations')
            ->whereIn('sig_status', $problems)
            ->where('received_at', '>=', now()->subMinutes($window))
            ->selectRaw('device_id, sig_status, count(*) AS n, max(received_at) AS last_at')
            ->groupBy('device_id', 'sig_status')
            ->get();
        $perDevice = [];
        foreach ($rows as $row) {
            $perDevice[(int) $row->device_id][(string) $row->sig_status] = (int) $row->n;
            $perDevice[(int) $row->device_id]['last_at'] = max($perDevice[(int) $row->device_id]['last_at'] ?? '', (string) $row->last_at);
        }
        $perDevice = array_filter($perDevice, fn (array $counts) => ($counts['bad'] ?? 0) + ($counts['chain_gap'] ?? 0) >= $rule->threshold);

        // The audit-driven signals (numbers only are read: never a payload)
        $audited = DB::table('audit_logs')
            ->whereIn('action', [self::ROTATED, self::STOPPED_SIGNING])
            ->where('created_at', '>=', now()->subMinutes($window))
            ->whereNotNull('device_id')
            ->selectRaw('device_id, action, count(*) AS n, max(created_at) AS last_at')
            ->groupBy('device_id', 'action')
            ->get();
        foreach ($audited as $row) {
            $n = (int) $row->n;
            if ($row->action === self::ROTATED && $n < self::ROTATIONS) {
                continue;
            }
            $perDevice[(int) $row->device_id][$row->action === self::ROTATED ? 'key_rotations' : 'stopped_signing'] = $n;
            $perDevice[(int) $row->device_id]['last_at'] = max($perDevice[(int) $row->device_id]['last_at'] ?? '', (string) $row->last_at);
        }
        if ($perDevice === []) {
            return [];
        }
        // The most recent first when there are more than the cap
        uasort($perDevice, fn (array $a, array $b) => $b['last_at'] <=> $a['last_at']);
        $perDevice = array_slice($perDevice, 0, self::MAX_FINDINGS, true);

        $devices = Device::query()->whereIn('id', array_keys($perDevice))->with('branch:id,name')->get(['id', 'uuid', 'name', 'store_id', 'branch_id'])->keyBy('id');
        $stores = Store::query()->withTrashed()->whereIn('id', $devices->pluck('store_id')->filter()->unique())->get(['id', 'name', 'pos_sync_enforce_signatures'])->keyBy('id');
        $hours = (int) max(1, round($window / 60));

        $findings = [];
        foreach ($perDevice as $deviceId => $counts) {
            $device = $devices[$deviceId] ?? null;
            if ($device === null) {
                continue;
            }
            $store = $stores[$device->store_id] ?? null;
            $bad = (int) ($counts['bad'] ?? 0);
            $gap = (int) ($counts['chain_gap'] ?? 0);
            $rotations = (int) ($counts['key_rotations'] ?? 0);
            $stopped = (int) ($counts['stopped_signing'] ?? 0);
            $parts = array_filter([
                $bad > 0 ? "{$bad} ta amal imzosi noto'g'ri" : null,
                $gap > 0 ? "navbatdan amal yo'qolgan ({$gap} ta uzilish)" : null,
                $rotations > 0 ? "imzo kaliti {$rotations} marta yangilangan" : null,
                $stopped > 0 ? "imzolab turgan kassa imzolashni to'xtatdi" : null,
            ]);

            $findings[] = new AlertFinding(
                (string) $device->uuid,
                ($store !== null ? $store->name : "Do'kon") . ': ' . ($device->name ?: 'Kassa') . ' — ' . implode(', ', $parts) . " (oxirgi {$hours} soat)",
                [
                    'device_uuid' => (string) $device->uuid,
                    'device_name' => $device->name,
                    'branch' => $device->branch?->name,
                    'store' => $store?->name,
                    'bad' => $bad,
                    'chain_gap' => $gap,
                    'key_rotations' => $rotations,
                    'stopped_signing' => $stopped,
                    'enforce' => (bool) $store?->getAttribute('pos_sync_enforce_signatures'),
                    'last_at' => \Illuminate\Support\Carbon::parse($counts['last_at'], 'UTC')->toIso8601String(),
                    'window_minutes' => $window,
                    'threshold' => (int) $rule->threshold,
                ],
                $store !== null ? (int) $store->id : null,
            );
        }

        return $findings;
    }
}
