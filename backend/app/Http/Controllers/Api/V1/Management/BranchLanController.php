<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Lan\BranchLan;
use App\Enums\Permission;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P6 local bridge (docs/POS_SPEC.md §5.5): the owner's view of a branch's LAN: which till is the hub, the port, the key
 * version, and the key rotation. settings.manage; never from a terminal PIN session; a branch-bound caller works on
 * their own branch. The key itself is never shown here: terminals get theirs in their own bootstrap.
 */
#[Group('Owner: local bridge', "Filialning lokal ko'prigi: markaz kassa, port, kalitni yangilash (P6).")]
class BranchLanController extends ManagementController
{
    /**
     * The branch's local bridge
     *
     * `hub`: the till that runs the bridge (`chosen`: the owner picked it; otherwise the printing till, else the first
     * paired till), `candidates`: the branch's active tills, `port` (default 18480), `key_id` and `rotated_at`.
     */
    #[QueryParameter('branch_id', description: 'The branch (a branch-bound caller: their own).', type: 'int')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BranchLan::VIEW . '}')]
    public function show(Request $request): JsonResponse
    {
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE);

        return $this->success(app(BranchLan::class)->view($branch));
    }

    /**
     * Pick the hub or the port
     *
     * `hub_device_id`: an active till (uuid) of the branch, null: back to the default. `port`: 1024–65535. Send only
     * what changes. Audited (settings.pos_lan_changed).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BranchLan::VIEW . '}')]
    public function update(Request $request): JsonResponse
    {
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE);
        $data = $request->validate([
            'hub_device_id' => ['sometimes', 'nullable', 'uuid'],
            'port' => ['sometimes', 'integer', 'min:1024', 'max:65535'],
        ], UzValidation::messages(), [
            'hub_device_id' => 'Markaz kassa',
            'port' => 'Port',
        ]);

        return $this->success(app(BranchLan::class)->update($branch, $data, $request->user()), "Lokal ko'prik sozlamalari saqlandi.");
    }

    /**
     * Rotate the branch's LAN key
     *
     * A new secret (`key_id` + 1): every terminal takes its new keys with its next bootstrap (a sign-in or restart) and
     * cannot join the LAN until then. Use it when a device was lost. Audited (pos.lan_key_rotated).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BranchLan::VIEW . '}')]
    public function rotate(Request $request): JsonResponse
    {
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE);

        return $this->success(app(BranchLan::class)->rotate($branch, $request->user()), "Lokal tarmoq kaliti yangilandi: qurilmalar uni keyingi kirishda oladi.");
    }
}
