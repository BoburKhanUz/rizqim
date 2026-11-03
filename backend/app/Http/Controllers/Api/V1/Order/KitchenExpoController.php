<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Domain\Pos\Kitchen\KitchenAssembly;
use App\Domain\Pos\Orders\PosOrderException;
use App\Enums\Permission;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P6-33 (B6, module `kds_expo`): the expo (assembly, "yig'ish") station's bump on the kitchen display (docs/API.md
 * "KDS v2"). Same callers and branch scoping as the line bumps (StaffOrderController): kitchen.manage, a normal staff
 * sign-in or a staff PIN session on the expo terminal.
 */
#[Group('Kitchen')]
class KitchenExpoController extends StaffOrderController
{
    /**
     * Assemble an order (expo bump)
     *
     * Every station cooked its part: the expo puts the order together and bumps it. A takeaway, pickup or delivery
     * order becomes `ready` (the customer is told, couriers of a delivery called), a counter order's number is called
     * (`queue.ready`: the board and the customer display); `kitchen.order_assembled` goes to the staff channels.
     * Only for an order that goes through the expo (the branch has the module and an active expo station, its lines
     * were cooked on two or more stations; otherwise 422 `assembly_not_required`: the last line bump made it ready);
     * 422 `stations_not_ready` while a line is still cooking, `kitchen_status` once it left the kitchen. Assembled
     * already: 200 unchanged. A line recalled (or a new one bumped) undoes the assembly. Returns the ticket.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::TICKET . '}')]
    #[ApiDocResponse(422, type: 'array{success: false, message: string, data: array{reason: string}}', description: '`assembly_not_required`, `stations_not_ready` yoki `kitchen_status`')]
    public function assembled(Request $request, string $uuid, KitchenAssembly $assembly): JsonResponse
    {
        $user = $this->user($request);
        $order = $this->find($user, $uuid, Permission::KITCHEN_MANAGE);

        try {
            $result = $assembly->assemble($order, $user);
        } catch (PosOrderException $e) {
            return $this->error($e->getMessage(), ['reason' => $e->reason], $e->status);
        }

        foreach ($result['statuses'] as $status) {
            $this->alerts->statusChanged($result['order'], $status->value);
        }

        $fresh = $result['order']->fresh() ?? $result['order'];

        return $this->success($this->presenter->ticket($fresh), $result['changed'] ? "Buyurtma yig'ildi." : "O'zgarish yo'q.");
    }
}
