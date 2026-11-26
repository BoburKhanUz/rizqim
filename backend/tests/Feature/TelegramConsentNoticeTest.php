<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Click = consent": the bot's phone request (the only message carrying the request_contact keyboard) says that
 * sending the number accepts the Terms of Use (/shartlar) and the Privacy Policy (/maxfiylik) on the public site.
 */
class TelegramConsentNoticeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.webhook_secret' => 'consent-secret',
            'services.telegram.bot_token' => '123456:fake-token',
            'services.telegram.webapp_url' => 'https://app.example.test',
        ]);
        Http::fake();
    }

    protected function start(string $telegramUserId)
    {
        return $this->postJson('/api/v1/telegram/webhook', ['update_id' => random_int(1, 1000000), 'message' => [
            'message_id' => 1,
            'from' => ['id' => (int) $telegramUserId, 'first_name' => 'New'],
            'chat' => ['id' => (int) $telegramUserId],
            'text' => '/start',
        ]], ['X-Telegram-Bot-Api-Secret-Token' => 'consent-secret']);
    }

    /** @return array<int, array<string, mixed>> every sendMessage payload sent to the chat */
    protected function sentTo(string $chatId): array
    {
        return Http::recorded()
            ->map(fn ($pair) => $pair[0])
            ->filter(fn ($request) => str_ends_with($request->url(), '/sendMessage') && (string) ($request['chat_id'] ?? '') === $chatId)
            ->map(fn ($request) => $request->data())
            ->values()
            ->all();
    }

    public function test_phone_request_carries_the_consent_line_with_links_to_the_legal_pages(): void
    {
        $this->start('9100')->assertJsonPath('status', 'request_contact_sent');

        $sent = $this->sentTo('9100');
        $this->assertCount(1, $sent);
        $payload = $sent[0];

        $this->assertSame('Markdown', $payload['parse_mode']);
        $this->assertStringContainsString('request_contact', $payload['reply_markup']);
        $this->assertStringContainsString(
            'Telefon raqamingizni yuborib, siz [Foydalanish shartlari](https://rizqim.uz/shartlar) va '
            . '[Maxfiylik siyosati](https://rizqim.uz/maxfiylik)ga rozilik bildirasiz.',
            $payload['text']
        );
        // The link previews would push the button prompt away: off
        $this->assertTrue($payload['link_preview_options']['is_disabled']);
    }

    public function test_customer_without_a_phone_gets_the_same_notice(): void
    {
        User::create(['telegram_id' => '9101', 'name' => 'No Phone', 'role' => UserRole::CUSTOMER->value]);

        $this->start('9101')->assertJsonPath('status', 'request_contact_sent');

        $this->assertStringContainsString('(https://rizqim.uz/maxfiylik)', $this->sentTo('9101')[0]['text']);
    }

    public function test_site_url_comes_from_config_and_is_kept_safe_for_markdown(): void
    {
        config(['services.site.url' => 'https://staging.rizqim.uz/']);

        $this->start('9102');
        $text = $this->sentTo('9102')[0]['text'];
        $this->assertStringContainsString('[Foydalanish shartlari](https://staging.rizqim.uz/shartlar)', $text);
        $this->assertStringContainsString('[Maxfiylik siyosati](https://staging.rizqim.uz/maxfiylik)', $text);

        // A ")" would close the Markdown link early: percent-encoded
        config(['services.site.url' => 'https://example.test/a)b']);
        $this->start('9103');
        $this->assertStringContainsString('(https://example.test/a%29b/shartlar)', $this->sentTo('9103')[0]['text']);
    }

    public function test_default_site_url_is_rizqim_uz(): void
    {
        $this->assertSame('https://rizqim.uz', config('services.site.url'));

        config(['services.site.url' => '']);
        $this->start('9105');
        $this->assertStringContainsString('(https://rizqim.uz/shartlar)', $this->sentTo('9105')[0]['text']);
    }

    public function test_welcome_for_customers_with_a_phone_has_no_consent_line(): void
    {
        User::create(['telegram_id' => '9104', 'name' => 'Has Phone', 'phone' => '+998901234567', 'role' => UserRole::CUSTOMER->value]);

        $this->start('9104')->assertJsonPath('status', 'welcome_sent');

        $this->assertStringNotContainsString('rozilik', $this->sentTo('9104')[0]['text']);
    }
}
