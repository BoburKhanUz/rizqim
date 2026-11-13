<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\QrPayments\QrPaymentSettings;
use App\Domain\Pos\QrPayments\QrProvider;
use App\Domain\Pos\QrPayments\QrProviders;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Enums\Permission;
use App\Models\Branch;
use App\Models\Store;
use App\Services\Finance\Billing\Entitlements;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * P6-41: a branch's QR payment settings for the partner web panel (pos-setup "To'lov"): which providers the till
 * offers, the default one, how long a QR stays valid, and whether the store's Click/Payme merchant account and UzQR
 * gateway are set up (`merchants`; the keys themselves are edited in the store's payment settings and never shown).
 * settings.manage; changing needs the `qr_payments` module; audited (settings.qr_payments_changed). Never from a
 * terminal PIN session.
 */
#[Group('Owner: QR payments', "Kassada Click, Payme va UzQR QR to'lovi: filial sozlamasi va merchant holati (P6-41).")]
class QrPaymentSettingsController extends ManagementController
{
    private const VIEW = 'array{settings: ' . QrPaymentSettings::VIEW . ', merchants: list<' . QrProviders::VIEW . '>, options: array{providers: list<array{key: string, label: string}>, expiry_min: int, expiry_max: int}}';

    /**
     * The branch's QR payment settings
     *
     * `providers`: offered at the till (each also needs its merchant account: `merchants[].configured` — Click and
     * Payme: the store's own merchant account with its keys in the payment settings and "o'z hisobi" on; UzQR: a gateway
     * in the fiscal settings, `fiscal_settings.uzqr.driver`); `default_provider`; `expiry_minutes` (1–30, default 5);
     * `enabled`: the branch has the `qr_payments` module.
     */
    #[QueryParameter('branch_id', description: 'The branch (a branch-bound caller: their own when omitted).', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::VIEW . '}')]
    public function show(Request $request): JsonResponse
    {
        $request->validate(['branch_id' => 'nullable|integer|min:1|max:999999999999999999']);
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE);

        return $this->success($this->view($branch));
    }

    /**
     * Change the branch's QR payment settings
     *
     * Send what changes: `providers` (a list of click, payme, uzqr; may be empty), `default_provider` (one of them, or
     * null), `expiry_minutes` (1–30). Needs the `qr_payments` module (403 module_disabled); a locked subscription
     * changes nothing (403 subscription_locked). Audited (settings.qr_payments_changed).
     */
    #[BodyParameter('branch_id', description: 'The branch (a branch-bound caller: their own when omitted).', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::VIEW . '}')]
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer|min:1|max:999999999999999999',
            'providers' => 'sometimes|array|max:3',
            'providers.*' => ['string', 'distinct', Rule::enum(QrProvider::class)],
            'default_provider' => ['sometimes', 'nullable', 'string', Rule::enum(QrProvider::class)],
            'expiry_minutes' => 'sometimes|integer|min:' . QrPaymentSettings::minExpiry() . '|max:' . QrPaymentSettings::maxExpiry(),
        ]);
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE);
        app(Entitlements::class)->assertSettingsUnlocked((int) $branch->store_id);
        app(PosModules::class)->assertEnabled($branch, PosModule::QR_PAYMENTS);

        $settings = app(QrPaymentSettings::class);
        $providers = $data['providers'] ?? $settings->present($branch)['providers'];
        if (isset($data['default_provider']) && !in_array($data['default_provider'], $providers, true)) {
            throw ValidationException::withMessages(['default_provider' => "Asosiy provayder yoqilganlar orasida bo'lishi kerak."]);
        }

        $settings->update($branch, array_intersect_key($data, array_flip(['providers', 'default_provider', 'expiry_minutes'])), $request->user());

        return $this->success($this->view($branch), "QR to'lov sozlamalari saqlandi.");
    }

    /**
     * @return array<string, mixed>
     */
    private function view(Branch $branch): array
    {
        return [
            'settings' => app(QrPaymentSettings::class)->present($branch),
            'merchants' => app(QrProviders::class)->present(Store::findOrFail((int) $branch->store_id)),
            'options' => [
                'providers' => array_map(fn (QrProvider $provider) => ['key' => $provider->value, 'label' => $provider->label()], QrProvider::cases()),
                'expiry_min' => QrPaymentSettings::minExpiry(),
                'expiry_max' => QrPaymentSettings::maxExpiry(),
            ],
        ];
    }
}
