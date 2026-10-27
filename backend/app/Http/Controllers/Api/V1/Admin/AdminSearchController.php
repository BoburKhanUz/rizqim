<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Pos\Booking\UzPhone;
use App\Enums\AdminPermission;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use App\Services\Admin\AdminPii;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P4-11: the admin app's Ctrl+K search over the whole platform: stores (name, STIR, phone), orders (code or uuid
 * prefix), users (phone, name) and team members, at most 5 of each, only the types the member may see. Phones are
 * masked unless the member has admin.pii.view (then the reveal is audited).
 *
 * Review F6: by phone, a member without admin.pii.view finds only the exact full number (+998XXXXXXXXX however typed):
 * a fragment search would let them read a masked number digit by digit. With pii.view any 4+ digits match, and every
 * number shown is an audited reveal.
 */
#[Group('Admin')]
class AdminSearchController extends AdminController
{
    private const LIMIT = 5;

    public function __construct(
        private readonly AdminPermissionService $permissions,
        private readonly AdminPii $pii,
    ) {}

    /**
     * Search everything
     *
     * `q` at least 2 characters. Each hit: `type` (store, order, user, member), `id`, `title`, `subtitle` and `href`,
     * the admin app path to open (`/stores/{id}`, `/orders/{uuid}`, `/users/{id}`, `/team/{id}`). Types the member
     * has no permission for (stores.view, orders.view, users.view, team.manage) are left out. By phone: with
     * admin.pii.view any 4+ digits of the number (each number shown is an audited reveal); without it only the whole
     * number (`+998901234567`, `90 123 45 67`...).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<array{type: string, id: int|string, title: string, subtitle: string|null, href: string}>}')]
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'required|string|min:2|max:100',
        ], UzValidation::messages());

        $member = $this->member($request);
        $q = trim($data['q']);
        $digits = (string) preg_replace('/\D/', '', $q);
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtolower($q)) . '%';
        $phone = $this->phoneMatch($member, $q, $digits);

        $results = [];

        if ($this->permissions->has($member, AdminPermission::STORES_VIEW)) {
            array_push($results, ...$this->stores($member, $like, $digits, $phone));
        }
        if ($this->permissions->has($member, AdminPermission::ORDERS_VIEW)) {
            array_push($results, ...$this->orders($q));
        }
        if ($this->permissions->has($member, AdminPermission::USERS_VIEW)) {
            array_push($results, ...$this->users($member, $like, $phone));
        }
        if ($this->permissions->has($member, AdminPermission::TEAM_MANAGE)) {
            array_push($results, ...$this->members($like));
        }

        return $this->success($results);
    }

    /**
     * @return list<array{type: string, id: int, title: string, subtitle: string|null, href: string}>
     */
    private function stores(User $member, string $like, string $digits, ?\Closure $phone): array
    {
        return Store::query()
            ->where(function (Builder $query) use ($like, $digits, $phone) {
                $query->whereRaw('lower(name) like ?', [$like]);
                if (strlen($digits) >= 3) {
                    $query->orWhere('tin', 'like', $digits . '%')
                        ->orWhere('tax_number', 'like', $digits . '%');
                }
                if ($phone !== null) {
                    $query->orWhere($phone);
                }
            })
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'tin', 'tax_number', 'phone'])
            ->map(fn (Store $store) => [
                'type' => 'store',
                'id' => (int) $store->id,
                'title' => (string) $store->name,
                'subtitle' => $this->join([
                    ($tin = $store->tin ?: $store->tax_number) ? 'STIR ' . $tin : null,
                    $this->pii->phone($member, $store->phone, 'store', (int) $store->id),
                ]),
                'href' => '/stores/' . $store->id,
            ])
            ->all();
    }

    /**
     * @return list<array{type: string, id: string, title: string, subtitle: string|null, href: string}>
     */
    private function orders(string $q): array
    {
        $code = ltrim($q, '#');
        $uuidPrefix = preg_match('/^[0-9a-fA-F-]{4,36}$/', $code) === 1 ? strtolower($code) : null;

        return Order::query()
            ->with('store:id,name')
            ->where(function (Builder $query) use ($code, $uuidPrefix) {
                $query->whereRaw('lower(short_code) like ?', [str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtolower($code)) . '%']);
                if ($uuidPrefix !== null) {
                    $query->orWhereRaw('cast(uuid as text) like ?', [$uuidPrefix . '%']);
                }
            })
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get(['id', 'uuid', 'short_code', 'store_id', 'status', 'total_price', 'created_at'])
            ->map(fn (Order $order) => [
                'type' => 'order',
                'id' => (string) $order->uuid,
                'title' => '#' . ($order->short_code ?? substr((string) $order->uuid, 0, 8)),
                'subtitle' => $this->join([
                    $order->store?->name,
                    $order->status->label(),
                    number_format((float) $order->total_price, 0, '.', ' ') . " so'm",
                ]),
                'href' => '/orders/' . $order->uuid,
            ])
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, subtitle: string|null, href: string}>
     */
    private function users(User $member, string $like, ?\Closure $phone): array
    {
        return User::query()
            ->whereNull('platform_role')
            ->where(function (Builder $query) use ($like, $phone) {
                $query->whereRaw('lower(name) like ?', [$like]);
                if ($phone !== null) {
                    $query->orWhere($phone);
                }
            })
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'phone', 'role'])
            ->map(fn (User $user) => [
                'type' => 'user',
                'id' => (int) $user->id,
                'title' => (string) $user->name,
                'subtitle' => $this->join([
                    $this->pii->phone($member, $user->phone, 'user', (int) $user->id),
                    $user->role->labelUz(),
                ]),
                'href' => '/users/' . $user->id,
            ])
            ->all();
    }

    /**
     * @return list<array{type: string, id: int, title: string, subtitle: string|null, href: string}>
     */
    private function members(string $like): array
    {
        return User::query()
            ->whereNotNull('platform_role')
            ->where(fn (Builder $query) => $query->whereRaw('lower(name) like ?', [$like])->orWhereRaw('lower(email) like ?', [$like]))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'email', 'platform_role', 'role'])
            ->map(fn (User $user) => [
                'type' => 'member',
                'id' => (int) $user->id,
                'title' => (string) $user->name,
                'subtitle' => $this->join([$user->email, $user->platform_role?->label()]),
                'href' => '/team/' . $user->id,
            ])
            ->all();
    }

    /**
     * The phone condition of the query, or null when it is not looked up by phone: with admin.pii.view any 4+ digits
     * anywhere in the number; without it only the whole number (normalized: "90 123 45 67", "+998 (90) 123-45-67").
     */
    private function phoneMatch(User $member, string $q, string $digits): ?\Closure
    {
        if ($this->pii->canReveal($member)) {
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
     * @param  list<string|null>  $parts
     */
    private function join(array $parts): ?string
    {
        $parts = array_values(array_filter($parts, fn (?string $part) => $part !== null && $part !== ''));

        return $parts !== [] ? implode(' · ', $parts) : null;
    }
}
