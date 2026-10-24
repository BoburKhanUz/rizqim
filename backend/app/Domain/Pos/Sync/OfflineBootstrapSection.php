<?php

namespace App\Domain\Pos\Sync;

use App\Domain\Pos\Hall\BusinessCalendar;
use App\Domain\Pos\Hall\RoomTariffResolver;
use App\Domain\Pos\Setup\PosBootstrapContributor;
use App\Enums\MinSpendMode;
use App\Enums\OrderChannel;
use App\Models\AutoGuestItem;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\Order;
use App\Models\User;
use App\Services\Order\DiningFeeCalculator;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * The `offline` section of GET /pos/bootstrap (P3-31, docs/POS_SPEC.md §5.2): what a till keeps to sell through a short
 * outage with the simple price rules only — the service charge and place fee in force per hall and channel, today's
 * room fee and minimum spend of each place, the per-guest "choy-non" items — plus its local order number prefix
 * (`K1`, unique in the store, given once) and the next number, and the limits of the limited mode.
 */
final class OfflineBootstrapSection implements PosBootstrapContributor
{
    /** POS_SPEC 5.4: limits the till enforces (minutes, hours, operations). */
    public const LIMITS = [
        'batch_size' => PosSyncService::MAX_BATCH,
        'warn_after_minutes' => 15,
        'alert_after_minutes' => 120,
        'max_queued_orders' => 500,
        'menu_max_age_hours' => 24,
        'contact_max_age_hours' => 24,
        'fiscal_warn_hours' => [24, 40],
        'fiscal_limit_hours' => 48,
        'clock_skew_warn_seconds' => PosSyncService::CLOCK_SKEW_WARN_SECONDS,
    ];

    public function __construct(
        private readonly DiningFeeCalculator $fees,
        private readonly BusinessCalendar $calendar,
        private readonly RoomTariffResolver $tariffs,
    ) {}

    /**
     * @return array{offline: array<string, mixed>}
     */
    public function contribute(User $user, Branch $branch, ?Device $device): array
    {
        $businessDate = $this->calendar->businessDate($branch, now());
        $dayType = $this->calendar->dayType($branch, $businessDate);

        $halls = Hall::where('branch_id', $branch->id)->orderBy('id')->pluck('id')->all();
        $fees = [];
        foreach ([null, ...$halls] as $hallId) {
            $settings = $this->fees->settingsForHall((int) $branch->store_id, (int) $branch->id, $hallId !== null ? (int) $hallId : null);
            foreach ([OrderChannel::POS_HALL, OrderChannel::POS_TAKEAWAY] as $channel) {
                $applies = fn (?array $channels) => in_array($channel->value, $channels ?? DiningFeeCalculator::DEFAULT_CHANNELS, true);
                $fees[] = [
                    'hall_id' => $hallId !== null ? (int) $hallId : null,
                    'channel' => $channel->value,
                    'service_charge_percent' => $settings !== null && $applies($settings->service_charge_channels) ? Money::of((string) $settings->service_charge_percent)->toString() : '0.00',
                    'place_fee_amount' => $settings !== null && $applies($settings->place_fee_channels) ? Money::of((string) $settings->place_fee_amount)->toString() : '0.00',
                    'place_fee_mode' => $settings?->place_fee_mode->value ?? 'per_person',
                ];
            }
        }

        $places = DiningTable::where('branch_id', $branch->id)->where('is_active', true)->orderBy('id')->get()->map(function (DiningTable $table) use ($businessDate, $dayType) {
            $tariff = $this->tariffs->resolve($table, $businessDate, $dayType);

            return [
                'table_id' => (int) $table->id,
                'hall_id' => (int) $table->hall_id,
                'room_fee' => $tariff !== null ? Money::of($tariff->price)->roundTo(0)->toString() : '0.00',
                'min_spend' => $table->min_spend !== null ? Money::of($table->min_spend)->toString() : '0.00',
                'min_spend_mode' => ($table->min_spend_mode ?? MinSpendMode::PER_TABLE)->value,
            ];
        })->values()->all();

        $guestItems = AutoGuestItem::with('variant')
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (AutoGuestItem $rule) => $rule->variant !== null && !$rule->variant->trashed())
            ->map(fn (AutoGuestItem $rule) => [
                'hall_id' => $rule->hall_id !== null ? (int) $rule->hall_id : null,
                'variant_uuid' => strtolower((string) $rule->variant?->uuid),
                'quantity_per_guest' => (string) $rule->quantity_per_guest,
                'sort_order' => (int) $rule->sort_order,
            ])
            ->values()
            ->all();

        return ['offline' => [
            'device_prefix' => $device !== null ? $this->prefix($device) : null,
            'next_local_number' => $device !== null ? $this->nextNumber($device) : 1,
            'business_date' => $businessDate,
            'day_type' => $dayType->value,
            'fees' => $fees,
            'places' => $places,
            'guest_items' => $guestItems,
            'limits' => self::LIMITS,
        ]];
    }

    /**
     * The till's local number prefix: K1, K2... in the order tills first asked, kept on the device.
     */
    public function prefix(Device $device): string
    {
        $prefix = $device->getAttribute('sync_prefix');
        if (is_string($prefix) && $prefix !== '') {
            return $prefix;
        }

        for ($try = 0; $try < 5; $try++) {
            $taken = Device::where('store_id', $device->store_id)->whereNotNull('sync_prefix')->pluck('sync_prefix')
                ->map(fn ($value) => (int) ltrim((string) $value, 'K'))
                ->max();
            $prefix = 'K' . ((int) $taken + 1 + $try);
            try {
                Device::whereKey($device->id)->whereNull('sync_prefix')->update(['sync_prefix' => $prefix]);
                $device->refresh();

                return (string) $device->getAttribute('sync_prefix');
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        return 'K' . $device->id;
    }

    /**
     * After the highest number this till's offline orders reached (a reinstalled app does not count from 1 again).
     */
    private function nextNumber(Device $device): int
    {
        $prefix = $this->prefix($device) . '-';
        $max = Order::where('store_id', $device->store_id)->where('local_number', 'like', $prefix . '%')->pluck('local_number')
            ->map(fn ($value) => (int) substr((string) $value, strlen($prefix)))
            ->max();

        return (int) $max + 1;
    }
}
