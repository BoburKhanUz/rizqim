<?php

namespace App\Domain\Pos\Lan;

use App\Domain\Pos\Setup\PosBootstrap;
use App\Domain\Pos\Setup\PosSettings;
use App\Domain\Pos\Sync\PosSyncApplier;
use App\Enums\DeviceApp;
use App\Enums\DeviceType;
use App\Events\Broadcast\PosLanChangedBroadcast;
use App\Models\Branch;
use App\Models\BranchLanSettings;
use App\Models\Device;
use App\Models\PosShift;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\PermissionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * P6 local bridge of a branch (docs/POS_SPEC.md §5.5, pos/packages/pos_lan/README.md): its secret and key version, its
 * hub, what a terminal gets in GET /pos/bootstrap `lan` (or GET /pos/lan without a staff session), the owner's view,
 * hub choice and key rotation, and whether the server takes an operation relayed by a device.
 *
 * - The secret is made the first time the branch's LAN is asked for; it never leaves the server except in the hub's
 *   own section (`hub_secret`). Every terminal of the branch gets its own device key and the beacon key; a PIN session
 *   also gets its signed offline grant (who is signed in, their permissions, 36 hours). A normal staff or owner sign-in
 *   (no terminal) gets nothing.
 * - The hub: the owner's choice while it is an active till of the branch, else the branch's printing till, else the
 *   till the hub's secret was last given to while it is still active (pairing another till never moves the hub: review
 *   C5), else the branch's first paired cashier till (DeviceApp, sent at pairing), else its first paired active till
 *   whose app is not known (paired before the app was recorded) that has had a shift on it. A waiter's phone is never a
 *   candidate; a terminal of unknown app that never had a shift (a waiter's phone paired before the app was recorded)
 *   is never the default (review S4: only the owner may pick it).
 * - The apps say which app they are (DeviceApp::HEADER): recorded on a terminal that has none; a terminal saying it is
 *   the waiter, kitchen or display app never gets the hub's role or secret, whoever chose it.
 * - Relays: from the current hub, or from the device the hub's secret was last given to, or (for an operation that
 *   happened before the secret moved, within 48 hours) the one before it, while it may still be the hub.
 * - The secret moving is audited (`pos.lan_hub_changed`), and a revoke, a rotation or a hub move is announced to the
 *   branch's terminals (`pos.lan_changed`, PosLanChangedBroadcast): the hub refetches GET /pos/lan at once.
 * - A staff grant ends GRANT_HOURS after the PIN sign-in (the server takes the session's relayed operations for as
 *   long, measured at each operation's time: PosSyncApplier::staff).
 */
final class BranchLan
{
    public const VERSION = 1;

    public const DEFAULT_PORT = 18480;

    /** How far a frame's time may be from the receiver's (pos_lan replay window). */
    public const CLOCK_WINDOW_SECONDS = 120;

    /** A grant is good for as long as the server takes the staff member's offline operations from that device. */
    public const GRANT_HOURS = PosSyncApplier::SESSION_WINDOW_HOURS;

    /** The previous hub's relays are taken this long after the secret moved, for what happened before. */
    public const PREVIOUS_HUB_HOURS = 48;

    /** The `lan` section (GET /pos/bootstrap, GET /pos/lan). */
    public const SECTION = 'array{v: int, branch_id: int, key_id: int, role: ' . LanRole::class . ', device: string, device_key: string, beacon_key: string, hub: array{device: string|null, name: string|null, port: int}, hub_secret: string|null, devices: list<array{uuid: string, type: string, name: string|null}>|null, grant: string|null, clock_window_seconds: int}';

    /** The owner's view (GET/PUT /owner/pos/lan, POST /owner/pos/lan/rotate). */
    public const VIEW = 'array{branch_id: int, key_id: int, rotated_at: string|null, port: int, hub: array{device: string, name: string|null, chosen: bool}|null, candidates: list<array{uuid: string, name: string|null, platform: string|null, app: ' . DeviceApp::class . '|null, last_seen_at: string|null}>}';

    public function __construct(
        private readonly PermissionService $permissions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * The branch's row, made with a new secret the first time.
     */
    public function settings(Branch $branch): BranchLanSettings
    {
        $row = BranchLanSettings::where('branch_id', $branch->id)->first();
        if ($row !== null) {
            return $row;
        }

        try {
            return BranchLanSettings::create([
                'store_id' => $branch->store_id,
                'branch_id' => $branch->id,
                'port' => self::DEFAULT_PORT,
                'key_id' => 1,
                'secret' => base64_encode(LanKeys::newSecret()),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two terminals asked at once: the other one made it
            return BranchLanSettings::where('branch_id', $branch->id)->firstOrFail();
        }
    }

    /**
     * The active tills (POS terminals) of the branch, oldest first: the hub is one of them. A waiter's phone (a POS
     * terminal running the waiter app) never is.
     *
     * @return Collection<int, Device>
     */
    public function candidates(Branch $branch): Collection
    {
        return Device::query()
            ->where('store_id', $branch->store_id)
            ->where('branch_id', $branch->id)
            ->where('type', DeviceType::POS->value)
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('app')->orWhere('app', '!=', DeviceApp::WAITER->value))
            ->orderBy('id')
            ->get();
    }

    /**
     * The branch's hub now: the owner's choice, the printing till, the till that holds the hub's secret, the first
     * cashier till, the first till whose app is not known that has had a shift; null without such a till.
     */
    public function hubOf(Branch $branch, ?BranchLanSettings $settings = null): ?Device
    {
        $settings ??= $this->settings($branch);
        $candidates = $this->candidates($branch);
        if ($settings->hub_device_id !== null && ($chosen = $candidates->firstWhere('id', $settings->hub_device_id)) !== null) {
            return $chosen;
        }

        $printing = $this->printingTill($branch);
        $eligible = $this->eligible($candidates, $printing);
        if ($printing !== null && ($till = $eligible->firstWhere('uuid', $printing)) !== null) {
            return $till;
        }
        if ($settings->issued_hub_device_id !== null && ($issued = $eligible->firstWhere('id', $settings->issued_hub_device_id)) !== null) {
            return $issued;
        }

        return $eligible->first(fn (Device $device) => $device->app === DeviceApp::CASHIER) ?? $eligible->first();
    }

    /**
     * Whether $device may be the branch's hub: the owner's choice, or a till the default may fall on (eligible()).
     */
    public function mayHost(Branch $branch, BranchLanSettings $settings, Device $device): bool
    {
        $candidates = $this->candidates($branch);
        if ($candidates->firstWhere('id', $device->id) === null) {
            return false;
        }

        return (int) $settings->hub_device_id === (int) $device->id
            || $this->eligible($candidates, $this->printingTill($branch))->firstWhere('id', $device->id) !== null;
    }

    /**
     * GET /pos/bootstrap `lan`: for a terminal of this branch only (a PIN session on it); null for a normal sign-in, a
     * store-wide terminal or another branch's.
     *
     * @return array<string, mixed>|null
     */
    public function section(User $user, Branch $branch, ?Device $device): ?array
    {
        if ($device === null || !$this->servesOn($device, $branch)) {
            return null;
        }

        return $this->build($branch, $device, $user, $this->claimedApp($device));
    }

    /**
     * GET /pos/lan: a terminal's own token without a staff session (a customer display); no grant.
     *
     * @return array<string, mixed>|null
     */
    public function deviceSection(Device $device): ?array
    {
        $branch = $device->branch_id !== null ? Branch::find($device->branch_id) : null;
        if ($branch === null || !$this->servesOn($device, $branch)) {
            return null;
        }

        return $this->build($branch, $device, null, $this->claimedApp($device));
    }

    /**
     * Whether the server takes an operation $sender relayed for another device of its branch (docs/POS_SPEC.md §5.5).
     */
    public function acceptsRelayFrom(Device $sender, ?Carbon $occurredAt): bool
    {
        $branch = $sender->branch_id !== null ? Branch::find($sender->branch_id) : null;
        if ($branch === null || $sender->type !== DeviceType::POS || $sender->revoked_at !== null) {
            return false;
        }

        $settings = $this->settings($branch);
        if ($this->hubOf($branch, $settings)?->id === $sender->id) {
            return true;
        }
        // Review S4: the device the secret went to, or the one before it, while it may still be the hub (a terminal
        // that got it before the rules were tightened — a waiter's phone of unknown app — no longer relays)
        if (!$this->mayHost($branch, $settings, $sender)) {
            return false;
        }
        if ($settings->issued_hub_device_id === (int) $sender->id) {
            return true;
        }

        return $settings->previous_hub_device_id === (int) $sender->id
            && $settings->hub_changed_at !== null
            && $settings->hub_changed_at->gte(now()->subHours(self::PREVIOUS_HUB_HOURS))
            && $occurredAt !== null
            && $occurredAt->lte($settings->hub_changed_at);
    }

    // --- owner ---------------------------------------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function view(Branch $branch): array
    {
        $settings = $this->settings($branch);
        $hub = $this->hubOf($branch, $settings);

        return [
            'branch_id' => (int) $branch->id,
            'key_id' => (int) $settings->key_id,
            'rotated_at' => $settings->rotated_at?->toJSON(),
            'port' => (int) $settings->port,
            'hub' => $hub === null ? null : [
                'device' => (string) $hub->uuid,
                'name' => $hub->name,
                'chosen' => $settings->hub_device_id !== null && (int) $settings->hub_device_id === (int) $hub->id,
            ],
            'candidates' => $this->candidates($branch)->map(fn (Device $device) => [
                'uuid' => (string) $device->uuid,
                'name' => $device->name,
                'platform' => $device->platform,
                'app' => $device->app?->value,
                'last_seen_at' => $device->last_seen_at?->toJSON(),
            ])->values()->all(),
        ];
    }

    /**
     * The owner picks the hub (`hub_device_id`: an active till of the branch, null: the default) and the port.
     *
     * @param  array{hub_device_id?: string|null, port?: int}  $data
     * @return array<string, mixed>
     */
    public function update(Branch $branch, array $data, User $actor): array
    {
        $before = $this->view($branch);

        DB::transaction(function () use ($branch, $data) {
            $settings = BranchLanSettings::whereKey($this->settings($branch)->id)->lockForUpdate()->firstOrFail();
            if (array_key_exists('hub_device_id', $data)) {
                $uuid = $data['hub_device_id'];
                $hub = $uuid === null ? null : $this->candidates($branch)->firstWhere('uuid', strtolower((string) $uuid));
                if ($uuid !== null && $hub === null) {
                    throw ValidationException::withMessages(['hub_device_id' => "Markaz shu filialning faol kassasi bo'lishi kerak."]);
                }
                $settings->hub_device_id = $hub?->id;
            }
            if (array_key_exists('port', $data)) {
                $settings->port = (int) $data['port'];
            }
            $settings->save();
        });

        $after = $this->view($branch);
        $this->audit->record('settings.pos_lan_changed', $branch, self::auditable($before), self::auditable($after), [], [
            'store_id' => (int) $branch->store_id, 'branch_id' => (int) $branch->id, 'actor' => $actor,
        ]);
        if (($before['hub']['device'] ?? null) !== ($after['hub']['device'] ?? null)) {
            event(new PosLanChangedBroadcast((int) $branch->id, LanChange::HUB_CHANGED, $after['hub']['device'] ?? null));
        }
        if ($before['port'] !== $after['port']) {
            event(new PosLanChangedBroadcast((int) $branch->id, LanChange::PORT_CHANGED));
        }

        return $after;
    }

    /**
     * A new secret (key_id + 1): every device takes its new keys with its next bootstrap; until then it cannot join the
     * LAN. Audited `pos.lan_key_rotated` (never the key).
     *
     * @return array<string, mixed>
     */
    public function rotate(Branch $branch, User $actor): array
    {
        $from = null;
        $to = null;
        DB::transaction(function () use ($branch, $actor, &$from, &$to) {
            $settings = BranchLanSettings::whereKey($this->settings($branch)->id)->lockForUpdate()->firstOrFail();
            $from = (int) $settings->key_id;
            $to = $from + 1;
            $settings->forceFill([
                'key_id' => $to,
                'secret' => base64_encode(LanKeys::newSecret()),
                'rotated_at' => now(),
                'rotated_by' => $actor->id,
            ])->save();
        });

        $this->audit->record('pos.lan_key_rotated', $branch, ['key_id' => $from], ['key_id' => $to], [], [
            'store_id' => (int) $branch->store_id, 'branch_id' => (int) $branch->id, 'actor' => $actor,
        ]);
        event(new PosLanChangedBroadcast((int) $branch->id, LanChange::KEY_ROTATED));

        return $this->view($branch);
    }

    // --- internals -----------------------------------------------------------------------------------------------------

    private function servesOn(Device $device, Branch $branch): bool
    {
        return $device->isTerminal()
            && $device->revoked_at === null
            && (int) $device->store_id === (int) $branch->store_id
            && $device->branch_id !== null
            && (int) $device->branch_id === (int) $branch->id;
    }

    /**
     * The app the request says it is (DeviceApp::HEADER), recorded on a terminal that has none when it runs on the
     * terminal's type (as at pairing; what was recorded stays). Null: an older app that does not say.
     */
    private function claimedApp(Device $device): ?DeviceApp
    {
        $claimed = DeviceApp::tryFrom((string) request()->header(DeviceApp::HEADER, ''));
        if ($claimed !== null && $device->app === null && $claimed->deviceType() === $device->type) {
            $device->forceFill(['app' => $claimed->value])->saveQuietly();
        }

        return $claimed;
    }

    /**
     * The branch's printing till (its uuid) while it is active.
     */
    private function printingTill(Branch $branch): ?string
    {
        return PosBootstrap::activePrintingDevice($branch, PosSettings::resolve($branch->pos_settings)['printing_device_id'] ?? null);
    }

    /**
     * The candidates the default hub may fall on (review S4, C5): a cashier till, the printing till, or a till whose app
     * is not known (paired before it was recorded) that has had a shift on it — a waiter's phone never opens a shift, so
     * one paired before the app was recorded is never the default hub.
     *
     * @param  Collection<int, Device>  $candidates
     * @return Collection<int, Device>
     */
    private function eligible(Collection $candidates, ?string $printing): Collection
    {
        $unknown = $candidates->filter(fn (Device $device) => $device->app === null && (string) $device->uuid !== $printing)->pluck('id')->all();
        $shifted = $unknown === [] ? [] : PosShift::query()->whereIn('device_id', $unknown)->distinct()->pluck('device_id')->map(fn ($id) => (int) $id)->all();

        return $candidates->filter(fn (Device $device) => $device->app === DeviceApp::CASHIER
            || ($printing !== null && (string) $device->uuid === $printing)
            || in_array((int) $device->id, $shifted, true))->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function build(Branch $branch, Device $device, ?User $user, ?DeviceApp $claimed): array
    {
        $settings = $this->settings($branch);
        $secret = (string) base64_decode((string) $settings->secret, true);
        $hub = $this->hubOf($branch, $settings);
        // Review S4: a terminal saying it runs the waiter, kitchen or display app never takes the hub's role
        $isHub = $hub !== null && (int) $hub->id === (int) $device->id && ($claimed === null || $claimed === DeviceApp::CASHIER);
        if ($isHub) {
            $this->issuedTo($branch, $settings, $device);
        }

        return [
            'v' => self::VERSION,
            'branch_id' => (int) $branch->id,
            'key_id' => (int) $settings->key_id,
            'role' => $isHub ? LanRole::HUB->value : LanRole::CLIENT->value,
            'device' => (string) $device->uuid,
            'device_key' => base64_encode(LanKeys::device($secret, (string) $device->uuid)),
            'beacon_key' => base64_encode(LanKeys::beacon($secret)),
            'hub' => [
                'device' => $hub?->uuid,
                'name' => $hub?->name,
                'port' => (int) $settings->port,
            ],
            'hub_secret' => $isHub ? base64_encode($secret) : null,
            'devices' => $isHub ? $this->terminals($branch) : null,
            'grant' => $user !== null ? $this->grant($secret, $settings, $branch, $device, $user) : null,
            'clock_window_seconds' => self::CLOCK_WINDOW_SECONDS,
        ];
    }

    /**
     * The hub's secret went to $device: the device that had it before is remembered (its relays of what happened before
     * the move are still taken, PREVIOUS_HUB_HOURS). Review S8: audited `pos.lan_hub_changed` (the devices, never the
     * key) and announced to the branch (S5).
     */
    private function issuedTo(Branch $branch, BranchLanSettings $settings, Device $device): void
    {
        if ($settings->issued_hub_device_id === (int) $device->id) {
            return;
        }
        $from = $settings->issued_hub_device_id;
        if ($from !== null) {
            $settings->previous_hub_device_id = $from;
            $settings->hub_changed_at = now();
        }
        $settings->issued_hub_device_id = (int) $device->id;
        $settings->saveQuietly();

        $this->audit->record('pos.lan_hub_changed', $branch,
            ['device' => $from !== null ? Device::whereKey($from)->value('uuid') : null],
            ['device' => (string) $device->uuid],
            ['key_id' => (int) $settings->key_id],
            ['store_id' => (int) $branch->store_id, 'branch_id' => (int) $branch->id, 'actor' => null, 'device' => $device],
        );
        event(new PosLanChangedBroadcast((int) $branch->id, LanChange::HUB_CHANGED, (string) $device->uuid));
    }

    /**
     * The terminals the hub lets in: the branch's active POS, KDS and display terminals.
     *
     * @return list<array{uuid: string, type: string, name: string|null}>
     */
    private function terminals(Branch $branch): array
    {
        return Device::query()
            ->where('store_id', $branch->store_id)
            ->where('branch_id', $branch->id)
            ->whereIn('type', array_map(fn (DeviceType $type) => $type->value, DeviceType::terminals()))
            ->whereNull('revoked_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Device $device) => ['uuid' => (string) $device->uuid, 'type' => $device->type->value, 'name' => $device->name])
            ->values()
            ->all();
    }

    /**
     * The staff member's offline grant on this terminal (pos_lan README §3), signed with the grant key.
     */
    private function grant(string $secret, BranchLanSettings $settings, Branch $branch, Device $device, User $user): string
    {
        // A PIN session on the terminal (the only caller with a device): its staff session token
        $token = $user->currentAccessToken();
        $session = (int) $token->getKey();
        $issued = now();
        // Review C8: the grant ends GRANT_HOURS after the PIN sign-in, as the server's window for the session's relayed
        // operations does (PosSyncApplier::staff, measured at each operation's time)
        $signedIn = $token->created_at ?? $issued;

        return LanKeys::signGrant($secret, [
            'v' => self::VERSION,
            'kid' => (int) $settings->key_id,
            'branch_id' => (int) $branch->id,
            'device' => (string) $device->uuid,
            'staff_id' => (int) $user->id,
            'name' => (string) $user->name,
            'role' => $user->role->value,
            'permissions' => array_map(fn ($permission) => $permission->value, $this->permissions->permissionsFor($user)),
            'session' => $session,
            'iat' => $issued->getTimestamp(),
            'exp' => $signedIn->copy()->addHours(self::GRANT_HOURS)->getTimestamp(),
        ]);
    }

    /**
     * The owner's view without the device list, for the audit.
     *
     * @param  array<string, mixed>  $view
     * @return array<string, mixed>
     */
    private static function auditable(array $view): array
    {
        return ['hub' => $view['hub']['device'] ?? null, 'hub_chosen' => $view['hub']['chosen'] ?? false, 'port' => $view['port']];
    }
}
