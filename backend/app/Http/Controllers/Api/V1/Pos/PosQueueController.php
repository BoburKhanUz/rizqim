<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Domain\Pos\Orders\PickupQueue;
use App\Domain\Pos\Orders\PickupStatus;
use App\Domain\Pos\Orders\PosOrderException;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Domain\Pos\Shifts\QueueNumbers;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Http\Controllers\Api\V1\PosHall\PosHallController;
use App\Models\Branch;
use App\Models\Order;
use App\Models\User;
use Closure;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * P6-13 (A2, docs/POS_SPEC.md 2.14): order numbers and calling for the till, the kitchen display and (stage B) the
 * customer display: the pickup board of today, the till's number blocks, and the number's moves — ready, call again,
 * handed. A normal staff sign-in or a staff PIN session on a paired terminal; the branch is the caller's (a PIN
 * session: its terminal's), another store's or branch's order is a 404. Gated by the `queue_numbers` (blocks) and
 * `queue_calling` (board, ready, call, handed) modules; none of it ever blocks a payment, a bill or a close.
 */
#[Group('POS: queue numbers', "Buyurtma raqami va chaqiruv: kassa oralig'lari, \"Tayyorlanmoqda / Tayyor\" taxtasi, tayyor, qayta chaqirish, berildi (P6-13).")]
class PosQueueController extends PosHallController
{
    /** One number on the pickup board. */
    public const ENTRY = 'array{order_uuid: string, number: int, business_date: string|null, status: \\App\\Domain\\Pos\\Orders\\PickupStatus|null, channel: string, order_status: string, ready_at: string|null, called_at: string|null, call_count: int, handed_at: string|null, opened_at: string|null}';

    /** The till's blocks (GET /pos/bootstrap `queue`). */
    public const STATE = 'array{numbers: bool, calling: bool, business_date: string, block_size: int, low_water: int, blocks: list<array{start: int, end: int, next: int}>, next_number: int|null}';

    /**
     * The pickup board
     *
     * Today's numbers of the branch (its business day) still waiting: `preparing` (oldest first) and `ready` (the
     * longest waiting first), at most 200 each: the customer display's "Tayyorlanmoqda / Tayyor" and the till's
     * calling list. Refetch on `queue.*` events of `pos-branch.{id}`. Needs orders.view; `branch_id` defaults to the
     * caller's (or their terminal's) branch.
     */
    #[QueryParameter('branch_id', description: "The branch; default: the caller's (or their terminal's).", type: 'integer', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{business_date: string, preparing: list<' . self::ENTRY . '>, ready: list<' . self::ENTRY . '>}}')]
    public function board(Request $request): JsonResponse
    {
        $user = $this->staff($request);
        $request->validate(['branch_id' => 'nullable|integer|min:1']);
        $branch = $this->branchFor($request, $user, Permission::ORDERS_VIEW);

        return $this->success(self::boardOf($branch));
    }

    /**
     * The branch's board for its business day (also served to the paired customer display, P6-14).
     *
     * @return array{business_date: string, preparing: list<array<string, mixed>>, ready: list<array<string, mixed>>}
     */
    public static function boardOf(Branch $branch): array
    {
        $date = BusinessDay::of($branch);

        $list = fn (PickupStatus $status, string $order) => Order::query()
            ->where('store_id', $branch->store_id)
            ->where('branch_id', $branch->id)
            ->where('business_date', $date)
            ->where('pickup_status', $status->value)
            ->whereNotIn('status', [OrderStatus::CANCELED->value, OrderStatus::REJECTED->value])
            ->orderBy($order)->orderBy('id')
            ->limit(200)
            ->get()
            ->map(fn (Order $order) => self::entry($order))
            ->values()
            ->all();

        return [
            'business_date' => $date,
            'preparing' => $list(PickupStatus::PREPARING, 'created_at'),
            'ready' => $list(PickupStatus::READY, 'pickup_ready_at'),
        ];
    }

