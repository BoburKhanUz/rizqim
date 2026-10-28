<?php

namespace App\Services\Admin\System;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * P4-31 Tizim → Sozlamalar: the platform settings the old superadmin panel edited (the `settings` table, read by the
 * setting() helper: SMS) and, read-only, what the server's configuration says about Telegram and the app. Grouped as
 * SMS, Telegram and Umumiy; payment providers have their own tab (PaymentProviders).
 *
 * A secret (the Eskiz password, the bot token...) is never answered: only whether it is set and its last 4 characters
 * (SecretMask). It is not changed with the other settings either, but replaced on its own (replaceSecret) and the
 * audit entry never holds its value.
 *
 * The value a setting has follows setting(): a row in `settings` with a non-null value wins (an empty string too: it
 * means "nothing"), then config/settings.php (.env), then the code's own default.
 */
final class SystemSettings
{
    public const GROUPS = [
        'sms' => 'SMS',
        'telegram' => 'Telegram',
        'general' => 'Umumiy',
    ];

    /**
     * The settings stored in the `settings` table, editable here.
     *
     * @var array<string, array{group: string, label: string, description: string, type: string, default?: string, options?: array<string, string>, rules?: list<string>}>
     */
    public const EDITABLE = [
        'sms_driver' => [
            'group' => 'sms',
            'label' => 'SMS yuborish usuli',
            'description' => "«Log» — SMS yuborilmaydi, kod faqat server jurnaliga yoziladi (sinov uchun). «Eskiz» — haqiqiy SMS; Eskiz email va paroli kiritilgan bo'lishi kerak.",
            'type' => 'select',
            'default' => 'log',
            'options' => ['log' => 'Log (SMS yuborilmaydi)', 'eskiz' => 'Eskiz (haqiqiy SMS)'],
            'rules' => ['in:log,eskiz'],
        ],
        'eskiz_email' => [
            'group' => 'sms',
            'label' => 'Eskiz email',
            'description' => 'Eskiz kabinetiga kiradigan email.',
            'type' => 'email',
            'rules' => ['email', 'max:191'],
        ],
        'eskiz_from' => [
            'group' => 'sms',
            'label' => "Jo'natuvchi nomi",
            'description' => "SMS kimdan kelgani (Eskiz tasdiqlagan nom yoki raqam). Bo'sh bo'lsa 4546.",
            'type' => 'text',
            'default' => '4546',
            'rules' => ['max:20', 'regex:/^[A-Za-z0-9 ._-]+$/'],
        ],
        'eskiz_password' => [
            'group' => 'sms',
            'label' => 'Eskiz paroli',
            'description' => "Eskiz API paroli. Faqat oxirgi 4 belgisi ko'rinadi; almashtirish alohida amal.",
            'type' => 'secret',
        ],
    ];

    /**
     * What the server's configuration (.env) holds: shown, never edited here (a change is a deploy).
     *
     * @var array<string, array{group: string, label: string, description: string, config: string, secret: bool}>
     */
    public const SERVER = [
        'telegram_bot_token' => ['group' => 'telegram', 'label' => 'Bot tokeni', 'description' => 'RIZQIM botining tokeni (TELEGRAM_BOT_TOKEN).', 'config' => 'services.telegram.bot_token', 'secret' => true],
        'telegram_bot_username' => ['group' => 'telegram', 'label' => 'Bot nomi', 'description' => 'Bot havolalari shu nom bilan ochiladi (TELEGRAM_BOT_USERNAME).', 'config' => 'services.telegram.bot_username', 'secret' => false],
        'telegram_webapp_url' => ['group' => 'telegram', 'label' => 'Mini App manzili', 'description' => 'TELEGRAM_WEBAPP_URL.', 'config' => 'services.telegram.webapp_url', 'secret' => false],
        'telegram_webhook_secret' => ['group' => 'telegram', 'label' => 'Webhook maxfiy kaliti', 'description' => "Telegram so'rovlarini tekshirish kaliti (TELEGRAM_WEBHOOK_SECRET).", 'config' => 'services.telegram.webhook_secret', 'secret' => true],
        'telegram_superadmin_chat_id' => ['group' => 'telegram', 'label' => 'Superadmin chati', 'description' => "Platforma ogohlantirishlari shu chatga boradi (TELEGRAM_SUPERADMIN_CHAT_ID).", 'config' => 'services.telegram.superadmin_chat_id', 'secret' => true],
        'telegram_ops_chat_id' => ['group' => 'telegram', 'label' => 'Navbatchi guruhi', 'description' => "Monitoring ogohlantirishlari guruhi (TELEGRAM_OPS_CHAT_ID). Bo'sh bo'lsa superadmin chatiga boradi.", 'config' => 'services.telegram.ops_chat_id', 'secret' => true],
        'app_name' => ['group' => 'general', 'label' => 'Ilova nomi', 'description' => 'APP_NAME.', 'config' => 'app.name', 'secret' => false],
        'app_url' => ['group' => 'general', 'label' => 'Ilova manzili', 'description' => 'APP_URL.', 'config' => 'app.url', 'secret' => false],
        'app_env' => ['group' => 'general', 'label' => 'Muhit', 'description' => 'production, staging yoki local (APP_ENV).', 'config' => 'app.env', 'secret' => false],
        'app_timezone' => ['group' => 'general', 'label' => 'Server vaqt mintaqasi', 'description' => "Hisobotlar va ko'rsatkichlar Toshkent vaqti bilan hisoblanadi.", 'config' => 'app.timezone', 'secret' => false],
    ];

