<?php

namespace App\Services\Idempotency;

use App\Models\Device;
use App\Models\IdempotencyKey;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

/**
 * The Idempotency-Key header (P1-21), for any endpoint that creates something (POST /orders now, POS sync later):
 *
 *  - the first request with a key claims it (a row; the unique index on scope + actor + key is the lock) and runs;
 *  - a successful (2xx) response is stored for config('menu.idempotency.ttl_hours') (24): the same user or terminal
 *    sending the same key with the same body gets it back unchanged (header Idempotent-Replayed: true), nothing runs
 *    again; any other outcome releases the key, so a retry runs again (nothing was created);
 *  - the same key with a different body: 409;
 *  - a repeat arriving while the first still runs waits for it (up to wait_seconds), then replays; if the first is
 *    still running after that, 409. A key held by a request that died is free again after lock_seconds;
 *  - an endpoint whose work commits in a transaction calls markApplied() inside it (POST /orders does): the key is
 *    then marked used together with what was created, so a request that died after committing, or one still running
 *    past lock_seconds, is never taken over and run twice (P1-34). Each run owns its claim by its locked_until value:
 *    a run whose claim was taken over cannot commit (markApplied throws) nor release the new owner's key.
 *
 * Keys are per scope (endpoint) and per actor (the user, else the terminal); another user's key never collides.
 */
class IdempotencyService
{
    public const HEADER = 'Idempotency-Key';

    public const REPLAYED_HEADER = 'Idempotent-Replayed';

