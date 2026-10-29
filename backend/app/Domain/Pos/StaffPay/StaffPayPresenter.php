<?php

namespace App\Domain\Pos\StaffPay;

use App\Enums\UserRole;
use App\Models\StaffPayRule;
use App\Models\StaffPayStatement;
use App\Models\StaffPayStatementLine;
use App\Models\StaffWorkSession;
use App\Models\User;
use App\Support\Money;

/**
 * P6-23: the staff pay records as the owner API and the till show them. Money as decimal strings, moments ISO 8601,
 * business dates YYYY-MM-DD. No phone numbers.
 */
final class StaffPayPresenter
{
    public const RULE = 'array{uuid: string, branch_id: int, user_id: int|null, user_name: string|null, role: string|null, role_label: string|null, effective_from: string, components: list<' . PayComponents::SHAPE . '>, note: string|null, updated_at: string|null}';

    public const SESSION = 'array{uuid: string, branch_id: int, user_id: int, user_name: string|null, business_date: string, clock_in_at: string, clock_out_at: string|null, abandoned: bool, open: bool, minutes: int, source: string, note: string|null}';

    public const METRICS = 'array{shifts: int, minutes: int, bills: int, sales: string, service_charge: string, events: int, tips: string, uncounted_sessions: int, rules: list<string>, own_rule: bool}';

    public const LINE = 'array{uuid: string, user_id: int|null, user_name: string|null, role: string|null, role_label: string|null, metrics: ' . self::METRICS . ', components: list<' . StaffPayCalculator::RESULT . '>, gross: string, advances: string, adjustment: string, adjustment_reason: string|null, payable: string, paid: bool, paid_at: string|null, paid_method: string|null, paid_note: string|null, cash_movement_id: int|null}';

    public const OPEN_SHIFT = 'array{uuid: string, device_name: string|null, business_date: string, opened_at: string, expected_cash: string}';

    public const SUMMARY_FIELDS = 'uuid: string, branch_id: int, period: string, period_label: string, period_from: string, period_to: string, status: string, status_label: string, gross_total: string, payable_total: string, paid_total: string, lines_count: int, warnings: list<array{type: string, user_id: int|null, name: string|null, count?: int}>, computed_at: string|null, closed_at: string|null, version: int, created_at: string|null';

    public const SUMMARY = 'array{' . self::SUMMARY_FIELDS . '}';

    /** A statement with its lines and the branch's open till shifts a line can be paid from. */
    public const DETAIL = 'array{' . self::SUMMARY_FIELDS . ', lines: list<' . self::LINE . '>, open_shifts: list<' . self::OPEN_SHIFT . '>}';

    /**
     * @param  array<int, string>  $names  user id => name
     * @return array<string, mixed>
     */
    public static function rule(StaffPayRule $rule, array $names = []): array
    {
        $role = $rule->role !== null ? UserRole::tryFrom($rule->role) : null;

        return [
            'uuid' => (string) $rule->uuid,
            'branch_id' => (int) $rule->branch_id,
            'user_id' => $rule->user_id !== null ? (int) $rule->user_id : null,
            'user_name' => $rule->user_id !== null ? ($names[(int) $rule->user_id] ?? User::whereKey($rule->user_id)->value('name')) : null,
            'role' => $rule->role,
            'role_label' => $role?->labelUz(),
            'effective_from' => $rule->effective_from->toDateString(),
            'components' => PayComponents::present($rule->components),
            'note' => $rule->note,
            'updated_at' => $rule->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, string>  $names
     * @return array<string, mixed>
     */
    public static function session(StaffWorkSession $session, array $names = []): array
    {
        return [
            'uuid' => (string) $session->uuid,
            'branch_id' => (int) $session->branch_id,
            'user_id' => (int) $session->user_id,
            'user_name' => $names[(int) $session->user_id] ?? User::whereKey($session->user_id)->value('name'),
            'business_date' => $session->business_date->toDateString(),
            'clock_in_at' => $session->clock_in_at->toIso8601String(),
            'clock_out_at' => $session->clock_out_at?->toIso8601String(),
            'abandoned' => $session->abandoned_at !== null,
            'open' => $session->isOpen(),
            'minutes' => $session->minutes(),
            'source' => $session->source,
            'note' => $session->note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(StaffPayStatement $statement, ?int $linesCount = null): array
    {
        $period = PayPeriod::tryFrom($statement->period);

        return [
            'uuid' => (string) $statement->uuid,
            'branch_id' => (int) $statement->branch_id,
            'period' => $statement->period,
            'period_label' => $period?->label() ?? $statement->period,
            'period_from' => $statement->period_from->toDateString(),
            'period_to' => $statement->period_to->toDateString(),
            'status' => $statement->status->value,
            'status_label' => $statement->status->label(),
            'gross_total' => Money::of($statement->gross_total)->toString(),
            'payable_total' => Money::of($statement->payable_total)->toString(),
            'paid_total' => Money::of($statement->paid_total)->toString(),
            'lines_count' => $linesCount ?? $statement->lines()->count(),
            'warnings' => $statement->warnings ?? [],
            'computed_at' => $statement->computed_at?->toIso8601String(),
            'closed_at' => $statement->closed_at?->toIso8601String(),
            'version' => (int) $statement->version,
            'created_at' => $statement->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(StaffPayStatement $statement): array
    {
        $lines = $statement->lines()->orderBy('user_name')->orderBy('id')->get();

        return self::summary($statement, $lines->count()) + [
            'lines' => $lines->map(fn (StaffPayStatementLine $line) => self::line($line))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function line(StaffPayStatementLine $line): array
    {
        $role = $line->role !== null ? UserRole::tryFrom((string) $line->role) : null;

        return [
            'uuid' => (string) $line->uuid,
            'user_id' => $line->user_id !== null ? (int) $line->user_id : null,
            'user_name' => $line->user_name,
            'role' => $line->role,
            'role_label' => $role?->labelUz(),
            'metrics' => $line->metrics,
            'components' => $line->components,
            'gross' => Money::of($line->gross)->toString(),
            'advances' => Money::of($line->advances)->toString(),
            'adjustment' => Money::of($line->adjustment)->toString(),
            'adjustment_reason' => $line->adjustment_reason,
            'payable' => Money::of($line->payable)->toString(),
            'paid' => $line->paid_at !== null,
            'paid_at' => $line->paid_at?->toIso8601String(),
            'paid_method' => $line->paid_method,
            'paid_note' => $line->paid_note,
            'cash_movement_id' => $line->cash_movement_id !== null ? (int) $line->cash_movement_id : null,
        ];
    }
}
