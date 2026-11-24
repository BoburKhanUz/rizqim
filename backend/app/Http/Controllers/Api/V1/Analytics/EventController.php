<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Domain\Analytics\EventIngestor;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Wave P, step 2 (docs/ANALYTICS_PLAN.md 4.1, A.4; docs/API.md "Analitika hodisalari"): the Mini App's first-party
 * analytics. Not a tenant route (a customer's events are about many stores) and outside the shared `api` limit: it has
 * its own `analytics-events` limiter, so analytics can never use up the budget of an order or a payment call.
 */
#[Group('Analytics', "Mini App statistikasi: o'z serverimizga yuboriladigan hodisalar (P to'lqini, 2-qadam).")]
class EventController extends Controller
{
    use ApiResponse;

    /** The envelope's fields: anything else (a user id, free text) is a malformed envelope */
    private const ENVELOPE_KEYS = ['session_id', 'app_version', 'platform', 'start_param', 'events'];

    /**
     * Send a batch of Mini App events
     *
     * A signed-in customer's batch of 1-50 events of the session (`session_id`, a UUID the app makes at launch and
     * renews after 30 minutes without events). Each event: `uid` (UUID, the same on a retry), `name` (the list in
     * docs/API.md), `at` (client time, milliseconds since the epoch), the ids the event is about (`store_id`,
     * `product_id`, `category_id`, integers) and its allowlisted `props`. Answers 202 with how many events were
     * accepted and dropped, never which: an event off the allowlist, older than 24 hours, or an `order_placed` of an
     * order that is not the caller's is dropped; staff and RIZQIM team accounts' events are all dropped. A replayed
     * event (same `session_id` and `uid`) is accepted and not stored twice. 422 only for a malformed envelope (unknown
     * top-level field, more than 50 events), 413 for a body over 32 KB, 429 over a limit (`Retry-After`).
     * Nothing identifying is stored beyond the token's user id: no IP address, User-Agent, raw start_param or text.
     */
    #[BodyParameter('session_id', description: 'The Mini App session (UUID).', type: 'string', format: 'uuid', required: true)]
    #[BodyParameter('platform', description: 'Telegram.WebApp.platform (ios, android, tdesktop, macos, web, weba...; anything else is stored as unknown).', type: 'string', required: true)]
    #[BodyParameter('app_version', description: 'The Mini App build, e.g. 1.4.2 (at most 16 of [0-9A-Za-z._+-], otherwise not kept).', type: 'string', required: false)]
    #[BodyParameter('start_param', description: "Telegram's start_param of this launch: only its kind is kept (campaign, referral, store, invite, none, unknown).", type: 'string', required: false)]
    #[BodyParameter('events', description: '1-50 events: {uid, name, at, store_id?, product_id?, category_id?, props?}.', type: 'array<array{uid: string, name: string, at: int, store_id?: int, product_id?: int, category_id?: int, props?: array<string, mixed>}>', required: true)]
    #[ApiDocResponse(202, description: 'Accepted and dropped counts.', type: 'array{success: true, message: string, data: array{accepted: int, dropped: int}}')]
    #[ApiDocResponse(413, description: 'The body is over 32 KB.', type: 'array{success: false, message: string, data: array{reason: "payload_too_large"}}')]
    public function store(Request $request, EventIngestor $ingestor): JsonResponse
    {
        $max = (int) config('analytics.limits.max_body_bytes', 32768);
        if ((int) $request->header('Content-Length', '0') > $max || strlen((string) $request->getContent()) > $max) {
            return $this->error("So'rov hajmi juda katta.", ['reason' => 'payload_too_large'], 413);
        }

        $body = $request->json()->all();
        $validator = Validator::make($body, [
            'session_id' => 'required|string|uuid',
            'platform' => 'required|string|max:32',
            'app_version' => 'nullable|string|max:32',
            'start_param' => 'nullable|string|max:512',
            'events' => 'required|array|list|min:1|max:' . (int) config('analytics.limits.max_events', 50),
        ], [
            'events.max' => "Bir so'rovda ko'pi bilan :max ta hodisa yuboriladi.",
        ]);
        $validator->after(function ($validator) use ($body) {
            foreach (array_diff(array_keys($body), self::ENVELOPE_KEYS) as $key) {
                $validator->errors()->add((string) $key, "Noma'lum maydon.");
            }
        });
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var User $user */
        $user = $request->user();
        /** @var array{session_id: string, platform: string, app_version?: string|null, start_param?: string|null, events: list<mixed>} $batch */
        $batch = $body;

        return $this->success($ingestor->ingest($user, $batch), '', 202);
    }
}
