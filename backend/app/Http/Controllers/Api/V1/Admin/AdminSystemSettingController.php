<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Admin\AdminRules;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\System\SmsTestSender;
use App\Services\Admin\System\SystemAdminException;
use App\Services\Admin\System\SystemSettings;
use App\Services\Sms\OnlinegroupSmsService;
use App\Support\PiiMask;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * P4-31 Tizim → Sozlamalar (admin.system.manage): the platform settings grouped as SMS, Telegram and Umumiy. Writes
 * need a step-up and a `reason`. Secrets are write-only: the answer has `configured` and `last4`, never the value, and
 * a secret is replaced on its own (PUT/DELETE /system/settings/secrets/{key}); its audit entry has no value either.
 */
#[Group('Admin')]
class AdminSystemSettingController extends AdminController
{
    private const ITEM = 'array{key: string, label: string, description: string, type: string, editable: bool, secret: bool, value: string|null, configured: bool, last4: string|null, source: string|null, default: string|null, options: list<array{value: string, label: string}>|null, updated_at: string|null}';

    private const GROUPS = 'array{groups: list<array{key: string, label: string, items: list<' . self::ITEM . '>}>}';

    private const ERROR = 'array{success: false, message: string, code: string, data: array{reason: string}}';

    public function __construct(
        private readonly SystemSettings $settings,
    ) {}

    /**
     * Settings
     *
     * Groups `sms`, `telegram`, `general`. `editable=false` items come from the server's configuration (.env) and only
     * show. `source`: `settings` (stored here), `env`, `default` (the code's own) or null (not set). A secret
     * (`secret=true`) has `value: null`, `configured` and `last4` (null for a value under 16 characters).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::GROUPS . '}')]
    public function index(): JsonResponse
    {
        return $this->success(['groups' => $this->settings->groups()]);
    }

    /**
     * Change settings
     *
     * `values` {key: value} of the plain settings (`sms_driver`: log | eskiz | onlinegroup, `eskiz_email`, `eskiz_from`),
     * `reason`. An empty value removes the stored one (the .env value or the default applies). A secret key here is
     * 422 `secret_separate`; Eskiz without its email and password 422 `eskiz_not_configured`; Onlinegroup without its
     * API key 422 `onlinegroup_not_configured`. Answers every group.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::GROUPS . '}')]
    #[ApiDocResponse(422, 'A secret sent here (secret_separate), an unknown key (unknown_setting), Eskiz not set up (eskiz_not_configured), Onlinegroup without its API key (onlinegroup_not_configured)', type: self::ERROR)]
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'values' => 'required|array|min:1',
            ...SystemSettings::valueRules(),
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());

        /** @var array<string, mixed> $values */
        $values = $request->input('values', []);

        try {
            $change = $this->settings->update(array_map(fn ($value) => is_scalar($value) ? (string) $value : null, $values));
        } catch (SystemAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        $audit = AdminAudit::for($request)->changes($change['before'], $change['after'])->meta(['settings' => array_keys($change['after'])]);
        if ($change['after'] === []) {
            $audit->skip();
        }

        return $this->success(['groups' => $this->settings->groups()], 'Sozlamalar saqlandi.');
    }

    /**
     * Replace a secret
     *
     * `value` (1-500 characters), `reason`. `eskiz_password` or `onlinegroup_api_key`. The answer and the audit entry never hold the
     * value: the entry says which setting and whether it was set before.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::GROUPS . '}')]
    #[ApiDocResponse(404, 'Not a secret setting (not_found)', type: self::ERROR)]
    public function replaceSecret(Request $request, string $key): JsonResponse
    {
        $request->validate(['value' => 'required|string|min:1|max:500', 'reason' => AdminRules::reason()], UzValidation::messages());

        return $this->writeSecret($request, $key, trim((string) $request->input('value')), 'Maxfiy kalit almashtirildi.');
    }

    /**
     * Clear a secret
     *
     * `reason`. The stored value goes; the .env value applies again (if there is one). The Eskiz password (the Onlinegroup
     * API key) cannot be cleared while SMS goes through Eskiz (Onlinegroup): 422 `in_use`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::GROUPS . '}')]
    #[ApiDocResponse(422, 'Still used (in_use)', type: self::ERROR)]
    public function clearSecret(Request $request, string $key): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());

        return $this->writeSecret($request, $key, null, "Maxfiy kalit o'chirildi.");
    }

    /**
     * Send a test SMS
     *
     * `phone` (an Uzbek mobile number, any spacing: `+998 90 123 45 67`, `901234567`), optional `reason`. Sends the one
     * fixed text "RIZQIM: sinov xabari. SMS sozlamalari ishlayapti." (no text of one's own) through the saved settings:
     * the chosen real driver, or with SMS off (log) the saved Onlinegroup key. Step-up; 5 an hour per member (429
     * `sms_test_rate_limited`). Every attempt that reached the provider is audited (driver, outcome, the phone masked).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{driver: string, phone: string}}')]
    #[ApiDocResponse(422, 'Not an Uzbek mobile number (errors.phone), SMS not set up (sms_not_configured), the provider refused the key (sms_key_refused), its own limit (sms_provider_rate_limited), refused the message (sms_provider_refused), did not answer (sms_provider_unavailable), any other failure (sms_send_failed)', type: 'array{success: false, message: string, code: string, data: array{reason: string, driver?: string}}')]
    #[ApiDocResponse(429, 'More than 5 tests an hour (sms_test_rate_limited)', type: self::ERROR)]
    public function testSms(Request $request, SmsTestSender $sender): JsonResponse
    {
        $request->validate(['phone' => 'required|string|max:32', 'reason' => AdminRules::reason(false)], UzValidation::messages());

        $phone = OnlinegroupSmsService::normalizePhone((string) $request->input('phone'));
        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => "O'zbekiston mobil raqamini kiriting: +998 va 9 ta raqam."]);
        }

        $provider = $sender->provider();
        if ($provider === null) {
            $e = SmsTestSender::notConfigured();

            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        // An attempt reached the provider: audited whatever it answers (AuditRedactor masks the phone in the entry)
        $masked = (string) PiiMask::phone($phone);
        $audit = AdminAudit::for($request)->recordAlways()->meta(['driver' => $provider, 'phone' => $phone]);

        try {
            $sender->send($provider, $phone);
        } catch (SystemAdminException $e) {
            $audit->meta(['outcome' => $e->reason]);

            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        $audit->meta(['outcome' => 'sent']);

        return $this->success(['driver' => $provider, 'phone' => $masked], "Sinov SMS yuborildi ({$masked}). Telefonga kelganini tekshiring.");
    }

    private function writeSecret(Request $request, string $key, ?string $value, string $message): JsonResponse
    {
        try {
            $result = $this->settings->replaceSecret($key, $value);
        } catch (SystemAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        $audit = AdminAudit::for($request)->subject($this->settings->row($key))
            ->meta(['setting' => $key, 'was_configured' => $result['was_configured'], 'cleared' => $value === null]);
        if (!$result['changed']) {
            $audit->skip();
        }

        return $this->success(['groups' => $this->settings->groups()], $message);
    }
}
