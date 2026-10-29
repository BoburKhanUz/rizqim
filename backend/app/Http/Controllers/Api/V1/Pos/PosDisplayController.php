<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Domain\Pos\Display\DisplayFeed;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Enums\DeviceType;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Device;
use App\Traits\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P6-14 (A3, docs/POS_SPEC.md 2.14a): the customer display. A paired terminal's own token (auth:device; no staff
 * session: a display has no staff): the display app reads its state and the pickup board, and the till publishes the
 * cart it shows on the second screen. The branch is the terminal's (a terminal paired without one: 422
 * `branch_required`). Gated by the format modules: `customer_display` for the cart, `queue_calling` for the board.
 */
#[Group('POS: customer display', "Mijoz ekrani: joriy chek, to'lov va \"Rahmat\", \"Tayyorlanmoqda / Tayyor\" taxtasi (P6-14).")]
class PosDisplayController extends Controller
{
    use ApiResponse;

    /** The cart a till publishes (and a display reads back). */
    public const CART = 'array{terminal: string, seq: int, session: string|null, state: string, lines: list<array{name: string, qty: string, unit: string|null, price: string, total: string, modifiers: list<string>, note: string|null}>, subtotal: string, discount: string, total: string, queue_number: int|null, payment: array{method: string|null, tendered: string|null, change: string|null, qr: array{payload: string, label: string|null, provider: string|null}|null}|null, updated_at: string}';

    public function __construct(
        protected DisplayFeed $feed,
        protected PosModules $modules,
    ) {}

