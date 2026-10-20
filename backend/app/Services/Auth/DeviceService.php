<?php

namespace App\Services\Auth;

use App\Enums\DeviceType;
use App\Models\Device;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * A person's own app installs (register / update / list) and revoking any device.
 */
class DeviceService
{
    public function __construct(
        protected TokenService $tokens,
        protected AuditLogger $audit,
    ) {}

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

                return $device;
            }

            $device = Device::create($attributes + [
                'user_id' => $user->id,
                'store_id' => $user->store_id,
                'branch_id' => $user->branch_id,
                'name' => $data['name'] ?? $type->label(),
                'last_seen_at' => now(),
            ]);

            $this->enforceCap($user);

            return $device;
        });
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
