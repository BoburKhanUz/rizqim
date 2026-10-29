<?php

namespace App\Domain\Pos\Billing;

use App\Domain\Pos\Contracts\ManagerApprovals;
use App\Enums\Permission;
use App\Models\Branch;
use App\Models\Device;
use App\Models\PosApproval;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\AuthTokenException;
use App\Services\Auth\PermissionService;
use App\Services\Auth\StaffPinService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manager approvals on a terminal (POS_SPEC 2.2, P3-18): the staff member on the terminal lacks a permission, a manager
 * types their own PIN on that terminal (POST /pos/approvals), the server answers with a one-time token for that one
 * permission on that one terminal, valid 60 seconds. The action then sends the token (`approval_token`) and consumes
 * it. The PIN check is StaffPinService's (same lockout and failure count as a sign-in); only the token's sha256 is
 * stored; the audit log records both people: who asked (`requested_by`) and who approved (the actor).
 */
final class ManagerApprovalService implements ManagerApprovals
{
    public const TTL_SECONDS = 60;

    /**
     * What a manager's PIN can approve on a terminal: the floor and till actions, never settings, staff or money out of
     * the platform. P6-19: `batches.manage` for the till's write-off of unsold portions (POST /owner/batches/{id}/write-off).
     */
    public const APPROVABLE = [
        'orders.manage', 'orders.cancel', 'orders.discount', 'orders.void_item', 'tables.manage',
        'pos.open_shift', 'pos.close_shift', 'pos.cash_in_out', 'pos.take_payment', 'pos.reprint_bill', 'pos.refund',
        'bookings.manage', 'customer_accounts.manage', 'batches.manage',
        // P6-24: a manager's PIN lets a waiter or cashier hand an open table to another waiter
        'orders.transfer',
        // P6-22: a manager's PIN lets the cashier correct a tip (with a reason)
        'tips.manage',
    ];

    public function __construct(
        private readonly StaffPinService $pins,
        private readonly PermissionService $permissions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return list<string> the approvable permissions this installation knows
     */
    public static function approvable(): array
    {
        return self::APPROVABLE;
    }

    /**
     * Checks the manager's PIN on the terminal and issues the token.
     *
     * @return array{token: string, expires_at: Carbon, manager: User, permission: string}
     *
     * @throws AuthTokenException wrong PIN, unknown person, locked out (StaffPinService)
     * @throws PosActionException the person does not hold the permission here
     */
    public function grant(User $requester, Device $device, Permission $permission, ?int $userId, ?string $staffCode, string $pin): array
    {
        if (!in_array($permission->value, self::approvable(), true)) {
            throw PosActionException::invalid('approval_permission_invalid', "Bu amal uchun menejer tasdig'i berilmaydi.");
        }

        $manager = $this->pins->verify($device, $userId, $staffCode, $pin);

        $subject = $device->branch_id !== null ? Branch::find($device->branch_id) : Store::find($device->store_id);
        if (!$this->permissions->allows($manager, $permission, $subject)) {
            throw PosActionException::forbidden('approver_not_allowed', "{$manager->name} bu amalni tasdiqlay olmaydi: unda bu ruxsat yo'q.");
        }

        $token = Str::random(48);
        $expiresAt = now()->addSeconds(self::TTL_SECONDS);

        DB::transaction(function () use ($token, $expiresAt, $device, $permission, $manager, $requester) {
            PosApproval::create([
                'token_hash' => hash('sha256', $token),
                'store_id' => $device->store_id,
                'branch_id' => $device->branch_id,
                'device_id' => $device->id,
                'permission' => $permission->value,
                'manager_id' => $manager->id,
                'requested_by' => $requester->id,
                'expires_at' => $expiresAt,
            ]);

            $this->audit->record('pos.approval_granted', null, [], [], [
                'permission' => $permission->value,
                'requested_by' => $requester->id,
                'requested_by_name' => $requester->name,
                'approved_by_name' => $manager->name,
            ], ['actor' => $manager, 'device' => $device, 'store_id' => $device->store_id, 'branch_id' => $device->branch_id]);
        });

        return ['token' => $token, 'expires_at' => $expiresAt, 'manager' => $manager, 'permission' => $permission->value];
    }

    public function consume(string $token, string $permission, ?int $deviceId): int
    {
        return DB::transaction(function () use ($token, $permission, $deviceId) {
            $approval = $token === '' ? null : PosApproval::where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            $user = Auth::user();

            if ($approval === null || ($user instanceof User && (int) $user->store_id !== (int) $approval->store_id)) {
                throw PosActionException::forbidden('approval_invalid', "Menejer tasdig'i topilmadi. Menejer PIN'ini qayta kiriting.");
            }
            if ($approval->used_at !== null) {
                throw PosActionException::forbidden('approval_used', "Menejer tasdig'i allaqachon ishlatilgan. Menejer PIN'ini qayta kiriting.");
            }
            if ($approval->expires_at->isPast()) {
                throw PosActionException::forbidden('approval_expired', "Menejer tasdig'ining muddati o'tdi (60 soniya). Menejer PIN'ini qayta kiriting.");
            }
            if ($deviceId === null || (int) $approval->device_id !== $deviceId) {
                throw PosActionException::forbidden('approval_wrong_device', "Menejer tasdig'i boshqa terminal uchun berilgan.");
            }
            if ($approval->permission !== $permission) {
                throw PosActionException::forbidden('approval_wrong_permission', "Menejer tasdig'i boshqa amal uchun berilgan.");
            }

            $approval->forceFill(['used_at' => now(), 'used_by' => $user instanceof User ? $user->id : null])->save();

            $this->audit->record('pos.approval_used', null, [], [], [
                'permission' => $permission,
                'approved_by' => (int) $approval->manager_id,
                'requested_by' => $approval->requested_by,
            ], [
                'actor' => $user instanceof User ? $user : null,
                'device' => Device::find($approval->device_id),
                'store_id' => (int) $approval->store_id,
                'branch_id' => $approval->branch_id !== null ? (int) $approval->branch_id : null,
            ]);

            return (int) $approval->manager_id;
        });
    }

    /**
     * The user may do it themselves (null), or a manager approved it with a token on this terminal (the manager's id).
     *
     * @throws PosActionException 403 when neither (data.reason = approval_required: ask for a manager's PIN)
     */
    public function authorize(User $user, Permission $permission, ?Model $subject, ?string $token, ?Device $device): ?int
    {
        if ($this->permissions->allows($user, $permission, $subject)) {
            return null;
        }

        if ($token === null || $token === '') {
            throw PosActionException::forbidden('approval_required', "Bu amal uchun menejer tasdig'i kerak.", ['permission' => $permission->value]);
        }

        return $this->consume($token, $permission->value, $device?->id);
    }
}
