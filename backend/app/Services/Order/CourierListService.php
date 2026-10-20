<?php

namespace App\Services\Order;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Collection;

class CourierListService
{
    public function __construct(
        protected OrderStatusService $orderStatus
    ) {}

    /**
     * Exactly the employees assign-courier accepts from this owner/manager (couriers, managers and owners of the
     * store/branch), labelled for the courier picker. A branch_id filter applies unless the assigner is tied to a branch.
     *
     * @param array $filters request input; only the presence and value of branch_id matter
     */
    public function forAssigner(User $assigner, array $filters = []): Collection
    {
        $query = $this->orderStatus->assignableStaff((int) $assigner->store_id, $assigner);

        if (($assigner->role !== UserRole::MANAGER || !$assigner->branch_id) && array_key_exists('branch_id', $filters)) {
            $query->where(function ($q) use ($filters) {
                $q->where('branch_id', $filters['branch_id'])
                  ->orWhereNull('branch_id')
                  ->orWhere('role', UserRole::OWNER->value);
            });
        }

        return $query->get(['id', 'name', 'phone', 'telegram_username', 'role', 'branch_id', 'courier_type'])->map(function ($u) {
            $roleLabel = match($u->role->value ?? $u->role) {
                'owner' => 'Ega / Owner',
                'manager' => 'Menejer',
                default => 'Kuryer'
            };
            return [
                'id' => $u->id,
                'name' => "{$u->name} ({$roleLabel})",
                'phone' => $u->phone,
                'telegram_username' => $u->telegram_username,
                'branch_id' => $u->branch_id,
                // P1-24: the restaurant's own courier or the Rizqim fleet
                'courier_type' => $u->courier_type->value,
            ];
        });
    }
}
