<?php

namespace App\Http\Requests\Admin;

/**
 * P4-11: validation rules shared by the admin API. Stage B actions that need a reason (cancel an order, approve a
 * payout, ...) take `reason` with reason(); RecordAdminAudit writes it into the action's audit entry.
 */
final class AdminRules
{
    /**
     * @return list<string>
     */
    public static function reason(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'min:3', 'max:500'];
    }

    /**
     * A 6-digit TOTP code.
     *
     * @return list<string>
     */
    public static function code(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'regex:/^\d{6}$/'];
    }

    /**
     * A new team member's password: at least 12 characters, not the email.
     *
     * @return list<string>
     */
    public static function password(): array
    {
        return ['required', 'string', 'min:12', 'max:200'];
    }
}
