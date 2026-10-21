<?php

namespace App\Http\Controllers\Api\V1\PosBilling;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\PosShift;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Idempotency\IdempotencyService;
use App\Services\Order\StaffOrderScope;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * P3-18: shared scoping of the bill, payment, refund, shift and approval endpoints (/pos/*). A normal staff sign-in or
 * a staff PIN session on a paired terminal; the branch is the caller's (a PIN session: its terminal's, StaffOrderScope
 * and PermissionService::actingBranchId, failing closed); another store's or branch's order, bill or shift is a 404.
 * Every change needs an Idempotency-Key (428 without) and commits together with it (IdempotencyService::markApplied).
 */
abstract class PosBillingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected StaffOrderScope $scope,
        protected PermissionService $permissions,
        protected IdempotencyService $idempotency,
    ) {}

    protected function staff(Request $request): User
    {
        $user = $request->user();
        if (!$user instanceof User || !$this->scope->isStoreStaff($user)) {
            throw new HttpResponseException($this->error('Ruxsat etilmagan', [], 403));
        }

        return $user;
    }

    /**
     * The paired terminal of a staff PIN session (null for a normal sign-in).
     */
    protected function terminal(User $user): ?Device
    {
        return $this->permissions->terminalOf($user);
    }

    protected function order(User $user, string $uuid, Permission $permission = Permission::ORDERS_VIEW): Order
    {
        $order = Str::isUuid($uuid) ? Order::where('uuid', $uuid)->first() : null;
        if (!$order instanceof Order || !$this->scope->allows($user, $permission, $order)) {
            throw new HttpResponseException($this->error('Buyurtma topilmadi yoki sizning filialingizga tegishli emas.', [], 404));
        }

        return $order;
    }

    /**
     * @return array{0: OrderBill, 1: Order}
     */
    protected function bill(User $user, string $uuid): array
    {
        $bill = Str::isUuid($uuid) ? OrderBill::where('uuid', $uuid)->first() : null;
        $order = $bill !== null ? Order::find($bill->order_id) : null;
        if (!$bill instanceof OrderBill || !$order instanceof Order || !$this->scope->allows($user, Permission::ORDERS_VIEW, $order)) {
            throw new HttpResponseException($this->error('Schyot topilmadi yoki sizning filialingizga tegishli emas.', [], 404));
        }

        return [$bill, $order];
    }

    protected function shift(User $user, string $uuid): PosShift
    {
        $shift = Str::isUuid($uuid) ? PosShift::where('uuid', $uuid)->where('store_id', $user->store_id)->first() : null;
        $branchIds = $this->scope->branchIds($user);
        if (!$shift instanceof PosShift || ($branchIds !== null && !in_array((int) $shift->branch_id, $branchIds, true))) {
            throw new HttpResponseException($this->error('Smena topilmadi yoki sizning filialingizga tegishli emas.', [], 404));
        }

        return $shift;
    }

    protected function requireIdempotencyKey(Request $request): void
    {
        $key = $request->headers->get(IdempotencyService::HEADER);
        if ($key === null || $key === '') {
            throw new HttpResponseException($this->error(
                "Idempotency-Key sarlavhasi majburiy: har bir amal uchun yangi UUID yuboring va qayta urinishda o'shasini ishlating.",
                ['reason' => 'idempotency_key_required'],
                428,
            ));
        }
    }

    /**
     * Marks the request's Idempotency-Key used inside the transaction that commits the change.
     */
    protected function applied(Request $request): Closure
    {
        return fn () => $this->idempotency->markApplied($request);
    }

    protected function optionalString(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
