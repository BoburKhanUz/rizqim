<?php

namespace App\Http\Controllers\Api\V1\Menu;

use App\Domain\Pos\Menu\WriteOffReason;
use App\Enums\Permission;
use App\Models\MenuBatchWriteOff;
use App\Support\Quantity;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Owner: batches', "Qozon / partiya qoldig'i: ochish, to'ldirish, tuzatish, yopish (P1-21); keyingi partiya vaqti va hisobdan chiqarish (P6-15). Oshpaz, menejer, ega.")]
class BatchWriteOffController extends MenuStaffController
{
    /**
     * Written-off portions
     *
     * P6-15, module `batch_writeoff`: the branch's write-offs (newest first) between two business days (`from`, `to`,
     * YYYY-MM-DD; default today), with who wrote off, why and on which shift. `reasons` is the list of reasons with
     * their Uzbek labels for a picker. Needs batches.manage or reports.view.
     */
    #[QueryParameter('branch_id', description: 'Required unless the caller is bound to a branch.', type: 'int')]
    #[QueryParameter('from', description: 'First business day, YYYY-MM-DD (default: today).', type: 'string')]
    #[QueryParameter('to', description: 'Last business day, YYYY-MM-DD (default: from).', type: 'string')]
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
        ]);
        $permission = $this->permissions->has($request->user(), Permission::BATCHES_MANAGE) ? Permission::BATCHES_MANAGE : Permission::REPORTS_VIEW;
        $branch = $this->branch($request, $permission);

        $from = $data['from'] ?? app(\App\Domain\Pos\Menu\DailyMenuService::class)->today($branch);
        $to = $data['to'] ?? $from;

        $rows = MenuBatchWriteOff::where('branch_id', $branch->id)
            ->whereBetween('business_date', [$from, $to])
            ->with(['product:id,name', 'variant:id,name', 'writtenOffBy:id,name'])
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        return $this->success([
            'branch_id' => (int) $branch->id,
            'from' => $from,
            'to' => $to,
            'reasons' => array_map(fn (WriteOffReason $reason) => ['key' => $reason->value, 'label' => $reason->label()], WriteOffReason::cases()),
            'write_offs' => $rows->map(fn (MenuBatchWriteOff $row) => [
                'uuid' => $row->uuid,
                'batch_id' => (int) $row->menu_batch_id,
                'product_id' => (int) $row->product_id,
                'product_name' => $row->product?->name,
                'variant_name' => $row->variant?->name,
                'quantity' => Quantity::normalize($row->quantity),
                'unit' => $row->unit,
                'reason' => $row->reason,
                'reason_label' => WriteOffReason::tryFrom($row->reason)?->label() ?? $row->reason,
                'note' => $row->note,
                'business_date' => $row->business_date->format('Y-m-d'),
                'shift_id' => $row->shift_id !== null ? (int) $row->shift_id : null,
                'written_off_by' => $row->writtenOffBy ? ['id' => (int) $row->writtenOffBy->id, 'name' => $row->writtenOffBy->name] : null,
                'created_at' => $row->created_at?->toJSON(),
            ])->values(),
        ]);
    }
}
