<?php

namespace App\Domain\Pos\Orders;

use App\Domain\Pos\Billing\PosActionException;
use App\Domain\Pos\Contracts\ManagerApprovals;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * The waiter rule (POS_SPEC 11, R19): a staff member whose role is waiter changes only their own orders. An order
 * with no waiter becomes theirs with their first change (usually the first round); another waiter's order needs a
 * manager's one-time approval (POST /pos/approvals for `orders.manage`, sent as `approval_token`) given by an owner
 * or a manager. An action that already took such an approval for its own permission (a void after sending, a
 * waiver, a cancel) needs no second one. Owners, managers and cashiers are not affected.
 *
 * Call it with the order row locked, inside the change's transaction.
 */
class WaiterAccess
{
    public function __construct(
        protected PermissionService $permissions,
    ) {}

    /**
     * Returns the approving manager's id when one was needed, else null.
     *
     * @throws PosActionException 403 `approval_required` (permission orders.manage), `approval_*`, `approver_not_allowed`
     */
    public function guard(Order $order, User $actor, ?string $approvalToken, ?int $approvedBy = null): ?int
    {
        if ($actor->role !== UserRole::WAITER) {
            return null;
        }

        $waiterId = $order->waiter_id !== null ? (int) $order->waiter_id : null;
        if ($waiterId === (int) $actor->id) {
            return null;
        }
        if ($waiterId === null) {
            // Unassigned: it is theirs from now on
            $order->forceFill(['waiter_id' => $actor->id])->save();

            return null;
        }

        if ($approvedBy !== null && $this->isManager($approvedBy)) {
            return $approvedBy;
        }

        $token = $approvalToken !== null ? trim($approvalToken) : '';
        if ($token === '' || !app()->bound(ManagerApprovals::class)) {
            throw PosActionException::forbidden('approval_required', "Bu buyurtma boshqa ofitsiantniki: uni o'zgartirish uchun menejer tasdig'i kerak.", [
                'permission' => Permission::ORDERS_MANAGE->value,
                'waiter_id' => $waiterId,
            ]);
        }

        try {
            $managerId = app(ManagerApprovals::class)->consume($token, Permission::ORDERS_MANAGE->value, $this->permissions->terminalOf($actor)?->id);
        } catch (QueryException $e) {
            throw $e;
        } catch (PosActionException $e) {
            throw $e;
        } catch (Throwable) {
            throw PosActionException::forbidden('approval_invalid', "Menejer tasdig'i noto'g'ri, eskirgan yoki boshqa amal uchun.", ['permission' => Permission::ORDERS_MANAGE->value]);
        }

        if (!$this->isManager($managerId)) {
            throw PosActionException::forbidden('approver_not_allowed', "Boshqa ofitsiantning buyurtmasini faqat ega yoki menejer tasdiqlaydi.", ['permission' => Permission::ORDERS_MANAGE->value]);
        }

        return $managerId;
    }

    private function isManager(int $userId): bool
    {
        $role = User::whereKey($userId)->value('role');
        $role = $role instanceof UserRole ? $role : UserRole::tryFrom((string) $role);

        return in_array($role, [UserRole::OWNER, UserRole::MANAGER], true);
    }
}
