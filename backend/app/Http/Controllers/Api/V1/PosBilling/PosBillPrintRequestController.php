<?php

namespace App\Http\Controllers\Api\V1\PosBilling;

use App\Domain\Pos\Billing\BillPrintInbox;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Traits\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Review P6 hardware/LAN (C2): the till's catch-up of the waiters' bill print requests with its own token (auth:device;
 * the till prints them while its screen is locked too). See App\Domain\Pos\Billing\BillPrintInbox.
 */
#[Group('POS: billing')]
class PosBillPrintRequestController extends Controller
{
    use ApiResponse;

    /**
     * Outstanding bill print requests
     *
     * The `bill.print_requested` requests of the last hour this till has not printed yet (named to it, or to no till),
     * oldest first, at most 20: each with the event's fields, the order (as GET /pos/orders/{uuid}) and its bills (as GET
     * /pos/orders/{uuid}/bills), so a till without a staff session (PIN screen) prints the pre-check too. Only open
     * orders and bills that are neither void nor paid. The till reads it on start, after reconnecting, on unlock and when
     * a broadcast arrives while nobody is signed in, then claims each request before printing it
     * (POST /pos/bill-print-requests/{request_id}/printed). A POS terminal's own token; a terminal without a branch gets
     * an empty list.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{requests: list<' . BillPrintInbox::ENTRY . '>}}')]
    public function index(Request $request): JsonResponse
    {
        $device = $this->till($request);

        return $this->success(['requests' => app(BillPrintInbox::class)->pending($device)]);
    }

    /**
     * Claim a bill print request
     *
     * The till takes the request before it prints the pre-check: `claimed: true` — this till prints it (the first to
     * claim, or it claimed it before), `false` — another till of the branch took it (nothing to print). Once claimed it
     * leaves every till's outstanding list. An unknown request of the branch: 404 `request_not_found` (the till prints
     * it anyway). A POS terminal's own token.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{request_id: string, claimed: bool}}')]
    #[HeaderParameter('Idempotency-Key', description: 'Optional: claiming twice changes nothing anyway (the same till gets `claimed: true` again).', type: 'string')]
    public function printed(Request $request, string $requestId): JsonResponse
    {
        $device = $this->till($request);

        $claimed = app(BillPrintInbox::class)->claim($device, $requestId);
        if ($claimed === null) {
            return $this->error("Chop etish so'rovi topilmadi.", ['reason' => 'request_not_found'], 404);
        }

        return $this->success(['request_id' => strtolower($requestId), 'claimed' => $claimed]);
    }

    private function till(Request $request): Device
    {
        $device = $request->user('device');
        abort_unless($device instanceof Device && $device->isTerminal(), 401);
        if ($device->type !== \App\Enums\DeviceType::POS) {
            abort($this->error('Schyotni faqat kassa chop etadi.', ['reason' => 'till_required'], 403));
        }

        return $device;
    }
}
