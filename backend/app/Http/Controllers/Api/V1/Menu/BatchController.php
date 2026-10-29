<?php

namespace App\Http\Controllers\Api\V1\Menu;

use App\Domain\Pos\Billing\ManagerApprovalService;
use App\Domain\Pos\Menu\WriteOffReason;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Enums\Permission;
use App\Models\MenuBatch;
use App\Models\PosShift;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Catalog\BatchService;
use App\Support\Quantity;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[Group('Owner: batches', "Qozon / partiya qoldig'i: ochish, to'ldirish, tuzatish va yopish (P1-21). Oshpaz, menejer, ega.")]
class BatchController extends MenuStaffController
{
    /**
     * A branch's batches
     *
     * Open batches first (with what is left), then the latest closed ones. Branch-bound staff always get their own
     * branch.
     */
    #[QueryParameter('branch_id', description: 'Required unless the caller is bound to a branch.', type: 'int')]
    #[QueryParameter('include_closed', description: 'Also the last 50 closed batches.', type: 'bool')]
    public function index(Request $request): JsonResponse
    {
        $permission = $this->permissions->has($request->user(), Permission::BATCHES_MANAGE) ? Permission::BATCHES_MANAGE : Permission::MENU_VIEW;
        $branch = $this->branch($request, $permission);

        $open = MenuBatch::where('branch_id', $branch->id)->open()->with(['product:id,name', 'variant:id,name,unit'])->orderBy('started_at')->get();
        $closed = $request->boolean('include_closed')
            ? MenuBatch::where('branch_id', $branch->id)->whereNotNull('closed_at')->with(['product:id,name', 'variant:id,name,unit'])->latest('closed_at')->limit(50)->get()
            : collect();

        return $this->success([
            'branch_id' => (int) $branch->id,
            'batches' => $open->concat($closed)->map(fn (MenuBatch $batch) => $this->present($batch))->values(),
        ]);
    }

