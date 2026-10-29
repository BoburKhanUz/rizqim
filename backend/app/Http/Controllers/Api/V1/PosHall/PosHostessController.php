<?php

namespace App\Http\Controllers\Api\V1\PosHall;

use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Models\Branch;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * P6-24/P6-26: shared plumbing of the hostess endpoints (waitlist, table states): the PosHallController scoping and
 * Idempotency-Key rules, and a write that commits together with its key.
 */
abstract class PosHostessController extends PosHallController
{
    /**
     * Runs a write in one transaction with the request's Idempotency-Key (required) and answers $respond with its
     * result.
     *
     * @template T
     *
     * @param  Closure(): T  $write
     * @param  Closure(T): JsonResponse  $respond
     */
    protected function atomically(Request $request, Closure $write, Closure $respond): JsonResponse
    {
        $this->requireIdempotencyKey($request);

        try {
            $result = DB::transaction(function () use ($request, $write) {
                $result = $write();
                $this->idempotency->markApplied($request);

                return $result;
            });
        } catch (ConflictHttpException $e) {
            return $this->error($e->getMessage(), ['reason' => 'idempotency_conflict'], 409);
        }

        return $respond($result);
    }

    /**
     * The route's `pos.module` gate lets a store-wide caller through when ANY branch of the store has the module; the
     * record's own branch is checked here (403 `module_disabled` / `plan_feature_missing`).
     */
    protected function requireModule(Branch|int|null $branch, PosModule $module): void
    {
        $branch = is_int($branch) ? Branch::find($branch) : $branch;
        if ($branch !== null) {
            app(PosModules::class)->assertEnabled($branch, $module);
        }
    }
}
