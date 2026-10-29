<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Admin\AdminRules;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\System\SystemAdminException;
use App\Services\Admin\System\SystemSettings;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     * `values` {key: value} of the plain settings (`sms_driver`: log | eskiz, `eskiz_email`, `eskiz_from`), `reason`. An
     * empty value removes the stored one (the .env value or the default applies). A secret key here is 422
     * `secret_separate`; Eskiz without its email and password 422 `eskiz_not_configured`. Answers every group.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::GROUPS . '}')]
    #[ApiDocResponse(422, 'A secret sent here (secret_separate), an unknown key (unknown_setting), Eskiz not set up (eskiz_not_configured)', type: self::ERROR)]
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
     * `value` (1-500 characters), `reason`. Only `eskiz_password` today. The answer and the audit entry never hold the
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
     * `reason`. The stored value goes; the .env value applies again (if there is one). The Eskiz password cannot be
     * cleared while SMS goes through Eskiz (422 `in_use`).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::GROUPS . '}')]
    #[ApiDocResponse(422, 'Still used (in_use)', type: self::ERROR)]
    public function clearSecret(Request $request, string $key): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());

        return $this->writeSecret($request, $key, null, "Maxfiy kalit o'chirildi.");
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
