<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Str;
use App\Traits\ApiResponse;
use App\Enums\UserRole;

class OwnerEmployeeController extends Controller
{
    use ApiResponse;

    /**
     * Get list of employees (Managers, Couriers) for the current store.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$this->isStoreOwner($user)) {
            return $this->error('Ruxsat etilmagan', [], 403);
        }

        $employees = User::where('store_id', $user->store_id)
            ->whereIn('role', [UserRole::MANAGER, UserRole::COURIER])
            ->with('branch:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

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
            'role' => 'required|in:manager,courier',
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
     * A manager/courier of the owner's own store: never the owner himself or a global (storeless) account.
     */
    private function storeEmployee(User $owner, mixed $employeeId): ?User
    {
        return User::where('store_id', $owner->store_id)
            ->whereIn('role', [UserRole::MANAGER, UserRole::COURIER])
            ->where('id', '!=', $owner->id)
            ->whereKey((int) $employeeId)
            ->first();
    }
}
