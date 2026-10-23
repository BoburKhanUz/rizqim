<?php

namespace App\Domain\Pos\Setup;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Device;
use App\Models\KitchenStation;
use App\Models\Printer;
use App\Models\Store;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Support\StoreTimezone;

/**
 * GET /pos/bootstrap (P3-13, POS_SPEC 12.1): everything a terminal needs to start work on its branch in one call:
 * the branch with its time zone, business day start and POS settings, kitchen stations and the routing of dishes to
 * them, the printers this terminal may use, a summary of the staff, `menu_version` and the server's time.
 *
 * Halls and tables (P3-12) and the current shift (P3-18) join through PosBootstrapContributor: until they are
 * contributed, `halls` and `tables` are empty lists and `shift` is null.
 */
final class PosBootstrap
{
    /** Container tag of the PosBootstrapContributor implementations. */
    public const CONTRIBUTORS = 'pos.bootstrap';

    public function __construct(
        private readonly PermissionService $permissions,
        private readonly KitchenStationRouter $router,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, Branch $branch, ?Device $device): array
    {
        $store = Store::findOrFail($branch->store_id);
        $timezone = StoreTimezone::of($store);
        $startsAt = PosSettings::timeOf($branch->business_day_starts_at);
        $now = now();
        $settings = PosSettings::resolve($branch->pos_settings);
        $settings['printing_device_id'] = self::activePrintingDevice($branch, $settings['printing_device_id']);

        $routes = $this->router->routesOf((int) $branch->id);

        $data = [
            'server_time' => $now->toIso8601String(),
            'menu_version' => MenuVersion::forStore((int) $store->id),
            'store' => ['id' => (int) $store->id, 'name' => (string) $store->name, 'timezone' => $timezone],
            'branch' => [
                'id' => (int) $branch->id,
                'name' => (string) $branch->name,
                'address' => $branch->address,
                'phone' => $branch->phone,
                'timezone' => $timezone,
                'business_day_starts_at' => $startsAt,
                'business_date' => PosSettings::businessDate($now, $timezone, $startsAt),
                'pos_settings' => $settings,
            ],
            'device' => $device === null ? null : [
                'uuid' => $device->uuid,
                'type' => $device->type->value,
                'name' => $device->name,
                // The "chop etuvchi" till prints the tickets of online orders
                'is_printing_device' => $settings['printing_device_id'] !== null && $settings['printing_device_id'] === $device->uuid,
            ],
            'user' => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'role' => $user->role->value,
                'permissions' => array_map(fn ($permission) => $permission->value, $this->permissions->permissionsFor($user)),
            ],
            'stations' => $this->stations($branch),
            'station_routes' => [
                'categories' => array_map(fn (int $categoryId, int $stationId) => ['category_id' => $categoryId, 'station_id' => $stationId], array_keys($routes['categories']), array_values($routes['categories'])),
                'products' => array_map(fn (int $productId, int $stationId) => ['product_id' => $productId, 'station_id' => $stationId], array_keys($routes['products']), array_values($routes['products'])),
            ],
            'printers' => $this->printers($branch, $device),
            'staff' => $this->staff($branch),
            // Placeholders until P3-12 (halls, tables) and P3-18 (shift) contribute their sections
            'halls' => [],
            'tables' => [],
            'shift' => null,
            // P3-26: the one-tap buttons (QuickItemsBootstrapSection)
            'quick_items' => [],
        ];

        foreach (app()->tagged(self::CONTRIBUTORS) as $contributor) {
            if ($contributor instanceof PosBootstrapContributor) {
                $data = array_merge($data, $contributor->contribute($user, $branch, $device));
            }
        }

        return $data;
    }

    /**
     * Active stations, in the order the screens list them.
     *
     * @return list<array<string, mixed>>
     */
    private function stations(Branch $branch): array
    {
        return KitchenStation::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (KitchenStation $station) => SetupPresenter::station($station))
            ->values()
            ->all();
    }

    /**
     * The branch's active printers this terminal can use: LAN and Bluetooth ones from any terminal (and from a normal
     * staff sign-in), a USB or built-in one only from the terminal it is attached to.
     *
     * @return list<array<string, mixed>>
     */
    private function printers(Branch $branch, ?Device $device): array
    {
        return Printer::query()
            ->with('device:id,uuid')
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->filter(fn (Printer $printer) => !$printer->connection->isDeviceBound() || ($device !== null && (int) $printer->device_id === (int) $device->id))
            ->map(fn (Printer $printer) => SetupPresenter::printer($printer))
            ->values()
            ->all();
    }

    /**
     * Who works on this branch (store-wide staff and the branch's own): name and role, to pick a waiter. PINs and staff
     * codes stay out; the PIN pad has its own list (GET /terminal/staff).
     *
     * @return list<array{id: int, name: string, role: string, photo_url: string|null}>
     */
    private function staff(Branch $branch): array
    {
        return User::query()
            ->where('store_id', $branch->store_id)
            ->whereIn('role', array_map(fn (UserRole $role) => $role->value, UserRole::terminalRoles()))
            ->where(fn ($query) => $query->where('branch_id', $branch->id)->orWhereNull('branch_id'))
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'role', 'telegram_photo_url'])
            ->map(fn (User $staff) => [
                'id' => (int) $staff->id,
                'name' => (string) $staff->name,
                'role' => $staff->role->value,
                'photo_url' => $staff->telegram_photo_url,
            ])
            ->values()
            ->all();
    }

    /**
     * The printing till's uuid while that terminal is still paired to this branch (or to the whole store); null once it
     * was revoked or moved, so a terminal never waits for a till that is gone.
     */
    public static function activePrintingDevice(Branch $branch, ?string $uuid): ?string
    {
        if ($uuid === null) {
            return null;
        }

        $exists = Device::query()
            ->where('uuid', $uuid)
            ->where('store_id', $branch->store_id)
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->where('branch_id', $branch->id)->orWhereNull('branch_id'))
            ->exists();

        return $exists ? $uuid : null;
    }
}
