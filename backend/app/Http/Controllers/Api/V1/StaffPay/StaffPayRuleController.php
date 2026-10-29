<?php

namespace App\Http\Controllers\Api\V1\StaffPay;

use App\Domain\Pos\Booking\BookingEventType;
use App\Domain\Pos\StaffPay\GuaranteeMode;
use App\Domain\Pos\StaffPay\GuaranteePer;
use App\Domain\Pos\StaffPay\PayComponents;
use App\Domain\Pos\StaffPay\PayComponentType;
use App\Domain\Pos\StaffPay\PayPeriod;
use App\Domain\Pos\StaffPay\PercentBase;
use App\Domain\Pos\StaffPay\StaffPayPresenter;
use App\Domain\Pos\StaffPay\StaffPayRules;
use App\Domain\Pos\StaffPay\StaffPaySettings;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\StaffPayRule;
use App\Models\User;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P6-23 (C3): "Ish haqi qoidalari" — a branch's pay period and its wage rules per staff member or role, each a list
 * of components that add up (docs/API.md "Ish haqi qoidalari"). Read with staff_pay.view (or staff_pay.manage),
 * changed with staff_pay.manage; every change audited. Behind the `staff_pay` module.
 */
#[Group('Owner: staff pay', "Ofitsiant va xodimlar ish haqi qoidalari, ish vaqti va davr hisob-kitobi (P6-23).")]
class StaffPayRuleController extends StaffPayOwnerController
{
    public const OPTION = 'array{value: string, label: string}';

    public const OVERVIEW = 'array{branch: array{id: int, name: string}, period: string, period_label: string, periods: list<' . self::OPTION . '>, component_types: list<' . self::OPTION . '>, guarantee_modes: list<' . self::OPTION . '>, guarantee_per: list<' . self::OPTION . '>, percent_bases: list<' . self::OPTION . '>, event_types: list<' . self::OPTION . '>, roles: list<' . self::OPTION . '>, staff: list<array{id: int, name: string, role: string, role_label: string, branch_id: int|null}>, rules: list<' . StaffPayPresenter::RULE . '>}';