    /**
     * Open a batch
     *
     * What the branch cooked of a product (`product_variant_id` null: the batch serves every variant, each using its
     * `batch_consumption` per unit, e.g. 0.7 for a "0.7 porsiya"). One open batch per branch and product/variant: 422
     * otherwise.
     */
    public function store(Request $request, BatchService $batches): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer',
            'product_id' => 'required|integer',
            'product_variant_id' => 'nullable|integer',
            'quantity' => 'required|numeric|gt:0|max:999999',
            'label' => 'nullable|string|max:100',
        ]);

        $branch = $this->branch($request, Permission::BATCHES_MANAGE);
        $product = $this->product($request, (int) $data['product_id']);
        $variant = isset($data['product_variant_id'])
            ? ProductVariant::where('product_id', $product->id)->find((int) $data['product_variant_id'])
            : null;
        if (isset($data['product_variant_id']) && !$variant) {
            throw ValidationException::withMessages(['product_variant_id' => 'Variant bu mahsulotga tegishli emas.']);
        }

        $batch = $batches->open($branch, $product, $variant, $this->quantity($data['quantity'], 'quantity'), $data['label'] ?? null, $request->user());

        return $this->success($this->present($batch->load(['product:id,name', 'variant:id,name,unit'])), 'Partiya ochildi.', 201);
    }

    /**
     * Top up or correct a batch
     *
     * `add`: more was cooked (produced and left both grow). `quantity_left`: set what is left (a recount). One of
     * them is required.
     */
    public function adjust(Request $request, int $id, BatchService $batches): JsonResponse
    {
        $data = $request->validate([
            'add' => 'nullable|required_without:quantity_left|numeric|gt:0|max:999999',
            'quantity_left' => 'nullable|required_without:add|numeric|min:0|max:999999',
            'reason' => 'nullable|string|max:255',
        ]);

        $batch = $this->batch($request, $id);
        $batch = $batches->adjust(
            $batch,
            isset($data['add']) ? $this->quantity($data['add'], 'add') : null,
            isset($data['quantity_left']) ? $this->quantity($data['quantity_left'], 'quantity_left') : null,
            $data['reason'] ?? null,
            $request->user(),
        );

        return $this->success($this->present($batch->load(['product:id,name', 'variant:id,name,unit'])), 'Partiya yangilandi.');
    }

    /**
     * Close a batch
     *
     * A batch-tracked product is "tugadi" on the branch until a new batch opens. Closing a closed batch is a no-op.
     */
    public function close(Request $request, int $id, BatchService $batches): JsonResponse
    {
        $batch = $batches->close($this->batch($request, $id), $request->user());

        return $this->success($this->present($batch->load(['product:id,name', 'variant:id,name,unit'])), 'Partiya yopildi.');
    }

    /**
     * Write off unsold portions
     *
     * P6-15, module `batch_writeoff`: takes `quantity` off what the batch still holds, with a `reason` (unsold, spoiled,
     * staff_meal, dropped, expired, other; `other` needs a `note`). More than what is left: 422. `close` also closes
     * the batch (the end-of-day "write off the rest"). Done from a terminal, it belongs to that terminal's open shift
     * and is listed on its Z report; from the panel it appears in the reports only. Send an Idempotency-Key so a retry
     * does not write the same portions off twice. Audited (`menu.batch_written_off`, with `approved_by` when a manager
     * approved it). Needs batches.manage; P6-19: on a terminal (staff PIN session) a cashier without it sends a manager's
     * `approval_token` for batches.manage (`POST /pos/approvals`; one use, 60 seconds, this terminal only), else 403
     * `approval_required` (`data.permission`). A refused write-off (422) does not use the token up.
     */
    public function writeOff(Request $request, int $id, BatchService $batches, PosModules $modules, ManagerApprovalService $approvals): JsonResponse
    {
        $data = $request->validate([
            'quantity' => 'required|numeric|gt:0|max:999999',
            'reason' => ['required', 'string', Rule::in(WriteOffReason::values())],
            'note' => 'nullable|string|max:255',
            'close' => 'nullable|boolean',
            'approval_token' => 'nullable|string|max:100',
        ]);

        /** @var User $user */
        $user = $request->user();
        // A terminal's write-off lands on its open shift (the Z report); a panel one on none
        $device = $this->permissions->terminalOf($user);
        // On a terminal a manager may approve it with their PIN; elsewhere the caller needs the permission
        $batch = $this->batch($request, $id, authorize: $device === null);
        $modules->assertEnabled($batch->branch()->firstOrFail(), PosModule::BATCH_WRITEOFF);
        $shift = $device !== null ? PosShift::where('device_id', $device->id)->where('status', 'open')->first() : null;
        $quantity = $this->quantity($data['quantity'], 'quantity');

        // A terminal acts on its own branch's batches only (a manager's approval is for this terminal's branch)
        if ($device !== null && $device->branch_id !== null && (int) $device->branch_id !== (int) $batch->branch_id) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException($this->error('Ruxsat etilmagan', [], 403));
        }

        // One transaction: a write-off the batch refuses (422) leaves the manager's approval unused
        $approvedBy = null;
        $writeOff = DB::transaction(function () use ($batches, $batch, $quantity, $data, $shift, $user, $device, $approvals, &$approvedBy) {
            $approvedBy = $device !== null
                ? $approvals->authorize($user, Permission::BATCHES_MANAGE, $batch, $data['approval_token'] ?? null, $device)
                : null;

            return $batches->writeOff(
                $batch,
                $quantity,
                WriteOffReason::from($data['reason']),
                $data['note'] ?? null,
                $shift,
                $user,
                (bool) ($data['close'] ?? false),
                $approvedBy,
            );
        });

        return $this->success([
            'write_off' => [
                'uuid' => $writeOff->uuid,
                'quantity' => Quantity::normalize($writeOff->quantity),
                'unit' => $writeOff->unit,
                'reason' => $writeOff->reason,
                'note' => $writeOff->note,
                'business_date' => $writeOff->business_date->format('Y-m-d'),
                'shift_id' => $writeOff->shift_id !== null ? (int) $writeOff->shift_id : null,
                // P6-19: the manager whose PIN approved it on the terminal (null: the caller's own permission)
                'approved_by' => $approvedBy,
            ],
            'batch' => $this->present($batch->fresh(['product:id,name', 'variant:id,name,unit']) ?? $batch),
        ], 'Hisobdan chiqarildi.', 201);
    }

    /**
     * The store's batch; with $authorize, the caller must hold batches.manage on its branch (403 otherwise).
     */
    private function batch(Request $request, int $id, bool $authorize = true): MenuBatch
    {
        $batch = MenuBatch::where('store_id', $this->storeId($request))->find($id);
        if (!$batch) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException($this->error('Partiya topilmadi.', [], 404));
        }

        if ($authorize) {
            $this->authorizeFor($request, Permission::BATCHES_MANAGE, $batch);
        }

        return $batch;
    }

    private function quantity(mixed $value, string $field): Quantity
    {
        $quantity = Quantity::tryOf($value);
        if ($quantity === null) {
            throw ValidationException::withMessages([$field => "Miqdor ko'pi bilan 3 ta kasr xonali son bo'lishi kerak."]);
        }

        return $quantity;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(MenuBatch $batch): array
    {
        return [
            'id' => (int) $batch->id,
            'uuid' => $batch->uuid,
            'branch_id' => (int) $batch->branch_id,
            'product_id' => (int) $batch->product_id,
            'product_name' => $batch->product?->name,
            'product_variant_id' => $batch->product_variant_id !== null ? (int) $batch->product_variant_id : null,
            'variant_name' => $batch->variant?->name,
            'unit' => $batch->variant?->unit->value,
            'label' => $batch->label,
            'quantity_produced' => Quantity::normalize($batch->quantity_produced),
            'quantity_left' => Quantity::normalize($batch->quantity_left),
            // P6-15: portions taken off with a reason (menu_batch_writeoffs)
            'written_off_quantity' => Quantity::normalize($batch->written_off_quantity ?? 0),
            'is_open' => $batch->closed_at === null,
            'started_at' => $batch->started_at->toJSON(),
            'sold_out_at' => $batch->sold_out_at?->toJSON(),
            'closed_at' => $batch->closed_at?->toJSON(),
        ];
    }
}
