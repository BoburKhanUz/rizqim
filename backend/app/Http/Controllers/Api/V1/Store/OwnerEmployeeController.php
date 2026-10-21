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

        $employee->delete();

        return $this->success(null, 'Xodim tizimdan o\'chirildi.');
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
