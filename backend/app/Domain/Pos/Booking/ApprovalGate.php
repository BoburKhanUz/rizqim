<?php

namespace App\Domain\Pos\Booking;

use App\Domain\Pos\Contracts\ManagerApprovals;
use App\Enums\Permission;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * Who approved a manager-only action: the signed-in staff member when they hold the permission themselves (for the
 * subject's branch), otherwise the manager whose one-time PIN approval token (POST /pos/approvals, P3-18) is consumed
 * here. Call it inside the transaction of the action, so a refused action leaves the token unused.
 */
class ApprovalGate
{
    public function __construct(
        protected PermissionService $permissions,
    ) {}

    public function approver(User $actor, string $permission, ?string $token, ?int $deviceId, mixed $subject = null): int
    {
        $known = Permission::tryFrom($permission);
        if ($known !== null && $this->permissions->allows($actor, $known, $subject)) {
            return (int) $actor->id;
        }

        if ($token === null || trim($token) === '') {
            throw new BookingException("Bu amal uchun menejer tasdig'i kerak (PIN).", 'approval_required', 403, ['permission' => $permission]);
        }

        try {
            return app(ManagerApprovals::class)->consume($token, $permission, $deviceId);
        } catch (QueryException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new BookingException("Menejer tasdig'i yaroqsiz yoki muddati o'tgan. Qayta tasdiqlating.", 'approval_invalid', 403, ['permission' => $permission]);
        }
    }
}
