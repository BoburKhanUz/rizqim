<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Writes the store's harakatlar jurnali (audit log). Callers pass what changed; this works out the actor (the signed-in
 * user and/or the terminal), the store and branch, keeps only the fields that actually differ and runs everything
 * through AuditRedactor, so no PIN, token, card or clear phone number is stored.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $before  the subject's fields before the change
     * @param  array<string, mixed>  $after  the same fields after it (only the differing keys are kept)
     * @param  array<string, mixed>  $meta  extra context (reason, note, ...)
     * @param  array{store_id?: ?int, branch_id?: ?int, actor?: ?User, device?: ?Device}  $context
     */
    public function record(string $action, ?Model $subject = null, array $before = [], array $after = [], array $meta = [], array $context = []): AuditLog
    {
        $actor = array_key_exists('actor', $context) ? $context['actor'] : $this->currentUser();
        $device = array_key_exists('device', $context) ? $context['device'] : $this->currentDevice($actor);

        [$before, $after] = $this->diff($before, $after);

        $storeId = $context['store_id'] ?? $this->attribute($subject, 'store_id') ?? $device->store_id ?? $actor->store_id ?? null;
        $branchId = array_key_exists('branch_id', $context)
            ? $context['branch_id']
            : ($this->attribute($subject, 'branch_id') ?? $device?->branch_id);

        return AuditLog::create([
            'user_id' => $actor?->id,
            'device_id' => $device?->id,
            'store_id' => $storeId,
            'branch_id' => $branchId,
            'action' => $action,
            'model_type' => $subject ? $subject->getMorphClass() : null,
            'model_id' => $subject?->getKey(),
            'before' => $before ? AuditRedactor::clean($before) : null,
            'after' => $after ? AuditRedactor::clean($after) : null,
            'payload_json' => $meta ? AuditRedactor::clean($meta) : null,
            'ip' => app()->runningInConsole() && !app()->runningUnitTests() ? null : request()->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function diff(array $before, array $after): array
    {
        if ($before === [] || $after === []) {
            return [$before, $after];
        }

        $keys = array_unique([...array_keys($before), ...array_keys($after)]);
        $changedBefore = [];
        $changedAfter = [];

        foreach ($keys as $key) {
            $old = $this->normalize($before[$key] ?? null);
            $new = $this->normalize($after[$key] ?? null);
            if ($old !== $new) {
                $changedBefore[$key] = $before[$key] ?? null;
                $changedAfter[$key] = $after[$key] ?? null;
            }
        }

        return [$changedBefore, $changedAfter];
    }

    private function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            is_int($value), is_float($value) => (string) (0 + $value),
            is_numeric($value) => (string) (0 + $value), // '50000.00' and 50000 are the same price
            default => $value,
        };
    }

    private function attribute(?Model $subject, string $key): ?int
    {
        $value = $subject?->getAttribute($key);

        return $value !== null ? (int) $value : null;
    }

    private function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * The terminal acting: its own token (device guard), or the device a staff PIN session token is bound to.
     */
    private function currentDevice(?User $actor): ?Device
    {
        // Set by App\Auth\DeviceTokenGuard when a terminal's own token authenticated this request
        $device = app()->runningInConsole() && !app()->runningUnitTests() ? null : request()->attributes->get('device');
        if ($device instanceof Device) {
            return $device;
        }

        $deviceId = $actor ? data_get($actor->currentAccessToken(), 'device_id') : null;

        return $deviceId ? Device::find($deviceId) : null;
    }
}
