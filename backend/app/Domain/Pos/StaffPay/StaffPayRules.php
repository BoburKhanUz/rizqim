<?php

namespace App\Domain\Pos\StaffPay;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\StaffPayRule;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * P6-23: the wage rules of a branch. A rule is a staff member's (user_id) or a role's (every person of that role in
 * the branch); each change of pay is a new version from a business date (`effective_from`) and the subject's latest
 * version on or before a day is in force that day. A person's own version wins over their role's (an own version
 * without components: no pay from that day). Every change is audited; statements keep a snapshot of what they used,
 * so editing or deleting a version never changes a closed statement.
 */
final class StaffPayRules
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Roles a rule can be for (the store's staff, not the owner).
     *
     * @return list<UserRole>
     */
    public static function roles(): array
    {
        return UserRole::assignableStaffRoles();
    }

    /**
     * Staff of the store who can work in the branch (bound to it or store-wide), with a pay role, oldest first.
     *
     * @return Builder<User>
     */
    public function staff(Branch $branch): Builder
    {
        return User::query()
            ->where('store_id', $branch->store_id)
            ->whereIn('role', array_map(fn (UserRole $role) => $role->value, self::roles()))
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $branch->id))
            ->orderBy('id');
    }

    /**
     * @return Collection<int, StaffPayRule>
     */
    public function forBranch(Branch $branch, ?string $until = null): Collection
    {
        return StaffPayRule::query()
            ->where('branch_id', $branch->id)
            ->when($until !== null, fn ($query) => $query->where('effective_from', '<=', $until))
            ->orderBy('subject')
            ->orderBy('effective_from')
            ->get();
    }

    /**
     * The version in force for a person on a business date: their own, else their role's; null: none.
     *
     * @param  Collection<int, StaffPayRule>  $rules  forBranch()
     */
    public static function effective(Collection $rules, User $user, string $date): ?StaffPayRule
    {
        $latest = fn (string $subject) => $rules
            ->filter(fn (StaffPayRule $rule) => $rule->subject === $subject && $rule->effective_from->toDateString() <= $date)
            ->sortBy(fn (StaffPayRule $rule) => $rule->effective_from->toDateString())
            ->last();

        return $latest('user:' . $user->id) ?? $latest('role:' . $user->role->value);
    }

    /**
     * @param  array{user_id?: int|null, role?: string|null, effective_from: string, components: mixed, note?: string|null}  $input
     *
     * @throws ValidationException
     */
    public function create(Branch $branch, array $input, User $actor): StaffPayRule
    {
        [$userId, $role, $subject] = $this->subject($branch, $input['user_id'] ?? null, $input['role'] ?? null);
        $components = PayComponents::normalize($input['components']);

        try {
            // In its own savepoint: a duplicate start date must not abort an enclosing transaction
            $rule = DB::transaction(fn () => StaffPayRule::create([
                'store_id' => $branch->store_id,
                'branch_id' => $branch->id,
                'user_id' => $userId,
                'role' => $role,
                'subject' => $subject,
                'effective_from' => $input['effective_from'],
                'components' => $components,
                'note' => $input['note'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['effective_from' => "Bu xodim yoki rol uchun shu sanadan qoida allaqachon bor: uni tahrirlang."]);
        }

        $this->audit->record('staff_pay.rule_created', $rule, [], [
            'subject' => $subject,
            'effective_from' => $rule->effective_from->toDateString(),
            'components' => $components,
        ], ['branch_name' => $branch->name], ['actor' => $actor]);

        return $rule;
    }

    /**
     * @param  array{effective_from?: string, components?: mixed, note?: string|null}  $input
     *
     * @throws ValidationException
     */
    public function update(StaffPayRule $rule, array $input, User $actor): StaffPayRule
    {
        $before = ['effective_from' => $rule->effective_from->toDateString(), 'components' => $rule->components, 'note' => $rule->note];
        if (array_key_exists('components', $input)) {
            $rule->components = PayComponents::normalize($input['components']);
        }
        if (isset($input['effective_from'])) {
            $rule->effective_from = \Illuminate\Support\Carbon::parse($input['effective_from']);
        }
        if (array_key_exists('note', $input)) {
            $rule->note = $input['note'];
        }
        $rule->updated_by = $actor->id;

        try {
            DB::transaction(fn () => $rule->save());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['effective_from' => "Bu xodim yoki rol uchun shu sanadan qoida allaqachon bor."]);
        }

        $this->audit->record('staff_pay.rule_changed', $rule, $before, [
            'effective_from' => $rule->effective_from->toDateString(),
            'components' => $rule->components,
            'note' => $rule->note,
        ], ['subject' => $rule->subject], ['actor' => $actor]);

        return $rule;
    }

    public function delete(StaffPayRule $rule, User $actor): void
    {
        $this->audit->record('staff_pay.rule_deleted', $rule, [
            'subject' => $rule->subject,
            'effective_from' => $rule->effective_from->toDateString(),
            'components' => $rule->components,
        ], [], [], ['actor' => $actor]);
        $rule->delete();
    }

    /**
     * @return array{0: int|null, 1: string|null, 2: string}
     *
     * @throws ValidationException
     */
    private function subject(Branch $branch, mixed $userId, mixed $role): array
    {
        if (($userId === null) === ($role === null)) {
            throw ValidationException::withMessages(['user_id' => 'Qoida bitta xodim (user_id) yoki bitta rol (role) uchun bo\'ladi.']);
        }

        if ($userId !== null) {
            $user = $this->staff($branch)->whereKey((int) $userId)->first();
            if ($user === null) {
                throw ValidationException::withMessages(['user_id' => 'Xodim topilmadi yoki bu filialda ishlamaydi.']);
            }

            return [(int) $user->id, null, 'user:' . $user->id];
        }

        $roleCase = is_string($role) ? UserRole::tryFrom($role) : null;
        if ($roleCase === null || !in_array($roleCase, self::roles(), true)) {
            throw ValidationException::withMessages(['role' => "Rol noto'g'ri: " . implode(', ', array_map(fn (UserRole $r) => $r->value, self::roles())) . '.']);
        }

        return [null, $roleCase->value, 'role:' . $roleCase->value];
    }
}
