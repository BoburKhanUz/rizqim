<?php

namespace App\Http\Controllers\Api\V1\PosHall;

use App\Domain\Pos\Orders\PosOrderException;
use App\Domain\Pos\Orders\PosOrderPresenter;
use App\Enums\OrderChannel;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Order;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Idempotency\IdempotencyService;
use App\Services\Order\StaffOrderScope;
use App\Support\RowId;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * P3-12: shared scoping of the POS hall endpoints (/pos/floor, /pos/orders/*). A normal staff sign-in or a staff PIN
 * session on a paired terminal; the branch is the caller's (a PIN session: its terminal's; StaffOrderScope), another
 * store's or branch's order is a 404. Every change needs an Idempotency-Key (428 without) and the order's `version`
 * (409 when stale), and commits together with the key (IdempotencyService::markApplied).
 */
abstract class PosHallController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected StaffOrderScope $scope,
        protected PermissionService $permissions,
        protected IdempotencyService $idempotency,
        protected PosOrderPresenter $presenter,
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
     * The branch of the request: `branch_id`, else the caller's (or their terminal's) branch; the caller must hold
     * $permission there (403), another store's branch is a 404.
     */
    protected function branchFor(Request $request, User $user, Permission $permission): Branch
    {
        $value = $request->input('branch_id', $request->query('branch_id'));
        if ($value === null || $value === '') {
            $value = $this->permissions->actingBranchId($user);
        }

        $id = RowId::parse(is_int($value) ? $value : (is_string($value) ? $value : null));
        if ($id === null) {
            throw new HttpResponseException($this->error('Filialni tanlang (branch_id).', ['errors' => ['branch_id' => ['Filialni tanlang.']]], 422));
        }

        $branch = Branch::with('store')->where('store_id', $user->store_id)->find($id);
        if (!$branch instanceof Branch) {
            throw new HttpResponseException($this->error('Filial topilmadi.', [], 404));
        }
        if (!$this->scope->allowsBranch($user, $permission, $branch)) {
            throw new HttpResponseException($this->error('Ruxsat etilmagan', [], 403));
        }

        return $branch;
    }

    /**
     * A POS order the caller holds $permission for (store, branch, terminal branch); 404 otherwise.
     */
    protected function order(User $user, string $uuid, Permission $permission): Order
    {
        $order = Str::isUuid($uuid) ? Order::where('uuid', $uuid)->first() : null;

        if (!$order instanceof Order || !$this->scope->allows($user, $permission, $order)) {
            throw new HttpResponseException($this->error('Buyurtma topilmadi yoki sizning filialingizga tegishli emas.', [], 404));
        }
        if (!$order->channel->isPos()) {
            throw new HttpResponseException($this->error("Bu buyurtma kassa (POS) buyurtmasi emas: uni bu yerda o'zgartirib bo'lmaydi.", ['reason' => 'not_pos_order'], 422));
        }

        return $order;
    }

    /**
     * POS changes are always retried safely: the Idempotency-Key header is required.
     */
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
     * Runs a change in one transaction with the Idempotency-Key, and answers with the order as the till shows it.
     *
     * @param  Closure(): Order  $change
     */
    protected function change(Request $request, Closure $change, string $message, int $status = 200): JsonResponse
    {
        try {
            $order = DB::transaction(function () use ($request, $change) {
                $order = $change();
                $this->idempotency->markApplied($request);

                return $order;
            });
        } catch (PosOrderException $e) {
            return $this->error($e->getMessage(), ['reason' => $e->reason] + $e->data, $e->status);
        } catch (ConflictHttpException $e) {
            return $this->error($e->getMessage(), ['reason' => 'idempotency_conflict'], 409);
        }

        return $this->success($this->presenter->order($order->fresh() ?? $order), $message, $status);
    }
}
