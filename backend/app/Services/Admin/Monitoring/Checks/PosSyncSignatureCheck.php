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
 * Reads the small partial index over the `bad` / `chain_gap` rows (pos_sync_operations_sig_problem_index). Details
 * carry names and counts only: never a key, a mac or a payload.
 */
class PosSyncSignatureCheck implements AlertCheck
{
    public const MAX_FINDINGS = 100;

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
        if ($rows->isEmpty()) {
            return [];
        }

        $perDevice = [];
        foreach ($rows as $row) {
            $perDevice[(int) $row->device_id][(string) $row->sig_status] = (int) $row->n;
            $perDevice[(int) $row->device_id]['last_at'] = max($perDevice[(int) $row->device_id]['last_at'] ?? '', (string) $row->last_at);
        }
        $perDevice = array_filter($perDevice, fn (array $counts) => ($counts['bad'] ?? 0) + ($counts['chain_gap'] ?? 0) >= $rule->threshold);
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
            $parts = array_filter([
                $bad > 0 ? "{$bad} ta amal imzosi noto'g'ri" : null,
                $gap > 0 ? "navbatdan amal yo'qolgan ({$gap} ta uzilish)" : null,
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
