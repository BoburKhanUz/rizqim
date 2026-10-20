<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AssignUserRoleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:assign-role
        {telegram_id : Telegram user ID (the person must have opened the Mini App at least once)}
        {role : super_admin, owner, manager, cashier, waiter, cook, courier or customer}
        {--store= : Store ID or UUID (required for store staff: owner, manager, cashier, waiter, cook, courier)}
        {--branch= : Branch ID inside that store (optional, for staff other than the owner)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Explicitly assign a role to a Telegram user (audited bootstrap path for staff and super admins)';


    public function handle(): int
    {
        $telegramId = trim((string) $this->argument('telegram_id'));
        $role = UserRole::tryFrom(strtolower(trim((string) $this->argument('role'))));
        $storeOption = $this->option('store');
        $branchOption = $this->option('branch');

        if (!ctype_digit($telegramId)) {
            $this->error("Noto'g'ri telegram_id: faqat raqamlardan iborat bo'lishi kerak.");
            return self::FAILURE;
        }

        if (!$role) {
            $this->error("Noto'g'ri rol. Mumkin bo'lgan qiymatlar: " . implode(', ', array_column(UserRole::cases(), 'value')));
            return self::FAILURE;
        }

        $needsStore = in_array($role, UserRole::storeRoles(), true);
        $store = null;
        $branch = null;

        if ($needsStore) {
            if (blank($storeOption)) {
                $this->error("'{$role->value}' roli uchun --store (do'kon ID yoki UUID) majburiy.");
                return self::FAILURE;
            }

            $store = ctype_digit((string) $storeOption)
                ? Store::find((int) $storeOption)
                : Store::where('uuid', $storeOption)->first();

            if (!$store) {
                $this->error("Do'kon topilmadi: {$storeOption}");
                return self::FAILURE;
            }

            if (!blank($branchOption)) {
                $branch = Branch::where('store_id', $store->id)->whereKey((int) $branchOption)->first();

                if (!$branch) {
                    $this->error("Filial topilmadi yoki bu do'konga tegishli emas: {$branchOption}");
                    return self::FAILURE;
                }
            }
        } elseif (!blank($storeOption) || !blank($branchOption)) {
            $this->error("--store/--branch faqat do'kon xodimlari (owner, manager, cashier, waiter, cook, courier) rollari uchun ishlatiladi.");
            return self::FAILURE;
        }

        $accounts = User::where('telegram_id', $telegramId)->orderBy('id')->get();

        if ($accounts->isEmpty()) {
            $this->error("Telegram ID {$telegramId} bilan foydalanuvchi topilmadi. Avval u Mini App'ni bir marta ochishi kerak.");
            return self::FAILURE;
        }

        // Store roles live on a per-store row (same as the invite flow); super_admin/customer on the global row
        $target = $accounts->first(fn (User $account) => $needsStore
            ? (int) $account->store_id === $store->id
            : $account->store_id === null);

        if (!$target && $needsStore) {
            // A store role without a store (left over from the removed login backdoor) is adopted, not duplicated
            $target = $accounts->first(fn (User $account) => $account->store_id === null
                && in_array($account->role, UserRole::storeRoles(), true));
        }

        $oldRole = $target?->role?->value;
        $oldStoreId = $target?->store_id;
        $created = $target === null;

        if ($created) {
            $source = $accounts->firstWhere('store_id', null) ?? $accounts->first();

            $target = User::create([
                'telegram_id' => $telegramId,
                'store_id' => $store?->id,
                'branch_id' => $branch?->id,
                'name' => $source->name,
                'telegram_username' => $source->telegram_username,
                'telegram_photo_url' => $source->telegram_photo_url,
                'phone' => $source->phone,
                'role' => $role,
            ]);
        } else {
            $target->role = $role;
            if ($store) {
                $target->store_id = $store->id;
            }
            if ($branch) {
                $target->branch_id = $branch->id;
            }
            $target->save();
        }

        $payload = [
            'telegram_id' => $telegramId,
            'old_role' => $oldRole,
            'new_role' => $role->value,
            'old_store_id' => $oldStoreId,
            'store_id' => $target->store_id,
            'branch_id' => $target->branch_id,
            'created' => $created,
        ];

        AuditLog::create([
            'user_id' => null,
            'action' => 'cli_role_assigned',
            'model_type' => User::class,
            'model_id' => $target->id,
            'payload_json' => $payload,
        ]);

        Log::notice('user:assign-role executed', $payload + ['user_id' => $target->id]);

        $this->info($created
            ? "Yangi hisob yaratildi va '{$role->value}' roli berildi (user #{$target->id})."
            : "User #{$target->id} roli o'zgartirildi: " . ($oldRole ?? '-') . " -> {$role->value}.");

        $this->table(
            ['ID', 'Telegram ID', 'Ism', 'Rol', 'Do\'kon', 'Filial'],
            User::where('telegram_id', $telegramId)->orderBy('id')->get()->map(fn (User $account) => [
                $account->id,
                $account->telegram_id,
                $account->name,
                $account->role->value,
                $account->store_id ?? '-',
                $account->branch_id ?? '-',
            ])->all()
        );

        return self::SUCCESS;
    }
}
