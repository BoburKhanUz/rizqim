<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Admin\AdminRules;
use App\Models\PaymentProvider;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\System\PaymentProviders;
use App\Services\Admin\System\SystemAdminException;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-31 Tizim → To'lov tizimlari (admin.system.manage): the platform merchant accounts. Writes need a step-up and a
 * `reason`. Credentials are write-only: one key at a time, the answer and the audit entry say which key and whether it
 * was stored, never the value. There is no "test connection": no provider has a call that checks the keys without
 * side effects.
 */
#[Group('Admin')]
class AdminSystemPaymentProviderController extends AdminController
{
    private const ERROR = 'array{success: false, message: string, code: string, data: array{reason: string}}';

    public function __construct(
        private readonly PaymentProviders $providers,
    ) {}

    /**
     * Payment providers
     *
     * Each with `is_active`, `mode` (test | production), `wired` (checkout and webhooks use it: Payme and Click) and its
     * credentials: `configured`, `last4` and `source` (`settings` — stored here, `env` — config/services.php, null).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<' . PaymentProviders::ROW . '>}')]
    public function index(): JsonResponse
    {
        return $this->success($this->providers->rows());
    }

    /**
     * Switch a provider
     *
     * `is_active` and/or `mode` (test | production), `reason`. Answers the provider. On APP_ENV=production a move to
     * `test` is refused with 422 `test_mode_not_allowed` unless the server sets PAYMENTS_ALLOW_TEST_MODE=true (a row
     * already in test may still be saved or switched back).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PaymentProviders::ROW . '}')]
    #[ApiDocResponse(404, 'No such provider (not_found)', type: self::ERROR)]
    #[ApiDocResponse(422, 'Test mode on production without PAYMENTS_ALLOW_TEST_MODE (test_mode_not_allowed)', type: self::ERROR)]
    public function update(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'is_active' => 'sometimes|boolean',
            'mode' => ['sometimes', 'string', Rule::in(PaymentProviders::MODES)],
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());

        $provider = $this->providers->find($slug);
        if ($provider === null) {
            return $this->notFound();
        }

        try {
            $change = $this->providers->update($provider, array_intersect_key($data, array_flip(['is_active', 'mode'])));
        } catch (SystemAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }
        $audit = AdminAudit::for($request)->subject($provider)->changes($change['before'], $change['after'])->meta(['provider' => $provider->slug]);
        if ($change['before'] === $change['after']) {
            $audit->skip();
        }

        return $this->success($this->providers->row($provider->refresh()), "To'lov tizimi saqlandi.");
    }

    /**
     * Replace a credential
     *
     * `value` (1-500 characters), `reason`. The key is one the provider's code knows (Payme: merchant_id, secret_key;
     * Click: service_id, merchant_id, secret_key, merchant_user_id; ...) or one already stored; else 422 `unknown_key`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PaymentProviders::ROW . '}')]
    #[ApiDocResponse(422, 'A key the provider does not have (unknown_key)', type: self::ERROR)]
    public function replaceCredential(Request $request, string $slug, string $key): JsonResponse
    {
        $request->validate(['value' => 'required|string|min:1|max:500', 'reason' => AdminRules::reason()], UzValidation::messages());

        return $this->writeCredential($request, $slug, $key, trim((string) $request->input('value')), 'Kalit almashtirildi.');
    }

    /**
     * Clear a credential
     *
     * `reason`. The stored value goes; config/services.php (.env) applies again.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PaymentProviders::ROW . '}')]
    public function clearCredential(Request $request, string $slug, string $key): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());

        return $this->writeCredential($request, $slug, $key, null, "Kalit o'chirildi: endi server sozlamasidagi qiymat ishlatiladi.");
    }

    private function writeCredential(Request $request, string $slug, string $key, ?string $value, string $message): JsonResponse
    {
        $provider = $this->providers->find($slug);
        if (!$provider instanceof PaymentProvider) {
            return $this->notFound();
        }

        try {
            $result = $this->providers->replaceCredential($provider, $key, $value);
        } catch (SystemAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        // `field`, not "key"/"credential": the audit redactor drops values under secret-looking names
        $audit = AdminAudit::for($request)->subject($provider)->meta(['provider' => $provider->slug, 'field' => $key, 'was_stored' => $result['was_stored'], 'cleared' => $value === null]);
        if (!$result['changed']) {
            $audit->skip();
        }

        return $this->success($this->providers->row($provider->refresh()), $message);
    }

    private function notFound(): JsonResponse
    {
        return $this->refuse("To'lov tizimi topilmadi.", 'not_found', 404);
    }
}
