<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Setup\FormatProfile;
use App\Domain\Pos\Setup\FormatProfileTemplateApplier;
use App\Domain\Pos\Setup\FormatProfileTemplates;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Domain\Pos\Setup\PosSettings;
use App\Enums\Permission;
use App\Models\Branch;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\Billing\BillingRefusal;
use App\Services\Finance\Billing\Entitlements;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * P6-11: a branch's format profile for the partner web panel's "Format" page (docs/POS_SPEC.md "Format profili"): the
 * profile, the till's mode, the module switches over the profile's defaults (with the plan's "Asosiy tarifda bor" and
 * the law's locks) and the sample stations and menu (B10). settings.manage (the owner, a manager); never from a
 * terminal PIN session; a branch-bound caller works on their own branch only. Every change is audited.
 */
#[Group('Owner: POS format', "Filial formati (choyxona, osh markazi, stolovaya...): kassa rejimi, modullar, namunaviy sexlar va menyu (P6-11).")]
class PosFormatController extends ManagementController
{
    private const FORMAT = 'array{branch_id: int, profile: string, profile_label: string, mode: string, first_screen: string, modules: list<string>, locks: array{service_charge_zero: bool}, module_rows: list<array{key: string, label: string, description: string, default: bool, override: bool|null, configured: bool, plan_allows: bool, required_plan: string|null, required_plan_name: string|null, locked: string|null, enabled: bool}>, profiles: list<array{key: string, label: string, description: string, mode: string, first_screen: string, modules: list<string>, locks_service_charge: bool, recommended_plan: string, stations: list<string>, dishes: int}>, template: array{profile: string, stations: list<array{name: string, kind: string, kind_label: string, exists: bool}>, dishes: list<array{category: string, name: string, variants: list<string>, exists: bool}>}}';

    /**
     * The branch's format
     *
     * `profile` (one of 14: choyxona, osh_markazi, lagmonxona, somsaxona, tamaddixona, stolovaya, kafe, restoran,
     * kabobxona, baliqxona, fast_food, qahvaxona, toyxona, dark_kitchen), `mode` (counter|table|hybrid), `first_screen`
     * (hall_map|quick_counter|booking_calendar|online_orders), `modules`: the effective modules (as GET /pos/bootstrap
     * `format.modules`), `locks.service_charge_zero` (stolovaya). `module_rows`: one per module — `default` (the
     * profile's), `override` (the owner's, null: none), `configured` (default or override, minus the law), `plan_allows`
     * with `required_plan` when not, `locked` (law: cannot be switched on; plan: the plan lacks it; null) and `enabled`
     * (the result). `profiles`: the picker (each profile's mode, first screen, modules, stations, number of sample
     * dishes). `template`: the branch's profile's sample stations and dishes, each with `exists` (applying skips it).
     */
    #[QueryParameter('branch_id', description: 'The branch (a branch-bound caller: their own when omitted).', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::FORMAT . '}')]
    public function show(Request $request, PosModules $modules, FormatProfileTemplateApplier $templates): JsonResponse
    {
        $request->validate(['branch_id' => 'nullable|integer|min:1|max:999999999999999999']);
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE);

        return $this->success($this->present($branch, $modules, $templates));
    }

    /**
     * Change the branch's format
     *
     * Send what changes. `profile`: a new profile starts from its own mode and modules (the earlier switches are
     * dropped; send `modules` with it to keep some). `mode`: counter|table|hybrid (a new profile's own when left out).
     * `modules`: {module: true|false|null} — true/false switches a module on or off over the profile's default, null goes
     * back to the default; an unknown module is 422. Switching on a module the plan lacks: 403 plan_feature_missing;
     * the service charge of a stolovaya: 422 service_charge_locked. A locked subscription changes nothing (403
     * subscription_locked). Audited (settings.pos_format_changed, the profile change named).
     */
    #[BodyParameter('branch_id', description: 'The branch (a branch-bound caller: their own when omitted).', type: 'int', required: false)]
    #[BodyParameter('profile', description: 'The format profile.', type: 'string', required: false, example: 'osh_markazi')]
    #[BodyParameter('mode', description: 'counter, table or hybrid.', type: 'string', required: false, example: 'counter')]
    #[BodyParameter('modules', description: 'Module switches over the profile: {module: true|false|null}.', type: 'array<string, bool|null>', required: false, example: ['queue_numbers' => true, 'customer_display' => null])]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::FORMAT . '}')]
    #[ApiDocResponse(422, type: 'array{success: false, message: string, code: string, data: array{reason: string, module: string}}')]
    public function update(Request $request, PosModules $modules, FormatProfileTemplateApplier $templates, AuditLogger $audit, Entitlements $entitlements): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer|min:1|max:999999999999999999',
            'profile' => ['sometimes', 'string', Rule::in(FormatProfile::values())],
            'mode' => ['sometimes', 'string', Rule::in(PosSettings::MODES)],
            'modules' => ['sometimes', 'array'],
            'modules.*' => ['nullable', 'boolean'],
        ], UzValidation::messages([
            'profile.in' => "Format noto'g'ri tanlangan.",
            'mode.in' => "Rejim counter, table yoki hybrid bo'lishi kerak.",
        ]), ['profile' => 'Format', 'mode' => 'Rejim', 'modules' => 'Modullar']);

        $branch = $this->branch($request, Permission::SETTINGS_MANAGE);
        $entitlements->assertSettingsUnlocked((int) $branch->store_id);

        $requested = is_array($request->input('modules')) ? $request->input('modules') : [];
        $unknown = array_diff(array_map('strval', array_keys($requested)), PosModule::values());
        if ($unknown !== []) {
            throw ValidationException::withMessages(array_combine(
                array_map(fn (string $key) => "modules.{$key}", $unknown),
                array_map(fn (string $key) => "Noma'lum modul: {$key}.", $unknown),
            ));
        }

        $current = PosSettings::format($branch->pos_settings);
        $profile = isset($data['profile']) ? FormatProfile::from($data['profile']) : $current['profile'];
        $profileChanged = $profile !== $current['profile'];

        // A new profile starts from its own set; otherwise the switches add up
        $overrides = $profileChanged ? [] : $current['modules'];
        foreach ($requested as $key => $value) {
            if ($value === null) {
                unset($overrides[$key]);
                continue;
            }
            $module = PosModule::from((string) $key);
            $on = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            if ($on && in_array($module, $profile->lockedOff(), true)) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => "Stolovayada xizmat haqi qonun bo'yicha olinmaydi (0%), uni yoqib bo'lmaydi.",
                    'code' => 'service_charge_locked',
                    'data' => ['reason' => 'service_charge_locked', 'module' => $module->value],
                ], 422));
            }
            if ($on && ($feature = $module->planFeature()) !== null && !$entitlements->allows((int) $branch->store_id, $feature)) {
                throw BillingRefusal::featureMissing($feature, $entitlements->requiredPlan($feature));
            }
            $overrides[$module->value] = $on;
        }

        $mode = $data['mode'] ?? ($profileChanged ? $profile->defaultMode() : null);

        $before = $this->audited($branch);
        $settings = PosSettings::withFormat($branch->pos_settings, $profile, $overrides, $mode);
        // The P3-13 flag the apps read so far follows the module
        $settings['queue_numbers'] = $settings['modules'][PosModule::QUEUE_NUMBERS->value] ?? in_array(PosModule::QUEUE_NUMBERS, $profile->modules(), true);
        $branch->update(['pos_settings' => $settings]);
        $branch->refresh();

        $audit->record('settings.pos_format_changed', $branch, $before, $this->audited($branch), array_filter([
            'profile_changed' => $profileChanged ?: null,
            'profile_from' => $profileChanged ? $current['profile']->value : null,
            'profile_to' => $profileChanged ? $profile->value : null,
        ]), ['store_id' => (int) $branch->store_id, 'branch_id' => (int) $branch->id]);

        return $this->success($this->present($branch, $modules, $templates), $profileChanged ? "Format o'zgartirildi: {$profile->label()}." : 'Format sozlamalari saqlandi.');
    }

    /**
     * Apply the sample stations and menu
     *
     * The profile's (`profile`, else the branch's) sample kitchen stations on the branch (`stations`, default true) and
     * sample dishes in the store's menu (`menu`, default true; needs menu.manage too). Nothing existing is changed: a
     * station of the same name (or the same osh/mangal/salat va bar/choy kind) and a product of the same name are
     * skipped; dishes are added switched off with a 0 price (set the price, then switch them on). Running it again
     * adds nothing. A locked subscription: 403 subscription_locked. Audited (settings.pos_template_applied).
     */
    #[BodyParameter('branch_id', description: 'The branch (a branch-bound caller: their own when omitted).', type: 'int', required: false)]
    #[BodyParameter('profile', description: "The profile whose template to apply (the branch's when omitted).", type: 'string', required: false, example: 'osh_markazi')]
    #[BodyParameter('stations', description: 'Add the sample kitchen stations.', type: 'bool', required: false, example: true)]
    #[BodyParameter('menu', description: 'Add the sample dishes (switched off, 0 price).', type: 'bool', required: false, example: true)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{stations_created: list<string>, stations_skipped: list<string>, categories_created: list<string>, dishes_created: list<string>, dishes_skipped: list<string>, format: ' . self::FORMAT . '}}')]
    public function applyTemplate(Request $request, PosModules $modules, FormatProfileTemplateApplier $templates, AuditLogger $audit, Entitlements $entitlements): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer|min:1|max:999999999999999999',
            'profile' => ['sometimes', 'string', Rule::in(FormatProfile::values())],
            'stations' => ['sometimes', 'boolean'],
            'menu' => ['sometimes', 'boolean'],
        ], UzValidation::messages(['profile.in' => "Format noto'g'ri tanlangan."]), ['profile' => 'Format']);

        $branch = $this->branch($request, Permission::SETTINGS_MANAGE);
        $entitlements->assertSettingsUnlocked((int) $branch->store_id);

        $withStations = (bool) ($data['stations'] ?? true);
        $withMenu = (bool) ($data['menu'] ?? true);
        if ($withMenu) {
            $this->authorizeFor($request, Permission::MENU_MANAGE, $branch);
        }
        $profile = isset($data['profile']) ? FormatProfile::from($data['profile']) : $modules->profile($branch);

        $result = $templates->apply($branch, $profile, $withStations, $withMenu);
        $audit->record('settings.pos_template_applied', $branch, [], [], [
            'profile' => $profile->value,
            'stations_created' => $result['stations_created'],
            'dishes_created' => $result['dishes_created'],
            'categories_created' => $result['categories_created'],
        ], ['store_id' => (int) $branch->store_id, 'branch_id' => (int) $branch->id]);

        $added = count($result['stations_created']) + count($result['dishes_created']);

        return $this->success([...$result, 'format' => $this->present($branch, $modules, $templates)], $added > 0
            ? "Namuna qo'shildi: " . count($result['stations_created']) . ' ta sex, ' . count($result['dishes_created']) . " ta taom (o'chirilgan, narxini kiriting)."
            : "Hammasi allaqachon bor: hech narsa qo'shilmadi.");
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Branch $branch, PosModules $modules, FormatProfileTemplateApplier $templates): array
    {
        $profile = $modules->profile($branch);

        return [
            'branch_id' => (int) $branch->id,
            ...$modules->format($branch),
            'module_rows' => $modules->rows($branch),
            'profiles' => array_map(fn (FormatProfile $option) => [
                'key' => $option->value,
                'label' => $option->label(),
                'description' => $option->description(),
                'mode' => $option->defaultMode(),
                'first_screen' => $option->firstScreen(),
                'modules' => array_map(fn (PosModule $module) => $module->value, $option->modules()),
                'locks_service_charge' => $option->locksServiceCharge(),
                'recommended_plan' => $option->recommendedPlan(),
                'stations' => array_map(fn (array $station) => $station['name'], FormatProfileTemplates::stations($option)),
                'dishes' => count(FormatProfileTemplates::dishes($option)),
            ], FormatProfile::cases()),
            'template' => $templates->preview($branch, $profile),
        ];
    }

    /**
     * @return array{profile: string, mode: string, modules: array<string, bool>}
     */
    private function audited(Branch $branch): array
    {
        $format = PosSettings::format($branch->pos_settings);

        return [
            'profile' => $format['profile']->value,
            'mode' => PosSettings::resolve($branch->pos_settings)['mode'],
            'modules' => $format['modules'],
        ];
    }
}
