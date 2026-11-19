<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use App\Enums\CourierType;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Str;
use App\Traits\ApiResponse;
use App\Enums\UserRole;
use App\Services\Auth\TokenService;
use App\Support\Tenancy\TenantRls;
use Illuminate\Support\Facades\DB;

#[Group('Owner: employees', "Xodimlar: taklif havolasi, filialga o'tkazish, o'chirish (ega).")]
class OwnerEmployeeController extends Controller
{
    use ApiResponse;

    /**
     * Get list of employees (managers, cashiers, waiters, cooks, couriers) for the current store.
     *
     * The store's staff as users, newest first, with their branch and, for one who has not joined yet, the invite
     * token of their link.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<array{id: int, uuid: string, store_id: int|null, name: string, email: string|null, telegram_id: string|null, telegram_username: string|null, telegram_photo_url: string|null, phone: string|null, role: UserRole, email_verified_at: string|null, created_at: string|null, updated_at: string|null, deleted_at: string|null, address: string|null, phone_verified_at: string|null, branch_id: int|null, referred_by: int|null, balance: string, pin_set_at: string|null, staff_code: string|null, courier_type: CourierType, branch: array{id: int, name: string}|null, telegram_invite_token: string|null}>}')]
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$this->isStoreOwner($user)) {
            return $this->error('Ruxsat etilmagan', [], 403);
        }

        $employees = User::where('store_id', $user->store_id)
            ->whereIn('role', UserRole::assignableStaffRoles())
            ->with('branch:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            // The owner shares these invite links; everywhere else the token stays hidden (User::$hidden)
            ->each->makeVisible('telegram_invite_token');

        return $this->success($employees);
    }

    /**
     * Generate an invite link for a new employee.
     */
    public function generateInvite(Request $request)
    {
        $user = $request->user();

        if (!$this->isStoreOwner($user)) {
            return $this->error('Ruxsat etilmagan', [], 403);
        }

        $request->validate([
            'role' => 'required|in:' . implode(',', array_map(fn (UserRole $role) => $role->value, UserRole::assignableStaffRoles())),
            'name' => 'required|string|max:255',
            'branch_id' => 'required|exists:branches,id',
        ]);

        // Verify branch belongs to Owner's store
        $branch = \App\Models\Branch::where('store_id', $user->store_id)
            ->where('id', $request->branch_id)
            ->first();

        if (!$branch) {
            return $this->error('Noto\'g\'ri filial tanlandi.', [], 422);
        }

        $token = 'emp_' . Str::random(32);

        $employee = User::create([
            'store_id' => $user->store_id,
            'branch_id' => $request->branch_id,
            'name' => $request->name,
            'role' => $request->role,
            'telegram_invite_token' => $token,
            // We set an empty dummy telegram_id so that standard validation passes if required, 
            // but we can make it nullable in the DB or use a fake one until claimed.
            // Since telegram_id is not strictly unique if null? Wait, telegram_id is unique.
            // Let's use a temporary fake ID, and when they join, we update it.
            'telegram_id' => 'temp_' . uniqid(),
        ]);

        $employee->load('branch');

        try {
            app(\App\Services\Notifications\TelegramNotificationService::class)->sendEmployeeInvite($employee, $user);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send employee invite to Telegram: " . $e->getMessage());
        }

        $botUsername = $user->store->telegram_bot_username ?? config('services.telegram.bot_username');
        $inviteLink = "https://t.me/{$botUsername}?start=invite_{$token}";

        return $this->success([
            'employee' => $employee,
            'invite_link' => $inviteLink,
        ], 'Taklifnoma muvaffaqiyatli yaratildi.');
    }

    /**
     * Transfer an employee to another branch.
     */
    public function updateBranch(Request $request, $employeeId)
    {
        $user = $request->user();

        if (!$this->isStoreOwner($user)) {
            return $this->error('Ruxsat etilmagan', [], 403);
        }

        $request->validate([
            'branch_id' => 'required|exists:branches,id',
        ]);

        $employee = $this->storeEmployee($user, $employeeId);

        if (!$employee) {
            return $this->error('Xodim topilmadi', [], 404);
        }

        // Verify branch belongs to the store
        $branch = \App\Models\Branch::where('store_id', $user->store_id)
            ->where('id', $request->branch_id)
            ->first();

        if (!$branch) {
            return $this->error('Noto\'g\'ri filial tanlandi.', [], 422);
        }

        $oldBranchId = $employee->branch_id;
        $employee->branch_id = $request->branch_id;
        $employee->save();

        // Write an audit entry for the transfer
        \App\Models\AuditLog::create([
            'user_id' => $user->id,
            'action' => 'employee_transfer',
            'model_type' => User::class,
            'model_id' => $employee->id,
            'payload_json' => [
                'employee_name' => $employee->name,
                'old_branch_id' => $oldBranchId,
                'new_branch_id' => $employee->branch_id,
            ],
        ]);

        return $this->success($employee, 'Xodim filialga muvaffaqiyatli o\'tkazildi.');
    }

