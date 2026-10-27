<?php

namespace App\Services\Admin\Monitoring;

use App\Domain\Delivery\ZoneResolver;
use App\Enums\DeviceType;
use App\Enums\StoreStatus;
use App\Models\Device;
use App\Models\Store;
use App\Support\StoreTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Wave 1 review fix: ONE answer to "is this terminal's branch open now?", used by the offline alert
 * (PosDeviceOfflineCheck) and by Bugun's "Ish vaqtida oflayn kassalar" (OfflineTerminalsSource), so the two never
 * disagree. A terminal counts as open when its store is active and switched on (`stores.is_open`) and:
 *  - it has a branch with its own opening hours: the branch is active and open at that time (ZoneResolver::isBranchOpen);
 *  - it has an active branch without hours, or no branch: the store's own hours.
 * No hours, or opening = closing time, means round the clock. Hours are read in the store's time zone; opening time is
 * inclusive, closing time exclusive, closing before opening means overnight.
 */
class TerminalBusinessHours
{
    public const STORE_COLUMNS = 'id,name,timezone,is_open,open_time,close_time,status';

    public const BRANCH_COLUMNS = 'id,store_id,name,status,is_active,open_time,close_time';

    public function __construct(
        private readonly ZoneResolver $zones,
    ) {}

    /**
     * Narrows a device query to paired POS / KDS terminals of active, switched-on stores and loads what window() reads.
     *
     * @param  Builder<Device>  $devices
     * @return Builder<Device>
     */
    public function constrain(Builder $devices): Builder
    {
        return $devices
            ->whereNull('revoked_at')
            ->whereNotNull('store_id')
            ->whereIn('type', array_map(fn (DeviceType $type) => $type->value, DeviceType::terminals()))
            ->whereHas('store', fn ($store) => $store->where('status', StoreStatus::ACTIVE->value)->where('is_open', true))
            ->with(['store:' . self::STORE_COLUMNS, 'branch:' . self::BRANCH_COLUMNS]);
    }

    /**
     * The business period the terminal is in at $now, or null when its branch / store is closed.
     */
    public function window(Device $device, CarbonImmutable $now): ?OpenWindow
    {
        /** @var Store|null $store */
        $store = $device->store;
        if ($store === null || $store->status !== StoreStatus::ACTIVE || !$store->is_open) {
            return null;
        }

        $branch = $device->branch;
        $hours = null;
        if ($branch !== null) {
            if (!$this->zones->isBranchOpen($branch, $now, $store)) {
                return null;
            }
            $hours = $this->hours($branch->open_time, $branch->close_time);
        }
        // No branch, or a branch without hours: the store's own
        $hours ??= $this->hours($store->getRawOriginal('open_time'), $store->getRawOriginal('close_time'));

        if ($hours === null || $hours[0] === $hours[1]) {
            return new OpenWindow(null);
        }

        [$open, $close] = $hours;
        $local = $now->setTimezone(StoreTimezone::of($store));
        $time = $local->format('H:i:s');

        if ($open < $close) {
            return $time >= $open && $time < $close ? new OpenWindow($local->setTimeFromTimeString($open)) : null;
        }

        // Overnight (18:00 - 02:00): in the evening part it opened today, in the small hours yesterday
        if ($time >= $open) {
            return new OpenWindow($local->setTimeFromTimeString($open));
        }

        return $time < $close ? new OpenWindow($local->subDay()->setTimeFromTimeString($open)) : null;
    }

    public function isOpen(Device $device, CarbonImmutable $now): bool
    {
        return $this->window($device, $now) !== null;
    }

    /**
     * @return array{0: string, 1: string}|null [open, close] as H:i:s, null when either is missing
     */
    private function hours(mixed $open, mixed $close): ?array
    {
        $open = $this->clock($open);
        $close = $this->clock($close);

        return $open === null || $close === null ? null : [$open, $close];
    }

    private function clock(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $value = trim((string) $value);

        return strlen($value) === 5 ? $value . ':00' : substr($value, 0, 8);
    }
}
