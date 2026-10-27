<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Admin\AdminRules;
use App\Models\AlertRule;
use App\Models\User;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\Monitoring\AlertKind;
use App\Services\Admin\Monitoring\AlertNotifier;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-15: Monitoring → Qoidalar: the built-in alert rules' switches and thresholds. Reading needs
 * admin.monitoring.view; changing a rule admin.monitoring.manage with a step-up and a reason.
 */
#[Group('Admin')]
class AdminMonitoringRuleController extends AdminController
{
    public function __construct(
        private readonly AlertNotifier $notifier,
    ) {}

    /**
     * Alert rules
     *
     * One rule per kind (failed_jobs_spike, queue_backlog, pos_device_offline, fiscal_failures, settlement_failures,
     * backup_stale, scheduler_stale). `fields` says what each threshold means (label, unit, allowed range) and is null
     * for a threshold the kind does not use; `defaults` are the built-in values. `telegram_configured`: whether an ops
     * (or superadmin) chat is set, i.e. whether `telegram: true` reaches anyone.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{rules: list<array{kind: string, label: string, description: string, severity: string, enabled: bool, telegram: bool, threshold: int, threshold_secondary: int|null, window_minutes: int|null, fields: array<string, mixed>, defaults: array<string, int|null>, unresolved_alerts: int, updated_at: string|null, updated_by: array{id: int, name: string}|null}>, telegram_configured: bool}}')]
    public function index(): JsonResponse
    {
        return $this->success($this->all());
    }

    /**
     * Change a rule
     *
     * Any of `enabled`, `telegram`, `threshold`, `threshold_secondary`, `window_minutes` (only the thresholds the kind
     * uses, within its range: see `fields` in the list) plus a required `reason`. Switching a rule off resolves its
     * alerts. Needs step-up. Answers with all the rules.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{rules: list<array{kind: string, label: string, description: string, severity: string, enabled: bool, telegram: bool, threshold: int, threshold_secondary: int|null, window_minutes: int|null, fields: array<string, mixed>, defaults: array<string, int|null>, unresolved_alerts: int, updated_at: string|null, updated_by: array{id: int, name: string}|null}>, telegram_configured: bool}}')]
    #[ApiDocResponse(404, 'No such rule kind', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(422, 'Value outside the kind\'s range, or a threshold the kind does not use', type: 'array{success: false, message: string, errors: array<string, list<string>>}')]
    public function update(Request $request, string $kind): JsonResponse
    {
        $kindEnum = AlertKind::tryFrom($kind);
        if ($kindEnum === null) {
            return $this->refuse('Bunday qoida yo\'q.', 'not_found', 404);
        }

        $fields = $kindEnum->fields();
        $range = fn (?array $field) => $field === null
            ? ['prohibited']
            : ['sometimes', 'required', 'integer', "min:{$field['min']}", "max:{$field['max']}"];

        $data = $request->validate([
            'enabled' => 'sometimes|required|boolean',
            'telegram' => 'sometimes|required|boolean',
            'threshold' => $range($fields['threshold']),
            'threshold_secondary' => $range($fields['threshold_secondary']),
            'window_minutes' => $range($fields['window']),
            'reason' => AdminRules::reason(),
        ], UzValidation::messages([
            'threshold_secondary.prohibited' => 'Bu qoidada ikkinchi chegara yo\'q.',
            'window_minutes.prohibited' => 'Bu qoidada vaqt oralig\'i yo\'q.',
        ]));
        unset($data['reason']);

        $rule = AlertRule::ensureAll()[$kindEnum->value];
        $before = $this->values($rule);

        /** @var User $member */
        $member = $this->member($request);
        $rule->fill($data)->forceFill(['updated_by' => $member->id])->save();
        $after = $this->values($rule);

        $audit = AdminAudit::for($request)->subject($rule)->meta(['kind' => $kindEnum->value])->changes($before, $after);
        if ($before === $after) {
            $audit->skip();
        }

        return $this->success($this->all(), 'Qoida saqlandi.');
    }

    /**
     * @return array<string, mixed> the fields a change can touch, for the audit entry
     */
    private function values(AlertRule $rule): array
    {
        return [
            'enabled' => $rule->enabled,
            'telegram' => $rule->telegram,
            'threshold' => $rule->threshold,
            'threshold_secondary' => $rule->threshold_secondary,
            'window_minutes' => $rule->window_minutes,
        ];
    }

    /**
     * @return array{rules: list<array<string, mixed>>, telegram_configured: bool}
     */
    private function all(): array
    {
        $rules = AlertRule::ensureAll();
        $unresolved = \App\Models\Alert::query()->unresolved()->selectRaw('alert_rule_id, count(*) as total')->groupBy('alert_rule_id')->pluck('total', 'alert_rule_id');
        $updaters = User::query()->whereIn('id', $rules->pluck('updated_by')->filter())->pluck('name', 'id');

        return [
            'rules' => $rules->map(function (AlertRule $rule) use ($unresolved, $updaters) {
                $kind = $rule->kindEnum();
                $fields = $kind->fields();

                return [
                    'kind' => $kind->value,
                    'label' => $kind->label(),
                    'description' => $kind->description(),
                    'severity' => $kind->severity(),
                    'enabled' => $rule->enabled,
                    'telegram' => $rule->telegram,
                    'threshold' => $rule->threshold,
                    'threshold_secondary' => $fields['threshold_secondary'] !== null ? $rule->threshold_secondary : null,
                    'window_minutes' => $fields['window'] !== null ? $rule->window_minutes : null,
                    'fields' => $fields,
                    'defaults' => $kind->defaults(),
                    'unresolved_alerts' => (int) ($unresolved[$rule->id] ?? 0),
                    'updated_at' => $rule->updated_by !== null ? $rule->updated_at?->toIso8601String() : null,
                    'updated_by' => $rule->updated_by !== null ? ['id' => (int) $rule->updated_by, 'name' => (string) ($updaters[$rule->updated_by] ?? '')] : null,
                ];
            })->values()->all(),
            'telegram_configured' => $this->notifier->chatId() !== null,
        ];
    }
}