    /**
     * Delete an employee.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        if (!$this->isStoreOwner($user)) {
            return $this->error('Ruxsat etilmagan', [], 403);
        }

        $employee = $this->storeEmployee($user, $id);

        if (!$employee) {
            return $this->error('Xodim topilmadi', [], 404);
        }

        // M4 stage 3 (security review H1): a person is one users row, and deleting it deletes what they did as a
        // CUSTOMER at any store: orders.user_id is ON DELETE CASCADE, and bills, payments, refunds and QR payments go
        // with the orders. Foreign-key actions run as the table owner, so no store guard and no row-level security
        // policy sees them. Such a person is taken out of the store instead (no role, store, PIN or session) and stays
        // the customer they also are. The same for staff who did anything in the store: the delete's ON DELETE SET
        // NULL actions would erase WHO from the store's own money journal. Only an account with no history is deleted
        if ($this->hasHistory($employee)) {
            DB::transaction(function () use ($employee) {
                $employee->forceFill([
                    'role' => UserRole::CUSTOMER, 'store_id' => null, 'branch_id' => null, 'staff_code' => null,
                    'pin_hash' => null, 'pin_set_at' => null, 'telegram_invite_token' => null,
                ])->save();
                app(TokenService::class)->revokeAllForUser($employee);
            });
        } else {
            $employee->delete();
        }

        return $this->success(null, 'Xodim tizimdan o\'chirildi.');
    }

    /**
     * What deleting the users row would take away. As a CUSTOMER at ANY store (ON DELETE CASCADE on user_id): orders
     * (and, from them, the money tables), complaints, ratings. As STAFF (ON DELETE SET NULL, or CASCADE for work
     * sessions): the name on what they did. The cheap, indexed questions first: the audit journal names the actor of
     * nearly everything done at a till or in the panel (audit_logs.user_id), then deliveries (orders.courier_id), work
     * sessions and tips; then the money columns that have no index of their own, within the person's store:
     * orders.waiter_id, pos_shifts.opened_by / closed_by, cash_movements.user_id, payments.received_by,
     * pos_refunds.user_id. Asked as the login role (TenantRls::asApp) and without the store scope: the owner's request
     * is bound to their own store, and a customer's rows are the other stores'. Only "exists" comes back.
     */
    private function hasHistory(User $employee): bool
    {
        $id = $employee->id;
        $own = fn (string $table) => DB::table($table)->where('store_id', $employee->store_id);

        return TenantRls::asApp(fn () => DB::table('orders')->where('user_id', $id)->exists()
            || DB::table('complaints')->where('user_id', $id)->exists()
            || DB::table('store_ratings')->where('user_id', $id)->exists()
            || DB::table('audit_logs')->where('user_id', $id)->exists()
            || DB::table('orders')->where('courier_id', $id)->exists()
            || DB::table('staff_work_sessions')->where('user_id', $id)->exists()
            || DB::table('pos_tips')->where('staff_id', $id)->exists()
            || $own('orders')->where('waiter_id', $id)->exists()
            || $own('pos_shifts')->where(fn ($q) => $q->where('opened_by', $id)->orWhere('closed_by', $id))->exists()
            || $own('cash_movements')->where('user_id', $id)->exists()
            || $own('payments')->where('received_by', $id)->exists()
            || $own('pos_refunds')->where('user_id', $id)->exists());
    }

    private function isStoreOwner(User $user): bool
    {
        return $user->role === UserRole::OWNER && $user->store_id !== null;
    }

    /**
     * A staff member (not an owner) of the owner's own store: never the owner himself or a global (storeless) account.
     */
    private function storeEmployee(User $owner, mixed $employeeId): ?User
    {
        return User::where('store_id', $owner->store_id)
            ->whereIn('role', UserRole::assignableStaffRoles())
            ->where('id', '!=', $owner->id)
            ->whereKey((int) $employeeId)
            ->first();
    }
}