    /**
     * Every group with its settings, in the order above.
     *
     * @return list<array{key: string, label: string, items: list<array<string, mixed>>}>
     */
    public function groups(): array
    {
        $rows = Setting::query()->whereIn('key', array_keys(self::EDITABLE))->get()->keyBy('key');

        $groups = [];
        foreach (self::GROUPS as $group => $label) {
            $groups[$group] = ['key' => $group, 'label' => $label, 'items' => []];
        }

        foreach (self::EDITABLE as $key => $definition) {
            $groups[$definition['group']]['items'][] = $this->editableItem($key, $definition, $rows->get($key));
        }
        foreach (self::SERVER as $key => $definition) {
            $groups[$definition['group']]['items'][] = $this->serverItem($key, $definition);
        }

        return array_values($groups);
    }

    public static function isSecret(string $key): bool
    {
        return (self::EDITABLE[$key]['type'] ?? null) === 'secret';
    }

    /**
     * The validation rules of the plain (non-secret) settings, as `values.<key>`.
     *
     * @return array<string, list<string>>
     */
    public static function valueRules(): array
    {
        $rules = [];
        foreach (self::EDITABLE as $key => $definition) {
            if ($definition['type'] !== 'secret') {
                $rules["values.{$key}"] = ['nullable', 'string', ...$definition['rules']];
            }
        }

        return $rules;
    }

    /**
     * Saves the plain settings that changed. An empty value removes the stored one (the .env value or the default
     * applies again). Switching SMS to Eskiz needs its email and password set.
     *
     * @param  array<string, string|null>  $values  key => value, already validated
     * @return array{before: array<string, string|null>, after: array<string, string|null>}
     */
    public function update(array $values): array
    {
        foreach (array_keys($values) as $key) {
            if (!array_key_exists($key, self::EDITABLE)) {
                throw new SystemAdminException("Bunday sozlama yo'q: {$key}.", 'unknown_setting');
            }
            if (self::isSecret($key)) {
                throw new SystemAdminException("Maxfiy kalit bu yerda o'zgartirilmaydi: uni alohida almashtiring.", 'secret_separate');
            }
        }

        $before = [];
        $after = [];
        foreach ($values as $key => $value) {
            $value = $value === null || trim($value) === '' ? null : trim($value);
            $current = $this->stored($key);
            if ($current === $value) {
                continue;
            }
            $before[$key] = $current;
            $after[$key] = $value;
        }

        // SMS through Eskiz (switched to now, or already) needs its email and password
        $driver = array_key_exists('sms_driver', $after) ? ($after['sms_driver'] ?? $this->resolve('sms_driver', null)[0]) : $this->effective('sms_driver');
        if ($driver === 'eskiz' && (array_key_exists('sms_driver', $after) || array_key_exists('eskiz_email', $after))) {
            $email = array_key_exists('eskiz_email', $after) ? ($after['eskiz_email'] ?? $this->resolve('eskiz_email', null)[0]) : $this->effective('eskiz_email');
            if (blank($email) || blank($this->effective('eskiz_password'))) {
                throw new SystemAdminException('Eskiz uchun avval email va parolni kiriting.', 'eskiz_not_configured');
            }
        }

        foreach ($after as $key => $value) {
            $this->write($key, $value);
        }

        return ['before' => $before, 'after' => $after];
    }

