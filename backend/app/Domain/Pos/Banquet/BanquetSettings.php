<?php

namespace App\Domain\Pos\Banquet;

use App\Models\BanquetSetting;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\StoreTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * P6-51/P6-52: the store's banquet settings with their defaults: the slots' local times, how long a hold without a
 * deposit lasts, the guest-count warnings (200/250/300) and the time window (06:00–23:00) — both WARNINGS, never
 * refusals —, the MXIK codes of the advance and the cancellation fee lines (the accountant confirms them), the default
 * quote currency. Every change is audited (banquet.settings_updated).
 */
class BanquetSettings
{
    public const VIEW = 'array{slots: array{morning: array{start: string, end: string}, day: array{start: string, end: string}, evening: array{start: string, end: string}}, hold_hours: int, guest_warning_thresholds: list<int>, window_start: string, window_end: string, advance_mxik: string|null, advance_package_code: string|null, cancellation_mxik: string|null, cancellation_package_code: string|null, quote_currency: string|null, version: int}';

    public const DEFAULT_THRESHOLDS = [200, 250, 300];

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function row(int $storeId): ?BanquetSetting
    {
        return BanquetSetting::where('store_id', $storeId)->first();
    }

    /**
     * @return array{slots: array<string, array{start: string, end: string}>, hold_hours: int, guest_warning_thresholds: list<int>, window_start: string, window_end: string, advance_mxik: string|null, advance_package_code: string|null, cancellation_mxik: string|null, cancellation_package_code: string|null, quote_currency: string|null, version: int}
     */
    public function view(int $storeId): array
    {
        $row = $this->row($storeId);
        $slots = [];
        foreach (BanquetSlot::named() as $slot) {
            [$start, $end] = $slot->defaultTimes();
            $stored = $row?->slots[$slot->value] ?? null;
            $slots[$slot->value] = [
                'start' => is_array($stored) && isset($stored['start']) ? (string) $stored['start'] : $start,
                'end' => is_array($stored) && isset($stored['end']) ? (string) $stored['end'] : $end,
            ];
        }
        $thresholds = $row?->guest_warning_thresholds;

        return [
            'slots' => $slots,
            'hold_hours' => $row !== null ? (int) $row->hold_hours : 72,
            'guest_warning_thresholds' => is_array($thresholds) ? array_values(array_map('intval', $thresholds)) : self::DEFAULT_THRESHOLDS,
            'window_start' => $row !== null ? $row->window_start : '06:00',
            'window_end' => $row !== null ? $row->window_end : '23:00',
            'advance_mxik' => $row?->advance_mxik,
            'advance_package_code' => $row?->advance_package_code,
            'cancellation_mxik' => $row?->cancellation_mxik,
            'cancellation_package_code' => $row?->cancellation_package_code,
            'quote_currency' => $row?->quote_currency,
            'version' => $row !== null ? (int) $row->version : 0,
        ];
    }

    /**
     * The UTC instants a slot covers on a local date: [start, end); an end at or before the start ends the next day.
     * A custom slot takes `$customStart`/`$customEnd` ("HH:MM").
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function instants(int $storeId, string $date, BanquetSlot $slot, ?string $customStart = null, ?string $customEnd = null): array
    {
        $tz = StoreTimezone::of(Store::find($storeId));
        if ($slot === BanquetSlot::CUSTOM) {
            [$start, $end] = [(string) $customStart, (string) $customEnd];
        } else {
            $times = $this->view($storeId)['slots'][$slot->value];
            [$start, $end] = [$times['start'], $times['end']];
        }

        $from = Carbon::parse("{$date} {$start}:00", $tz);
        $to = Carbon::parse("{$date} {$end}:00", $tz);
        if (!$to->greaterThan($from)) {
            $to->addDay();
        }

        return [$from->utc(), $to->utc()];
    }

    /**
     * @param  array<string, mixed>  $data  the validated fields that were sent
     */
    public function update(int $storeId, array $data, User $actor): void
    {
        DB::transaction(function () use ($storeId, $data, $actor) {
            $row = BanquetSetting::where('store_id', $storeId)->lockForUpdate()->first() ?? new BanquetSetting(['store_id' => $storeId]);
            $before = $this->view($storeId);

            if (array_key_exists('slots', $data)) {
                $slots = $before['slots'];
                foreach ((array) $data['slots'] as $key => $times) {
                    $slots[$key] = ['start' => (string) $times['start'], 'end' => (string) $times['end']];
                }
                $row->slots = $slots;
            }
            if (array_key_exists('guest_warning_thresholds', $data)) {
                $values = array_values(array_unique(array_map('intval', (array) ($data['guest_warning_thresholds'] ?? []))));
                sort($values);
                $row->guest_warning_thresholds = $values;
            }
            foreach (['hold_hours', 'window_start', 'window_end', 'advance_mxik', 'advance_package_code', 'cancellation_mxik', 'cancellation_package_code', 'quote_currency'] as $field) {
                if (array_key_exists($field, $data)) {
                    $row->{$field} = $data[$field];
                }
            }
            $row->version = $row->exists ? $row->version + 1 : 1;
            $row->save();

            $this->audit->record('banquet.settings_updated', $row, $before, $this->view($storeId), [], ['actor' => $actor, 'store_id' => $storeId, 'branch_id' => null]);
        });
    }
}