    /**
     * Reserve the till's next number block
     *
     * The till asks for its next block of numbers while online, when it holds fewer than `low_water` unused numbers
     * of today (GET /pos/bootstrap `queue` shows the same); with enough left nothing new is reserved. Blocks are
     * cut one after another for the branch's business day, never overlapping: numbers the till prints from them
     * (online or offline) are never another till's. Needs a staff PIN session on a paired till (422
     * `terminal_required`) and orders.manage.
     */
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::STATE . '}')]
    public function reserve(Request $request, QueueNumbers $numbers): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $device = $this->permissions->terminalOf($user);
        if ($device === null || $device->branch_id === null) {
            return $this->error("Raqam oralig'ini faqat kassa (terminal) oladi.", ['reason' => 'terminal_required'], 422);
        }
        $branch = $this->branchFor($request, $user, Permission::ORDERS_MANAGE);
        if ((int) $branch->id !== (int) $device->branch_id) {
            return $this->error("Raqam oralig'i faqat kassaning o'z filialiga beriladi.", ['reason' => 'terminal_required'], 422);
        }

        try {
            $state = DB::transaction(function () use ($request, $numbers, $branch, $device) {
                $state = $numbers->state($branch, $device, true);
                $this->idempotency->markApplied($request);

                return $state;
            });
        } catch (ConflictHttpException $e) {
            return $this->error($e->getMessage(), ['reason' => 'idempotency_conflict'], 409);
        }

        return $this->success($state, "Raqam oralig'i tayyor.");
    }

    /**
     * Mark a number ready
     *
     * The order is ready for the customer (a counter without a kitchen display, or the cashier overriding it):
     * `queue.ready` on `pos-branch.{id}`, an online pickup order becomes `ready` and its customer is told "Buyurtmangiz
     * tayyor, raqam N". Already ready: unchanged. 422 `not_in_queue` (no number, not a counter order, or calling
     * off), `already_handed`. Needs orders.manage or kitchen.manage.
     */
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ENTRY . '}')]
    public function ready(Request $request, string $uuid, PickupQueue $queue): JsonResponse
    {
        return $this->act($request, $uuid, fn (Order $order, User $user) => $queue->markReady($order, $user), 'Raqam tayyor.');
    }

    /**
     * Call a ready number again
     *
     * Re-announces a ready number: `queue.ready` with `called: true` (the customer display and the till's speaker
     * call it again), `call_count` + 1, `called_at`; an online customer gets the message again. 422 `not_ready` unless
     * it is ready, `not_in_queue`. Needs orders.manage or kitchen.manage.
     */
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ENTRY . '}')]
    public function call(Request $request, string $uuid, PickupQueue $queue): JsonResponse
    {
        return $this->act($request, $uuid, fn (Order $order, User $user) => $queue->call($order, $user), 'Raqam qayta chaqirildi.');
    }

    /**
     * Mark a number handed over
     *
     * The customer took the order: `queue.handed`, the number leaves the board; a POS order's lines are served (its
     * bill and payment are not touched), an online pickup order is delivered. Already handed: unchanged. 422
     * `not_in_queue`. Needs orders.manage or kitchen.manage.
     */
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ENTRY . '}')]
    public function handed(Request $request, string $uuid, PickupQueue $queue): JsonResponse
    {
        return $this->act($request, $uuid, fn (Order $order, User $user) => $queue->hand($order, $user), 'Raqam mijozga berildi.');
    }

    /**
     * @return array<string, mixed>
     */
    public static function entry(Order $order): array
    {
        $queue = PickupQueue::present($order);

        return [
            'order_uuid' => (string) $order->uuid,
            'number' => (int) $order->queue_number,
            'business_date' => $queue['business_date'] ?? null,
            'status' => $queue['status'] ?? null,
            'channel' => $order->channel->value,
            'order_status' => $order->status->value,
            'ready_at' => $queue['ready_at'] ?? null,
            'called_at' => $queue['called_at'] ?? null,
            'call_count' => (int) ($queue['call_count'] ?? 0),
            'handed_at' => $queue['handed_at'] ?? null,
            'opened_at' => $order->created_at?->toJSON(),
        ];
    }

    /**
     * Runs a number's move with the Idempotency-Key, on an order of the caller's branch (POS or online).
     *
     * @param  Closure(Order, User): Order  $change
     */
    private function act(Request $request, string $uuid, Closure $change, string $message): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $order = Str::isUuid($uuid) ? Order::where('uuid', $uuid)->first() : null;
        if (!$order instanceof Order || !$this->scope->allows($user, Permission::ORDERS_VIEW, $order)) {
            throw new HttpResponseException($this->error('Buyurtma topilmadi yoki sizning filialingizga tegishli emas.', [], 404));
        }

        try {
            $order = DB::transaction(function () use ($request, $change, $order, $user) {
                $order = $change($order, $user);
                $this->idempotency->markApplied($request);

                return $order;
            });
        } catch (PosOrderException $e) {
            return $this->error($e->getMessage(), ['reason' => $e->reason] + $e->data, $e->status);
        } catch (ConflictHttpException $e) {
            return $this->error($e->getMessage(), ['reason' => 'idempotency_conflict'], 409);
        }

        return $this->success(self::entry($order->fresh() ?? $order), $message);
    }
}