    /**
     * Replaces (or, with null, clears) a secret setting. Answers whether it was set before and whether anything
     * changed (clearing a value that is not stored changes nothing); never the value.
     *
     * @return array{was_configured: bool, changed: bool}
     */
    public function replaceSecret(string $key, ?string $value): array
    {
        if (!self::isSecret($key)) {
            throw new SystemAdminException("Bunday maxfiy sozlama yo'q.", 'not_found', 404);
        }

        $wasConfigured = filled($this->effective($key));

        if ($value === null && $this->stored($key) === null) {
            return ['was_configured' => $wasConfigured, 'changed' => false];
        }

        // Clearing while SMS sends through Eskiz would stop every sign-in code
        if ($value === null && $key === 'eskiz_password' && $this->effective('sms_driver') === 'eskiz') {
            throw new SystemAdminException("SMS Eskiz orqali yuborilmoqda: parolni o'chirishdan oldin SMS usulini «Log»ga o'tkazing.", 'in_use');
        }

        $this->write($key, $value);

        return ['was_configured' => $wasConfigured, 'changed' => true];
    }

    /**
     * The row behind a setting (created on demand, so the audit entry has a subject).
     */
    public function row(string $key): ?Setting
    {
        return Setting::query()->where('key', $key)->first();
    }

    private function write(string $key, ?string $value): void
    {
        $row = Setting::query()->firstOrNew(['key' => $key]);
        $row->value = $value;
        $row->save();
        // The model's own `saved` hook forgets the cache too; this covers a row that did not change
        Cache::forget("setting_{$key}");
    }

    private function stored(string $key): ?string
    {
        $value = Setting::query()->where('key', $key)->value('value');

        return $value === null ? null : (string) $value;
    }

    /**
     * What setting() answers, without its cache.
     */
    private function effective(string $key): ?string
    {
        [$value] = $this->resolve($key, Setting::query()->where('key', $key)->first());

        return $value;
    }

    /**
     * @return array{0: string|null, 1: string|null} value, source (settings | env | default | null)
     */
    private function resolve(string $key, ?Setting $row): array
    {
        if ($row !== null && $row->value !== null) {
            return [(string) $row->value, 'settings'];
        }
        $config = config("settings.{$key}");
        if ($config !== null && $config !== '') {
            return [(string) $config, 'env'];
        }
        $default = self::EDITABLE[$key]['default'] ?? null;

        return $default !== null ? [$default, 'default'] : [null, null];
    }

    /**
     * @param  array{group: string, label: string, description: string, type: string, default?: string, options?: array<string, string>, rules?: list<string>}  $definition
     * @return array<string, mixed>
     */
    private function editableItem(string $key, array $definition, ?Setting $row): array
    {
        [$value, $source] = $this->resolve($key, $row);
        $secret = $definition['type'] === 'secret';
        $mask = SecretMask::describe($value);

        return [
            'key' => $key,
            'label' => $definition['label'],
            'description' => $definition['description'],
            'type' => $definition['type'],
            'editable' => true,
            'secret' => $secret,
            'value' => $secret ? null : $value,
            'configured' => $mask['configured'],
            'last4' => $secret ? $mask['last4'] : null,
            'source' => $source,
            'default' => $definition['default'] ?? null,
            'options' => isset($definition['options']) ? array_map(fn (string $option, string $label) => ['value' => $option, 'label' => $label], array_keys($definition['options']), $definition['options']) : null,
            'updated_at' => $row?->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array{group: string, label: string, description: string, config: string, secret: bool}  $definition
     * @return array<string, mixed>
     */
    private function serverItem(string $key, array $definition): array
    {
        $raw = config($definition['config']);
        $value = is_scalar($raw) && (string) $raw !== '' ? (string) $raw : null;
        $mask = SecretMask::describe($value);

        return [
            'key' => $key,
            'label' => $definition['label'],
            'description' => $definition['description'],
            'type' => $definition['secret'] ? 'secret' : 'text',
            'editable' => false,
            'secret' => $definition['secret'],
            'value' => $definition['secret'] ? null : $value,
            'configured' => $mask['configured'],
            'last4' => $definition['secret'] ? $mask['last4'] : null,
            'source' => $value !== null ? 'env' : null,
            'default' => null,
            'options' => null,
            'updated_at' => null,
        ];
    }
}
