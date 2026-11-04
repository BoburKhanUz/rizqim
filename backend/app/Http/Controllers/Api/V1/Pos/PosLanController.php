<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Domain\Pos\Lan\BranchLan;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Traits\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P6 local bridge (docs/POS_SPEC.md §5.5, pos/packages/pos_lan/README.md): a terminal's LAN keys with its own token,
 * for a terminal that has no staff session (a customer display). A PIN session reads the same section, with its grant,
 * in GET /pos/bootstrap `lan`.
 */
#[Group('POS: local bridge', "Lokal ko'prik: internet uzilsa asosiy kassa orqali Wi-Fi'da ishlash (P6).")]
class PosLanController extends Controller
{
    use ApiResponse;

    /**
     * The terminal's LAN keys
     *
     * The same `lan` section as GET /pos/bootstrap, without a staff grant (`grant: null`): the terminal's own device key,
     * the beacon key, the branch's hub and port (the hub also gets `hub_secret` and the terminals it lets in). Only a
     * terminal of a branch with its own token; a store-wide terminal gets `data: null`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BranchLan::SECTION . '|null}')]
    public function show(Request $request): JsonResponse
    {
        $device = $request->user('device');
        if (!$device instanceof Device || !$device->isTerminal()) {
            return $this->error("Faqat terminal o'z tokeni bilan.", ['reason' => 'terminal_required'], 403);
        }

        return $this->success(app(BranchLan::class)->deviceSection($device));
    }
}