    /**
     * The display's state
     *
     * What a customer display (or a poll after a missed event) needs: its branch, the till it follows (`till_uuid`, null:
     * the branch's latest cart), which screens the format allows (`modules`) and the current `cart` (null: nothing
     * published yet or the cart expired). The display refetches this on `display.cart` events of `pos-branch.{id}`.
     * A terminal's own token; 422 `branch_required` without a branch.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{branch: array{id: int, name: string}, store: array{id: int, name: string}|null, device: array{uuid: string, type: string, name: string}, till_uuid: string|null, modules: array{customer_display: bool, queue_calling: bool}, server_time: string, cart: ' . self::CART . '|null}}')]
    public function state(Request $request): JsonResponse
    {
        [$device, $branch] = $this->context($request);
        if ($branch === null) {
            return $this->noBranch();
        }

        $till = $device->display_for_device_id !== null ? Device::query()->whereKey($device->display_for_device_id)->value('uuid') : null;

        return $this->success([
            'branch' => ['id' => (int) $branch->id, 'name' => (string) $branch->name],
            'store' => $branch->store ? ['id' => (int) $branch->store->id, 'name' => (string) $branch->store->name] : null,
            'device' => ['uuid' => (string) $device->uuid, 'type' => $device->type->value, 'name' => (string) $device->name],
            'till_uuid' => $till !== null ? (string) $till : null,
            'modules' => [
                'customer_display' => $this->modules->enabled($branch, PosModule::CUSTOMER_DISPLAY),
                'queue_calling' => $this->modules->enabled($branch, PosModule::QUEUE_CALLING),
            ],
            'server_time' => now()->toJSON(),
            'cart' => $this->modules->enabled($branch, PosModule::CUSTOMER_DISPLAY) ? $this->feed->cartFor($device, (int) $branch->id) : null,
        ]);
    }

    /**
     * The pickup board for the display
     *
     * Today's "Tayyorlanmoqda / Tayyor" numbers of the display's branch, the same payload as GET /pos/queue (a terminal's own
     * token instead of a staff session). Refetch on `queue.*` events of `pos-branch.{id}`. Needs `queue_calling`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{business_date: string, preparing: list<' . PosQueueController::ENTRY . '>, ready: list<' . PosQueueController::ENTRY . '>}}')]
    public function queue(Request $request): JsonResponse
    {
        [, $branch] = $this->context($request);
        if ($branch === null) {
            return $this->noBranch();
        }
        $this->modules->assertEnabled($branch, PosModule::QUEUE_CALLING);

        return $this->success(PosQueueController::boardOf($branch));
    }

    /**
     * Publish the cart for the second screen
     *
     * The till tells its customer display what to show: `state` (`idle` empty/welcome, `cart` lines being added,
     * `payment` the amount due and optionally a payment QR, `paid` "Rahmat"), the lines, the totals as decimal strings, the
     * queue number if the order has one and, in `payment`, the tender and a QR (`payment.qr.payload` is the text the display
     * draws as a QR code). `seq` grows with every publish (a late older one is dropped); `session` names the till's app run,
     * so a restarted till (seq from 1) is not mistaken for a stale one. Answers 200 `stored: false` for a stale publish.
     * Fire-and-forget for the till: it never waits for, or fails because of, the display. A POS terminal's own token;
     * needs `customer_display`; the display shows it within a moment (`display.cart` on `pos-branch.{id}`).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{stored: bool}}')]
    public function publish(Request $request): JsonResponse
    {
        [$device, $branch] = $this->context($request);
        if ($branch === null) {
            return $this->noBranch();
        }
        if ($device->type !== DeviceType::POS) {
            return $this->error("Chekni faqat kassa mijoz ekraniga yuboradi.", ['reason' => 'till_required'], 403);
        }
        $this->modules->assertEnabled($branch, PosModule::CUSTOMER_DISPLAY);

        $money = ['required', 'string', 'regex:/^\d{1,12}(\.\d{1,2})?$/'];
        $data = $request->validate([
            'state' => ['required', Rule::in(DisplayFeed::STATES)],
            'seq' => 'required|integer|min:0|max:2000000000',
            'session' => 'nullable|string|max:64',
            'lines' => 'present|array|max:100',
            'lines.*.name' => 'required|string|max:120',
            'lines.*.qty' => ['required', 'string', 'regex:/^\d{1,6}(\.\d{1,3})?$/'],
            'lines.*.unit' => 'nullable|string|max:16',
            'lines.*.price' => $money,
            'lines.*.total' => $money,
            'lines.*.modifiers' => 'nullable|array|max:10',
            'lines.*.modifiers.*' => 'string|max:60',
            'lines.*.note' => 'nullable|string|max:120',
            'subtotal' => $money,
            'discount' => ['nullable', 'string', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'total' => $money,
            'queue_number' => 'nullable|integer|min:1|max:99999',
            'payment' => 'nullable|array',
            'payment.method' => 'nullable|string|max:32',
            'payment.tendered' => ['nullable', 'string', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'payment.change' => ['nullable', 'string', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'payment.qr' => 'nullable|array',
            'payment.qr.payload' => 'required_with:payment.qr|string|max:1024',
            'payment.qr.label' => 'nullable|string|max:60',
            'payment.qr.provider' => 'nullable|string|max:32',
        ]);

        $cart = [
            'state' => $data['state'],
            'seq' => (int) $data['seq'],
            'session' => $data['session'] ?? null,
            'lines' => $data['state'] === 'idle' ? [] : array_map(fn (array $line) => [
                'name' => (string) $line['name'],
                'qty' => (string) $line['qty'],
                'unit' => $line['unit'] ?? null,
                'price' => (string) $line['price'],
                'total' => (string) $line['total'],
                'modifiers' => array_values(array_map('strval', $line['modifiers'] ?? [])),
                'note' => $line['note'] ?? null,
            ], $data['lines']),
            'subtotal' => (string) $data['subtotal'],
            'discount' => (string) ($data['discount'] ?? '0'),
            'total' => (string) $data['total'],
            'queue_number' => isset($data['queue_number']) ? (int) $data['queue_number'] : null,
            'payment' => isset($data['payment']) ? [
                'method' => $data['payment']['method'] ?? null,
                'tendered' => $data['payment']['tendered'] ?? null,
                'change' => $data['payment']['change'] ?? null,
                'qr' => isset($data['payment']['qr']) ? [
                    'payload' => (string) $data['payment']['qr']['payload'],
                    'label' => $data['payment']['qr']['label'] ?? null,
                    'provider' => $data['payment']['qr']['provider'] ?? null,
                ] : null,
            ] : null,
        ];

        return $this->success(['stored' => $this->feed->publish($device, $cart) !== null]);
    }

    /**
     * @return array{0: Device, 1: Branch|null}
     */
    private function context(Request $request): array
    {
        $device = $request->user('device');
        abort_unless($device instanceof Device && $device->isTerminal(), 401);

        $branch = $device->branch_id !== null ? Branch::query()->with('store:id,name')->where('store_id', $device->store_id)->whereKey($device->branch_id)->first() : null;

        return [$device, $branch];
    }

    private function noBranch(): JsonResponse
    {
        return $this->error("Bu qurilma filialga ulanmagan. Uni filialni tanlab qayta ulang.", ['reason' => 'branch_required'], 422);
    }
}
