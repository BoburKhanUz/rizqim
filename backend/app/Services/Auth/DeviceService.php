<?php

namespace App\Services\Auth;

use App\Enums\DeviceType;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Device;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * A person's own app installs (register / update / list), revoking any device, and which store devices a manager may
 * see and manage.
 */
class DeviceService
{
    public function __construct(
        protected TokenService $tokens,
        protected AuditLogger $audit,
        protected PermissionService $permissions,
    ) {}

    /**
     * Narrow a query of store devices to those $actor may see: terminals (no user_id), the actor's own app installs
     * and those of people ranked below them (owner > manager > other staff). A manager never sees the owner's phone.
     *
     * @param  Builder<Device>  $query
     * @return Builder<Device>
     */
    public function withinRankOf(Builder $query, User $actor): Builder
    {
        $below = array_values(array_map(
            fn (UserRole $role) => $role->value,
            array_filter(UserRole::cases(), fn (UserRole $role) => $role->rank() < $actor->role->rank()),
        ));

        return $query->where(fn (Builder $query) => $query
            ->whereNull('user_id')
            ->orWhere('user_id', $actor->id)
            ->orWhereHas('user', fn (Builder $query) => $query->whereIn('role', $below)));
    }

    /**
     * Whether $actor may rename or revoke the device: devices.manage for it (store, branch) and, for a person's app
     * install, being that person or ranked above them.
     */
    public function canManage(User $actor, Device $device): bool
    {
        if ($device->isRevoked() || !$this->permissions->allows($actor, Permission::DEVICES_MANAGE, $device)) {
            return false;
        }

        if ($device->user_id === null || (int) $device->user_id === (int) $actor->id) {
            return true;
        }

        $person = $device->user;

        return $person !== null && $person->role->rank() < $actor->role->rank();
    }

    /**
     * Register the app install the user is signing in from, or update it when `uuid` names one of their live devices
     * (an unknown or foreign uuid simply registers a new device). The oldest devices beyond the per-user cap are
     * revoked, so a login loop cannot grow the table without bound.
     *
     * @param  array{type: string, uuid?: ?string, name?: ?string, platform?: ?string, app_version?: ?string, push_token?: ?string}  $data
     */
    public function register(User $user, array $data): Device
    {
        $type = DeviceType::from($data['type']);
        if ($type->isTerminal()) {
            throw new \InvalidArgumentException('Terminals are paired with a code, not registered by a user.');
        }

        return DB::transaction(function () use ($user, $data, $type) {
            $device = !empty($data['uuid'])
                ? Device::where('uuid', $data['uuid'])->where('user_id', $user->id)->active()->lockForUpdate()->first()
                : null;

            $attributes = array_filter([
                'type' => $type->value,
                'name' => $data['name'] ?? null,
                'platform' => $data['platform'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'push_token' => $data['push_token'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');

            if ($device) {
                $device->fill($attributes + ['last_seen_at' => now()])->save();
                $this->releasePushTokenElsewhere($device);

                return $device;
            }

            $device = Device::create($attributes + [
                'user_id' => $user->id,
                'store_id' => $user->store_id,
                'branch_id' => $user->branch_id,
                'name' => $data['name'] ?? $type->label(),
                'last_seen_at' => now(),
            ]);

            $this->releasePushTokenElsewhere($device);
            $this->enforceCap($user);

            return $device;
        });
    }

    /**
     * Set (a string) or clear (null) the push token of a device (P1-22). One install has one token, so the same token
     * is taken away from any other device row: after a different person signs in on the same phone, the earlier
     * person's pushes stop reaching it.
     */
    public function setPushToken(Device $device, ?string $token): void
    {
        DB::transaction(function () use ($device, $token) {
            $device->forceFill(['push_token' => $token !== null && $token !== '' ? $token : null, 'last_seen_at' => now()])->save();
            $this->releasePushTokenElsewhere($device);
        });
    }

    private function releasePushTokenElsewhere(Device $device): void
    {
        if (filled($device->push_token)) {
            Device::where('push_token', $device->push_token)->where('id', '!=', $device->id)->update(['push_token' => null]);
        }
    }

    /**
     * Revoke a device: everything bound to it stops working at once. Audited for store devices (terminals, staff apps).
     */
    public function revoke(Device $device, ?User $actor): void
    {
        if ($device->isRevoked()) {
            return;
        }

        DB::transaction(function () use ($device, $actor) {
            $device->forceFill(['revoked_at' => now(), 'revoked_by' => $actor?->id, 'push_token' => null])->save();
            $this->tokens->revokeDevice($device);

            if ($device->store_id !== null) {
                $this->audit->record('device.revoked', $device, [], [], [
                    'type' => $device->type->value,
                    'name' => $device->name,
                ], ['actor' => $actor]);
            }
        });
    }

    public function rename(Device $device, string $name, ?User $actor): void
    {
        $before = $device->name;
        $device->forceFill(['name' => $name])->save();

        $this->audit->record('device.renamed', $device, ['name' => $before], ['name' => $name], [], ['actor' => $actor]);
    }

    private function enforceCap(User $user): void
    {
        $cap = max(1, (int) config('auth.tokens.max_devices_per_user', 20));

        $stale = Device::where('user_id', $user->id)->active()
            ->orderByDesc('last_seen_at')->orderByDesc('id')
            ->offset($cap)->limit(1000)
            ->get();

        foreach ($stale as $device) {
            $this->revoke($device, $user);
        }
    }
}
