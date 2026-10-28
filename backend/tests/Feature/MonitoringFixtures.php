<?php

namespace Tests\Feature;

use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Enums\DeviceType;
use App\Jobs\SendTelegramRequest;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Device;
use App\Models\FiscalReceipt;
use App\Models\Store;
use App\Services\Admin\Monitoring\AlertKind;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * P4-15 helpers: failed job rows (with a payload that carries secrets), alert rules and alerts, terminals, fiscal
 * receipts.
 */
trait MonitoringFixtures
{
    protected const SECRET_BOT_TOKEN = '123456789:AAHsecretbottokenvalue_1234567890abcdef';

    protected const SECRET_PHONE = '+998901234567';

    protected const SECRET_API_KEY = 'sk_live_super_secret_key_value';

    /**
     * A failed SendTelegramRequest whose payload and exception quote a bot token, a phone number and an API key.
     */
    protected function failedJob(array $attributes = []): string
    {
        $uuid = $attributes['uuid'] ?? (string) Str::uuid();
        $command = new SendTelegramRequest('sendMessage', [
            'chat_id' => '-100123',
            'text' => 'Buyurtma tayyor, mijoz ' . self::SECRET_PHONE,
            'api_key' => self::SECRET_API_KEY,
            'reply_markup' => ['bot_token' => self::SECRET_BOT_TOKEN],
        ]);

        $payload = json_encode([
            'uuid' => $uuid,
            'displayName' => SendTelegramRequest::class,
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'maxTries' => 5,
            'attempts' => 5,
            'data' => ['commandName' => SendTelegramRequest::class, 'command' => serialize($command)],
        ]);

        $exception = "Illuminate\\Http\\Client\\ConnectionException: cURL error 28: Operation timed out for https://api.telegram.org/bot" . self::SECRET_BOT_TOKEN
            . "/sendMessage (customer " . self::SECRET_PHONE . ")\n#0 /var/www/html/app/Jobs/SendTelegramRequest.php(60): Illuminate\\Http\\Client\\PendingRequest->send()\n#1 {main}";

        DB::table('failed_jobs')->insert(array_merge([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => $payload,
            'exception' => $exception,
            'failed_at' => now(),
        ], array_diff_key($attributes, ['uuid' => 1])));

        return $uuid;
    }

    protected function rule(AlertKind $kind, array $attributes = []): AlertRule
    {
        $rule = AlertRule::ensureAll()[$kind->value];
        if ($attributes !== []) {
            $rule->forceFill($attributes)->save();
        }

        return $rule->refresh();
    }

    /**
     * Only this kind's rule is enabled, so a test sees its alerts and nothing else.
     */
    protected function onlyRule(AlertKind $kind, array $attributes = []): AlertRule
    {
        AlertRule::ensureAll();
        AlertRule::query()->where('kind', '<>', $kind->value)->update(['enabled' => false]);

        return $this->rule($kind, $attributes);
    }

    protected function makeAlert(AlertKind $kind = AlertKind::QUEUE_BACKLOG, array $attributes = []): Alert
    {
        $rule = $this->rule($kind);

        return Alert::query()->create(array_merge([
            'alert_rule_id' => $rule->id,
            'kind' => $kind->value,
            'subject_key' => 'default',
            'severity' => $kind->severity(),
            'state' => 'open',
            'title' => 'Test ogohlantirish',
            'details' => ['size' => 900],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'count' => 1,
        ], $attributes));
    }

    protected function terminal(Store $store, array $attributes = []): Device
    {
        return Device::query()->forceCreate(array_merge([
            'uuid' => (string) Str::uuid(),
            'store_id' => $store->id,
            'type' => DeviceType::POS->value,
            'name' => 'Kassa 1',
            'last_seen_at' => now(),
        ], $attributes));
    }

    protected function failedReceipt(Store $store, array $attributes = []): FiscalReceipt
    {
        return FiscalReceipt::query()->forceCreate(array_merge([
            'uuid' => (string) Str::uuid(),
            'store_id' => $store->id,
            'type' => 'sale',
            'payment_form' => 'cash',
            'status' => FiscalReceiptStatus::FAILED->value,
            'driver' => 'sandbox',
            'idempotency_key' => Str::random(20),
            'total' => '50000.00',
            'lines' => [],
            'payments' => [],
            'error' => 'rejected',
        ], $attributes));
    }
}
