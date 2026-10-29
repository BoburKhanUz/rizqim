<?php

namespace App\Services\Admin\Customers;

use App\Domain\Pos\Booking\UzPhone;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Admin\AdminPii;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * P4-31: Mijozlar va yordam → Foydalanuvchilar: customers, couriers and store staff of the whole platform, newest
 * first, a cursor page at a time. RIZQIM team accounts (platform_role set, role super_admin / platform) are never
 * listed, opened or acted on here: Jamoa manages them.
 *
 * Search (`q`): by name (any part), or by phone with the AdminSearchController rule (review F6): without
 * admin.pii.view only the whole number (+998XXXXXXXXX however typed) matches, so a masked number cannot be read digit
 * by digit; with it any 4+ digits do, and every number shown is an audited reveal (AdminPii).
 */
class UserDirectory
{
    /** The role filter: one role, or `staff` for every store role but the courier. */
    public const ROLE_FILTERS = ['customer', 'courier', 'staff', 'owner', 'manager', 'cashier', 'waiter', 'cook', 'hostess'];

    public function __construct(
        private readonly AdminPii $pii,
    ) {}

    /**
     * Every account but the RIZQIM team's.
     *
     * @return Builder<User>
     */
    public static function query(): Builder
    {
        return User::query()
            ->whereNull('platform_role')
            ->whereNotIn('role', [UserRole::SUPER_ADMIN->value, UserRole::PLATFORM->value]);
    }

    /**
     * @param  array{q?: string|null, role?: string|null, status?: string|null, store_id?: int|null, per_page?: int|null, cursor?: string|null}  $filters
     * @return CursorPaginator<int, User>
     */
    public function page(User $member, array $filters): CursorPaginator
    {
        $query = self::query()
            ->with(['store:id,name', 'branch:id,name'])
            ->withCount('orders');

        $role = $filters['role'] ?? null;
        if ($role === 'staff') {
            $query->whereIn('role', [UserRole::OWNER->value, UserRole::MANAGER->value, UserRole::CASHIER->value, UserRole::WAITER->value, UserRole::COOK->value, UserRole::HOSTESS->value]);
        } elseif (is_string($role) && $role !== '') {
            $query->where('role', $role);
        }

        if (($filters['status'] ?? null) === 'blocked') {
            $query->whereNotNull('blocked_at');
        } elseif (($filters['status'] ?? null) === 'active') {
            $query->whereNull('blocked_at');
        }

        if (!empty($filters['store_id'])) {
            $query->where('store_id', (int) $filters['store_id']);
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $phone = $this->phoneMatch($member, $q);
            $like = '%' . CustomerFormat::like($q) . '%';
            $query->where(function (Builder $where) use ($like, $phone) {
                $where->whereRaw('lower(name) like ?', [$like]);
                if ($phone !== null) {
                    $where->orWhere($phone);
                }
            });
        }

        return $query->orderByDesc('id')
            ->cursorPaginate(max(1, min(100, (int) ($filters['per_page'] ?? 30))))
            ->withQueryString();
    }

    /**
     * The phone condition, or null when `q` is no phone the member may look up: with admin.pii.view any 4+ digits of
     * the number; without it only the whole number, normalized ("90 123 45 67", "+998 (90) 123-45-67").
     */
    public function phoneMatch(User $member, string $q): ?\Closure
    {
        if ($this->pii->canReveal($member)) {
            $digits = (string) preg_replace('/\D/', '', $q);

            return strlen($digits) >= 4 ? fn (Builder $query) => $query->where('phone', 'like', '%' . $digits . '%') : null;
        }

        $number = UzPhone::normalize($q);
        if ($number === null) {
            return null;
        }

        // Stored as +998XXXXXXXXX; older rows may lack the plus
        return fn (Builder $query) => $query->whereIn('phone', [$number, substr($number, 1)]);
    }

    /**
     * @param  iterable<User>  $users  with store, branch and orders_count loaded
     * @return list<array<string, mixed>>
     */
    public function rows(User $member, iterable $users): array
    {
        $rows = [];
        foreach ($users as $user) {
            $rows[] = [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'role' => $user->role->value,
                'role_label' => $user->role->labelUz(),
                'phone' => $this->pii->phone($member, $user->phone, 'user', (int) $user->id),
                'telegram_linked' => $user->hasTelegram(),
                'store' => $user->store ? ['id' => (int) $user->store->id, 'name' => (string) $user->store->name] : null,
                'branch' => $user->branch ? ['id' => (int) $user->branch->id, 'name' => (string) $user->branch->name] : null,
                'orders_count' => (int) ($user->getAttribute('orders_count') ?? 0),
                'blocked' => $user->isBlocked(),
                'blocked_at' => CustomerFormat::iso($user->blocked_at),
                'created_at' => CustomerFormat::iso($user->created_at),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, string> role => Uzbek label, the roles the list shows
     */
    public static function roleLabels(): array
    {
        $labels = ['staff' => "Ovqatxona xodimlari (barchasi)"];
        foreach ([UserRole::CUSTOMER, UserRole::COURIER, UserRole::OWNER, UserRole::MANAGER, UserRole::CASHIER, UserRole::WAITER, UserRole::COOK, UserRole::HOSTESS] as $role) {
            $labels[$role->value] = $role->labelUz();
        }

        return $labels;
    }

    /**
     * A non-team account by id, or null (team accounts are not found here).
     */
    public function find(int $id): ?User
    {
        return self::query()->whereKey($id)->first();
    }
}
