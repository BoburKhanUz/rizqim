<?php

namespace App\Services\Catalog;

use App\Enums\StopListSubject;
use App\Events\Broadcast\MenuAvailabilityChangedBroadcast;
use App\Models\Branch;
use App\Models\Modifier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StopListEntry;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A branch's stop-list (P1-21): staff switch a product, variant or modifier off on one branch (optionally until a
 * time), checkout refuses it with a 422, and every change is audited and broadcast (menu.availability_changed).
 */
class StopListService
{
    public function __construct(
        protected AuditLogger $audit,
    ) {}

    /**
     * The product, variant or modifier of the branch's store with this id, or a 422.
     */
    public function resolveSubject(Branch $branch, StopListSubject $type, int $id): Model
    {
        $storeId = (int) $branch->store_id;

        $subject = match ($type) {
            StopListSubject::PRODUCT => Product::where('store_id', $storeId)->find($id),
            StopListSubject::VARIANT => ProductVariant::whereHas('product', fn ($query) => $query->where('store_id', $storeId))->find($id),
            StopListSubject::MODIFIER => Modifier::whereHas('group', fn ($query) => $query->where('store_id', $storeId))->find($id),
        };

        if (!$subject) {
            throw ValidationException::withMessages(['id' => "Bu do'konda bunday mahsulot, variant yoki modifikator yo'q."]);
        }

        return $subject;
    }

    /**
     * Puts the subject on the branch's stop-list (or updates its reason/until when already there).
     */
    public function add(Branch $branch, StopListSubject $type, Model $subject, ?string $reason, ?CarbonInterface $until, ?User $actor): StopListEntry
    {
        if ($until !== null && $until->isPast()) {
            throw ValidationException::withMessages(['until' => "Tugash vaqti kelajakda bo'lishi kerak."]);
        }

        return DB::transaction(function () use ($branch, $type, $subject, $reason, $until, $actor) {
            $entry = StopListEntry::updateOrCreate(
                ['branch_id' => $branch->id, 'subject_type' => $type->value, 'subject_id' => $subject->getKey()],
                [
                    'store_id' => $branch->store_id,
                    'reason' => $reason !== null && trim($reason) !== '' ? mb_substr(trim($reason), 0, 255) : null,
                    'until' => $until,
                    'created_by' => $actor?->id,
                ],
            );

            $this->audit->record('menu.stop_list_added', $entry, [], [], [
                'type' => $type->value,
                'subject_id' => (int) $subject->getKey(),
                'name' => $this->nameOf($subject),
                'reason' => $entry->reason,
                'until' => $entry->until?->toJSON(),
            ], ['actor' => $actor, 'store_id' => (int) $branch->store_id, 'branch_id' => (int) $branch->id]);

            event(new MenuAvailabilityChangedBroadcast((int) $branch->store_id, (int) $branch->id, [
                ['type' => $type->value, 'id' => (int) $subject->getKey(), 'available' => false],
            ], 'stop_list'));

            return $entry;
        });
    }

    public function remove(StopListEntry $entry, ?User $actor): void
    {
        DB::transaction(function () use ($entry, $actor) {
            $subject = $entry->subject();

            $this->audit->record('menu.stop_list_removed', $entry, [], [], [
                'type' => $entry->subject_type->value,
                'subject_id' => (int) $entry->subject_id,
                'name' => $subject ? $this->nameOf($subject) : null,
                'reason' => $entry->reason,
            ], ['actor' => $actor, 'store_id' => (int) $entry->store_id, 'branch_id' => (int) $entry->branch_id]);

            $entry->delete();

            event(new MenuAvailabilityChangedBroadcast((int) $entry->store_id, (int) $entry->branch_id, [
                ['type' => $entry->subject_type->value, 'id' => (int) $entry->subject_id, 'available' => true],
            ], 'stop_list'));
        });
    }

    /**
     * Deletes the entries whose `until` passed and tells the branches (scheduled every minute). Returns how many.
     */
    public function expire(): int
    {
        $expired = StopListEntry::query()->whereNotNull('until')->where('until', '<=', now())->orderBy('id')->get();

        foreach ($expired->groupBy('branch_id') as $entries) {
            $first = $entries->first();
            StopListEntry::whereIn('id', $entries->pluck('id'))->delete();

            event(new MenuAvailabilityChangedBroadcast((int) $first->store_id, (int) $first->branch_id, $entries->map(fn (StopListEntry $entry) => [
                'type' => $entry->subject_type->value,
                'id' => (int) $entry->subject_id,
                'available' => true,
            ])->values()->all(), 'stop_list'));
        }

        return $expired->count();
    }

    private function nameOf(Model $subject): ?string
    {
        if ($subject instanceof ProductVariant) {
            $subject->loadMissing('product');

            return trim(($subject->product->name ?? '') . ($subject->name ? " ({$subject->name})" : ''));
        }

        return $subject->getAttribute('name');
    }
}
