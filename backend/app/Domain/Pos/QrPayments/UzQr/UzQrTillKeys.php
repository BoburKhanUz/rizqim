<?php

namespace App\Domain\Pos\QrPayments\UzQr;

use App\Domain\Fiscal\FiscalSettings;
use App\Models\Device;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;

/**
 * Wave P: each POS till's own UzQR API key and the store's STIR, for UzQrOperatorGateway.
 *
 * A "till" is a POS terminal (devices, type pos): the device a cashier takes money on is what the operator calls a
 * cash register (kassa). The operator's manual (v1.1) asks for one dynamic QR and one API key per cash register
 * ("Each API key works only with the QR to which it is linked"), and the QR request already keeps the device that
 * made it (pos_qr_payments.device_id). Review 11 M2: the key that created a QR is also kept on it (pos_qr_payments.
 * uzqr_api_key, encrypted), so its later calls (status, cancel, refund, receipt link) use that key even after the
 * owner removed or replaced the till's key; a key may be removed at any time, a revoked till's too.
 * Not the branch's bank terminal (bank_terminal_reports): that is the bank's card reader, not a Rizqim till.
 *
 * The key is encrypted at rest (devices.uzqr_api_key, the `encrypted` cast) and never returned: only whether it is
 * set, its last four characters and when. A save with a masked or empty key keeps the stored one (as
 * stores.payment_settings); `remove` clears it. Changes are audited (devices.uzqr_key_changed) without the key. The
 * STIR is the store's own (stores.tin, set in the fiscal settings): the restaurant's legal entity is the one that
 * registered at UzQR through its bank.
 */
final class UzQrTillKeys
{
    public const VIEW = 'array{uuid: string, name: string|null, branch_id: int|null, has_api_key: bool, api_key_last4: string|null, api_key_set_at: string|null, ready: bool, message: string|null}';

    /** Guess (the key's format is not published): 8–512 printable characters, no spaces. */
    public const KEY_PATTERN = '/^[\x21-\x7E]{8,512}$/';

    public function __construct(private readonly AuditLogger $audit) {}

    public function key(Device $device): ?string
    {
        $key = $device->uzqr_api_key;

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function tin(Store $store): ?string
    {
        $tin = is_string($store->tin) ? trim($store->tin) : '';

        return preg_match(FiscalSettings::TIN_PATTERN, $tin) === 1 ? $tin : null;
    }

    /**
     * Whether a submitted key is the masked one the form shows (or nothing): the stored key is kept.
     */
    public static function keeps(#[\SensitiveParameter] mixed $value): bool
    {
        return $value === null || (is_string($value) && preg_match('/^[\s*•]*([\s*•][\x21-\x7E]{0,4})?$/u', $value) === 1);
    }

    /**
     * Sets (or with null, clears) the till's key; audited when it changed.
     */
    public function set(Device $device, #[\SensitiveParameter] ?string $key, User $actor): void
    {
        if ($key === $this->key($device)) {
            return;
        }
        $before = $this->auditView($device);
        $device->forceFill([
            'uzqr_api_key' => $key,
            'uzqr_api_key_last4' => $key !== null ? substr($key, -4) : null,
            'uzqr_api_key_set_at' => $key !== null ? now() : null,
        ])->save();

        $this->audit->record('devices.uzqr_key_changed', $device, $before, $this->auditView($device), [], ['actor' => $actor, 'store_id' => (int) $device->store_id, 'branch_id' => $device->branch_id !== null ? (int) $device->branch_id : null]);
    }

    /**
     * A till as the owner sees it: never the key.
     *
     * @return array{uuid: string, name: string|null, branch_id: int|null, has_api_key: bool, api_key_last4: string|null, api_key_set_at: string|null, ready: bool, message: string|null}
     */
    public function present(Device $device, ?string $notReady): array
    {
        $has = $this->key($device) !== null;

        return [
            'uuid' => (string) $device->uuid,
            'name' => $device->name,
            'branch_id' => $device->branch_id !== null ? (int) $device->branch_id : null,
            'has_api_key' => $has,
            'api_key_last4' => $has ? $device->uzqr_api_key_last4 : null,
            'api_key_set_at' => $has ? $device->uzqr_api_key_set_at?->toIso8601String() : null,
            'ready' => $notReady === null,
            'message' => $notReady,
        ];
    }

    /**
     * @return array{has_api_key: bool, api_key_last4: string|null}
     */
    private function auditView(Device $device): array
    {
        $has = $this->key($device) !== null;

        return ['has_api_key' => $has, 'api_key_last4' => $has ? $device->uzqr_api_key_last4 : null];
    }
}