    /** The request attribute holding this run's claim: {id, locked_until} (the raw column value it owns). */
    public const CLAIM_ATTRIBUTE = 'idempotency.claim';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, string $scope, Closure $next): Response
    {
        $key = $request->headers->get(self::HEADER);
        if ($key === null || $key === '') {
            return $next($request);
        }

        if (!preg_match('/^[\x21-\x7E]{1,255}$/', $key)) {
            return $this->error("Idempotency-Key noto'g'ri: 1-255 ta bo'shliqsiz lotin belgi bo'lishi kerak (masalan UUID).", 400);
        }

        $actor = $this->actorOf($request);
        if ($actor === null) {
            return $next($request);
        }

        $hash = $this->hashOf($request);
        $deadline = microtime(true) + max(0, (int) config('menu.idempotency.wait_seconds', 10));

        while (true) {
            $claimed = $this->claim($scope, $actor, $key, $hash);
            if ($claimed !== null) {
                return $this->run($claimed, $request, $next);
            }

            $existing = $this->find($scope, $actor, $key);
            if ($existing === null) {
                if (microtime(true) >= $deadline) {
                    return $this->error("Shu kalit bilan yuborilgan so'rov hali bajarilmoqda. Birozdan so'ng qayta urinib ko'ring.", 409);
                }

                usleep(50_000);

                continue; // released or expired meanwhile: try to claim it again
            }

            if (!hash_equals($existing->request_hash, $hash)) {
                return $this->error("Bu Idempotency-Key boshqa so'rov uchun ishlatilgan. Yangi so'rov uchun yangi kalit yuboring.", 409);
            }

            if ($existing->response_status !== null) {
                return $this->replay($existing);
            }

            if ($this->takeOverStale($existing)) {
                return $this->run($existing->refresh(), $request, $next);
            }

            if (microtime(true) >= $deadline) {
                return $this->error("Shu kalit bilan yuborilgan so'rov hali bajarilmoqda. Birozdan so'ng qayta urinib ko'ring.", 409);
            }

            usleep(100_000);
        }
    }

    /**
     * @return array{type: string, id: int}|null the user (also a staff PIN session), else the paired terminal
     */
    private function actorOf(Request $request): ?array
    {
        $user = $request->user();
        if ($user instanceof User) {
            return ['type' => 'user', 'id' => (int) $user->id];
        }

        $device = $request->user('device');
        if ($device instanceof Device) {
            return ['type' => 'device', 'id' => (int) $device->id];
        }

        return null;
    }

    /**
     * The request as it matters: method, path and input with keys sorted, so key order or JSON spacing never makes
     * the same request look different.
     */
    private function hashOf(Request $request): string
    {
        $input = $request->all();
        $this->sortKeys($input);

        return hash('sha256', json_encode([
            'method' => $request->getMethod(),
            'path' => '/' . ltrim($request->path(), '/'),
            'input' => $input,
        ], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    private function sortKeys(array &$value): void
    {
        if (!array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortKeys($item);
            } elseif (is_object($item)) {
                $item = get_class($item); // an uploaded file: its presence, not its bytes
            }
        }
    }

    /**
     * Inserts the key as "in progress" unless it exists (an expired one is removed first). Null when taken.
     *
     * @param  array{type: string, id: int}  $actor
     */
    private function claim(string $scope, array $actor, string $key, string $hash): ?IdempotencyKey
    {
        $where = ['scope' => $scope, 'actor_type' => $actor['type'], 'actor_id' => $actor['id'], 'key' => $key];

        IdempotencyKey::where($where)->where('expires_at', '<=', now())->delete();

        $now = now();
        $inserted = IdempotencyKey::query()->insertOrIgnore($where + [
            'request_hash' => $hash,
            'locked_until' => $now->copy()->addSeconds((int) config('menu.idempotency.lock_seconds', 60)),
            'expires_at' => $now->copy()->addHours(max(1, (int) config('menu.idempotency.ttl_hours', 24))),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $inserted === 1 ? IdempotencyKey::where($where)->first() : null;
    }

    /**
     * @param  array{type: string, id: int}  $actor
     */
    private function find(string $scope, array $actor, string $key): ?IdempotencyKey
    {
        return IdempotencyKey::query()
            ->where(['scope' => $scope, 'actor_type' => $actor['type'], 'actor_id' => $actor['id'], 'key' => $key])
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Marks this request's key used, inside the transaction that commits what the request created (so both commit or
     * neither does). From then on the key is never taken over: a repeat replays the response, or gets 409 while it is
     * not stored yet. Throws 409 (rolling the transaction back) when this run's claim was taken over meanwhile. A
     * request without an Idempotency-Key has no claim: nothing to do.
     */
    public function markApplied(Request $request): void
    {
        $claim = $request->attributes->get(self::CLAIM_ATTRIBUTE);
        if (!is_array($claim)) {
            return;
        }

        $marked = IdempotencyKey::whereKey($claim['id'])
            ->whereNull('response_status')
            ->where('locked_until', $claim['locked_until'])
            ->update(['locked_until' => null, 'updated_at' => now()]);

        if ($marked !== 1) {
            throw new ConflictHttpException("Shu kalit bilan yuborilgan so'rov boshqa urinishda bajarilmoqda. Birozdan so'ng qayta urinib ko'ring.");
        }

        $request->attributes->set(self::CLAIM_ATTRIBUTE, ['applied' => true] + $claim);
    }

    /**
     * A key whose request died while running (never answered, past locked_until) is claimed by this request. A key
     * marked used (markApplied: locked_until null) never is.
     */
    private function takeOverStale(IdempotencyKey $existing): bool
    {
        if ($existing->locked_until === null || $existing->locked_until->isFuture()) {
            return false;
        }

        return IdempotencyKey::whereKey($existing->id)
            ->whereNull('response_status')
            ->where('locked_until', '<=', now())
            ->update(['locked_until' => now()->addSeconds((int) config('menu.idempotency.lock_seconds', 60)), 'updated_at' => now()]) === 1;
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    private function run(IdempotencyKey $claimed, Request $request, Closure $next): Response
    {
        $lockedUntil = $claimed->getRawOriginal('locked_until');
        $request->attributes->set(self::CLAIM_ATTRIBUTE, ['id' => (int) $claimed->id, 'locked_until' => $lockedUntil]);

        // This run's claim: still the one it took (not taken over), not answered yet
        $own = fn (bool $applied) => IdempotencyKey::whereKey($claimed->id)
            ->whereNull('response_status')
            ->when($applied, fn ($query) => $query->whereNull('locked_until'), fn ($query) => $query->where('locked_until', $lockedUntil));

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            // Nothing was committed under the key: release it (unless another run owns it by now)
            $own(false)->delete();

            throw $e;
        }

        $store = [
            'response_status' => $response->getStatusCode(),
            'response_body' => (string) $response->getContent(),
            'response_content_type' => mb_substr((string) $response->headers->get('Content-Type', 'application/json'), 0, 100),
            'locked_until' => null,
            'updated_at' => now(),
        ];

        $applied = (bool) data_get($request->attributes->get(self::CLAIM_ATTRIBUTE), 'applied');

        if ($applied && $own(true)->update($store) === 1) {
            // Marked used with what it created (markApplied): whatever the answer, it is the one a repeat gets
            return $response;
        }

        if ($response->isSuccessful()) {
            $own(false)->update($store);
        } else {
            // Nothing was created: the same key may be used for the retry
            $own(false)->delete();
        }

        return $response;
    }

    private function replay(IdempotencyKey $stored): Response
    {
        return response((string) $stored->response_body, (int) $stored->response_status)
            ->header('Content-Type', $stored->response_content_type ?: 'application/json')
            ->header(self::REPLAYED_HEADER, 'true');
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'data' => []], $status);
    }
}