    /**
     * Staff pay rules of a branch
     *
     * The branch's pay `period` (day, week, half_month, month: business days), the choices the rule editor offers,
     * the staff who can work in the branch, and every rule version, by subject and start date. A rule is a staff
     * member's (`user_id`) or a role's (`role`); a person's own version wins over their role's; a version is in force
     * from `effective_from` until the subject's next one. staff_pay.view or staff_pay.manage.
     */
    #[QueryParameter('branch_id', description: 'Filial (filialga biriktirilgan xodim uchun o\'z filiali).', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::OVERVIEW . '}')]
    public function index(Request $request, StaffPayRules $rules, StaffPaySettings $settings): JsonResponse
    {
        $request->validate(['branch_id' => UzValidation::rowId()]);
        $branch = $this->moduleBranch($request, $this->readPermission($request));
        $staff = $rules->staff($branch)->get(['id', 'name', 'role', 'branch_id']);
        $names = $staff->pluck('name', 'id')->all();
        $period = $settings->period($branch);
        $options = fn (array $cases) => array_map(fn ($case) => ['value' => (string) $case->value, 'label' => $case->label()], $cases);

        return $this->success([
            'branch' => ['id' => (int) $branch->id, 'name' => (string) $branch->name],
            'period' => $period->value,
            'period_label' => $period->label(),
            'periods' => $options(PayPeriod::cases()),
            'component_types' => $options(PayComponentType::cases()),
            'guarantee_modes' => $options(GuaranteeMode::cases()),
            'guarantee_per' => $options(GuaranteePer::cases()),
            'percent_bases' => $options(PercentBase::cases()),
            'event_types' => $options(BookingEventType::cases()),
            'roles' => array_map(fn (UserRole $role) => ['value' => $role->value, 'label' => $role->labelUz()], StaffPayRules::roles()),
            'staff' => $staff->map(fn (User $user) => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'role' => $user->role->value,
                'role_label' => $user->role->labelUz(),
                'branch_id' => $user->branch_id !== null ? (int) $user->branch_id : null,
            ])->values()->all(),
            'rules' => $rules->forBranch($branch)->map(fn (StaffPayRule $rule) => StaffPayPresenter::rule($rule, $names))->values()->all(),
        ]);
    }

    /**
     * Change the pay period of a branch
     *
     * `period`: day | week (Monday–Sunday) | half_month (1–15, 16–end) | month — business days in the store's time
     * zone. Statements made from now on use it; existing ones keep theirs. Audited. staff_pay.manage.
     */
    #[BodyParameter('branch_id', type: 'int', required: false)]
    #[BodyParameter('period', type: 'string', required: true, description: 'App\Domain\Pos\StaffPay\PayPeriod: day, week, half_month, month.')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{period: string, period_label: string}}')]
    public function settings(Request $request, StaffPaySettings $settings): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => UzValidation::rowId(),
            'period' => ['required', 'string', Rule::in(PayPeriod::values())],
        ], UzValidation::messages(['period.in' => "Davr noto'g'ri: day, week, half_month yoki month."]), ['period' => 'Hisob davri']);
        $branch = $this->moduleBranch($request, Permission::STAFF_PAY_MANAGE);
        /** @var User $user */
        $user = $request->user();

        $period = $settings->setPeriod($branch, PayPeriod::from((string) $data['period']), $user);

        return $this->success(['period' => $period->value, 'period_label' => $period->label()], 'Saqlandi.');
    }

    /**
     * Add a wage rule (version)
     *
     * For one staff member (`user_id`) or a role (`role`: manager, cashier, waiter, cook, hostess, courier), in force
     * from `effective_from` (a business date). `components`: one or more parts that add up — each `{type, ...}`:
     * shift_rate {amount}; hourly_rate {amount}; sales_percent {percent}; service_charge_percent {percent};
     * guarantee {amount, per: shift|period, mode: max|plus, percent, base: sales|service_charge}; event_rate {amount,
     * event_types: booking kinds, empty for all but "other"}; tips {} (the person's tips, only when this part is
     * there). Amounts so'm (0–100 000 000), percents 0–100; a type once per rule; an empty list means no pay from that
     * date. 422 when the subject already has a version from that date. Audited. staff_pay.manage.
     */
    #[BodyParameter('branch_id', type: 'int', required: false)]
    #[BodyParameter('user_id', type: 'int|null', required: false)]
    #[BodyParameter('role', type: 'string|null', required: false)]
    #[BodyParameter('effective_from', type: 'string', required: true, description: 'YYYY-MM-DD')]
    #[BodyParameter('components', type: 'list<array<string, mixed>>', required: true, infer: false)]
    #[BodyParameter('note', type: 'string|null', required: false)]
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . StaffPayPresenter::RULE . '}')]
    public function store(Request $request, StaffPayRules $rules): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => UzValidation::rowId(),
            'user_id' => UzValidation::rowId(),
            'role' => ['nullable', 'string', 'max:16'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'components' => ['present', 'array', 'max:' . PayComponents::MAX],
            'note' => ['nullable', 'string', 'max:500'],
        ], UzValidation::messages(), ['effective_from' => 'Boshlanish sanasi', 'components' => 'Qoida qismlari', 'note' => 'Izoh']);
        $branch = $this->moduleBranch($request, Permission::STAFF_PAY_MANAGE);
        /** @var User $user */
        $user = $request->user();

        $rule = $rules->create($branch, [
            'user_id' => isset($data['user_id']) ? (int) $data['user_id'] : null,
            'role' => $data['role'] ?? null,
            'effective_from' => (string) $data['effective_from'],
            'components' => $request->input('components'),
            'note' => $this->trimmed($request, 'note'),
        ], $user);

        return $this->success(StaffPayPresenter::rule($rule), 'Qoida saqlandi.', 201);
    }

    /**
     * Change a wage rule (version)
     *
     * Send what changes: `effective_from`, `components` (the whole list, as when adding), `note`. A closed statement
     * keeps what it used. Audited. staff_pay.manage.
     */
    #[BodyParameter('effective_from', type: 'string', required: false, description: 'YYYY-MM-DD')]
    #[BodyParameter('components', type: 'list<array<string, mixed>>', required: false, infer: false)]
    #[BodyParameter('note', type: 'string|null', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . StaffPayPresenter::RULE . '}')]
    public function update(Request $request, StaffPayRules $rules, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'effective_from' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'components' => ['sometimes', 'present', 'array', 'max:' . PayComponents::MAX],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ], UzValidation::messages(), ['effective_from' => 'Boshlanish sanasi', 'components' => 'Qoida qismlari', 'note' => 'Izoh']);
        [$rule] = $this->record($request, StaffPayRule::class, $uuid, Permission::STAFF_PAY_MANAGE, 'Qoida topilmadi.');
        /** @var User $user */
        $user = $request->user();

        $input = [];
        if (isset($data['effective_from'])) {
            $input['effective_from'] = (string) $data['effective_from'];
        }
        if ($request->has('components')) {
            $input['components'] = $request->input('components');
        }
        if ($request->has('note')) {
            $input['note'] = $this->trimmed($request, 'note');
        }

        return $this->success(StaffPayPresenter::rule($rules->update($rule, $input, $user)), 'Qoida saqlandi.');
    }

    /**
     * Delete a wage rule (version)
     *
     * The subject's previous version (if any) is in force again from this one's date. Closed statements keep what
     * they used. Audited. staff_pay.manage.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: null}')]
    public function destroy(Request $request, StaffPayRules $rules, string $uuid): JsonResponse
    {
        [$rule] = $this->record($request, StaffPayRule::class, $uuid, Permission::STAFF_PAY_MANAGE, 'Qoida topilmadi.');
        /** @var User $user */
        $user = $request->user();
        $rules->delete($rule, $user);

        return $this->success(null, "Qoida o'chirildi.");
    }
}
