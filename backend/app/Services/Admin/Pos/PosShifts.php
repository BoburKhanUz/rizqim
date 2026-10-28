<?php

namespace App\Services\Admin\Pos;

use App\Domain\Pos\Shifts\ShiftStatus;
use App\Models\CashMovement;
use App\Models\PosShift;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * P4-24 Smenalar: the till shifts of every store, read-only. A cash difference is counted - expected (negative: cash
 * is missing); a forced close is a manager closing the shift without the cashier.
 */
class PosShifts
{
    public const ROW = 'array{uuid: string, status: string, store: array{id: int, name: string}|null, branch: array{id: int, name: string}|null, device: array{uuid: string, name: string|null}|null, business_date: string, opened_at: string, opened_by: array{id: int, name: string}|null, opening_cash: string, closed_at: string|null, closed_by: array{id: int, name: string}|null, expected_cash: string|null, counted_cash: string|null, difference: string|null, has_difference: bool, z_number: int|null, force_closed: bool, close_note: string|null}';

    /**
     * @param  array<string, mixed>  $filters  kind (difference|force_closed|open), store_id, from / to (business dates)
     * @return Builder<PosShift>
     */
    public function query(array $filters): Builder
    {
        $query = PosShift::query();
        match ($filters['kind'] ?? null) {
            'difference' => $query->where('status', ShiftStatus::CLOSED->value)->whereNotNull('difference')->where('difference', '!=', 0),
            'force_closed' => $query->where('force_closed', true),
            'open' => $query->where('status', ShiftStatus::OPEN->value),
            default => null,
        };
        $query
            ->when(filled($filters['store_id'] ?? null), fn (Builder $q) => $q->where('store_id', (int) $filters['store_id']))
            ->when(filled($filters['from'] ?? null), fn (Builder $q) => $q->where('business_date', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn (Builder $q) => $q->where('business_date', '<=', $filters['to']));

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters  the same filters, but the kind
     * @return array{difference: int, force_closed: int, open: int, all: int}
     */
    public function counts(array $filters = []): array
    {
        unset($filters['kind']);

        return [
            'difference' => $this->query([...$filters, 'kind' => 'difference'])->count(),
            'force_closed' => $this->query([...$filters, 'kind' => 'force_closed'])->count(),
            'open' => $this->query([...$filters, 'kind' => 'open'])->count(),
            'all' => $this->query($filters)->count(),
        ];
    }

    /**
     * @param  Builder<PosShift>  $query
     * @return Builder<PosShift>
     */
    public function withRelations(Builder $query): Builder
    {
        return $query->with([
            'store' => fn ($store) => $store->withTrashed()->select(['id', 'name']),
            'branch:id,name',
            'device:id,uuid,name',
            'openedBy:id,name',
            'closedBy:id,name',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function row(PosShift $shift): array
    {
        $person = fn (?User $user) => $user !== null ? ['id' => (int) $user->id, 'name' => (string) $user->name] : null;
        $difference = $shift->difference !== null ? Money::of($shift->difference) : null;

        return [
            'uuid' => (string) $shift->uuid,
            'status' => $shift->status->value,
            'store' => $shift->store ? ['id' => (int) $shift->store->id, 'name' => (string) $shift->store->name] : null,
            'branch' => $shift->branch ? ['id' => (int) $shift->branch->id, 'name' => (string) $shift->branch->name] : null,
            'device' => $shift->device ? ['uuid' => (string) $shift->device->uuid, 'name' => $shift->device->name] : null,
            'business_date' => $shift->business_date->toDateString(),
            'opened_at' => $shift->opened_at->toIso8601String(),
            'opened_by' => $person($shift->openedBy),
            'opening_cash' => Money::of($shift->opening_cash)->toString(),
            'closed_at' => $shift->closed_at?->toIso8601String(),
            'closed_by' => $person($shift->closedBy),
            'expected_cash' => $shift->expected_cash !== null ? Money::of($shift->expected_cash)->toString() : null,
            'counted_cash' => $shift->counted_cash !== null ? Money::of($shift->counted_cash)->toString() : null,
            'difference' => $difference?->toString(),
            'has_difference' => $difference !== null && !$difference->isZero(),
            'z_number' => $shift->z_number !== null ? (int) $shift->z_number : null,
            'force_closed' => (bool) $shift->force_closed,
            'close_note' => $shift->getAttribute('close_note'),
        ];
    }

    /**
     * The shift card: the row, the banknotes counted, the Z report as kept at closing and the manual cash movements.
     *
     * @return array<string, mixed>
     */
    public function detail(PosShift $shift): array
    {
        $forcedBy = $shift->getAttribute('forced_by');
        $movements = CashMovement::query()->where('shift_id', $shift->id)->with('user:id,name')->orderBy('id')->get();

        return [
            ...$this->row($shift),
            'forced_by' => $forcedBy !== null ? User::query()->whereKey($forcedBy)->first(['id', 'name'])?->only(['id', 'name']) : null,
            'counted_breakdown' => $shift->counted_breakdown,
            'summary' => $shift->summary,
            'cash_movements' => $movements->map(fn (CashMovement $movement) => [
                'uuid' => $movement->uuid,
                'type' => $movement->type->value,
                'category' => $movement->category,
                'amount' => Money::of($movement->amount)->toString(),
                'direction' => $movement->type->sign(),
                'note' => $movement->note,
                'user' => $movement->user ? ['id' => (int) $movement->user->id, 'name' => (string) $movement->user->name] : null,
                'created_at' => $movement->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
