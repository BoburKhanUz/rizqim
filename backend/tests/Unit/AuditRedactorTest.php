<?php

namespace Tests\Unit;

use App\Services\Audit\AuditRedactor;
use PHPUnit\Framework\TestCase;

class AuditRedactorTest extends TestCase
{
    public function test_secrets_are_dropped_by_key(): void
    {
        $clean = AuditRedactor::clean([
            'pin' => '4831',
            'pin_hash' => '$2y$...',
            'password' => 'x',
            'token' => 'abc',
            'refresh_token' => 'rt_abc',
            'code' => '123456',
            'code_hash' => 'f00',
            'card_number' => '8600123412341234',
            'initData' => 'query_id=...',
            'telegram_invite_token' => 'emp_x',
            'nested' => ['api_key' => 'k', 'cvv' => '123'],
            'missing_token' => null,
        ]);

        foreach (['pin', 'pin_hash', 'password', 'token', 'refresh_token', 'code', 'code_hash', 'card_number', 'initData', 'telegram_invite_token'] as $key) {
            $this->assertSame(AuditRedactor::REDACTED, $clean[$key], $key);
        }
        $this->assertSame(['api_key' => AuditRedactor::REDACTED, 'cvv' => AuditRedactor::REDACTED], $clean['nested']);
        $this->assertNull($clean['missing_token']);
    }

    public function test_ordinary_fields_stay(): void
    {
        $clean = AuditRedactor::clean([
            'status' => 'canceled',
            'shipping_address' => 'Chilonzor 1',
            'short_code' => 'A1F3C',
            'staff_code' => '0427',
            'price' => '120000000.00',
            'company' => 'Rizqim',
            'courier_id' => 7,
        ]);

        $this->assertSame('canceled', $clean['status']);
        $this->assertSame('Chilonzor 1', $clean['shipping_address']);
        $this->assertSame('A1F3C', $clean['short_code']);
        $this->assertSame('0427', $clean['staff_code']);
        $this->assertSame('120000000.00', $clean['price']);
        $this->assertSame('Rizqim', $clean['company']);
        $this->assertSame(7, $clean['courier_id']);
    }

    public function test_phone_numbers_are_masked_by_key_and_in_text(): void
    {
        $clean = AuditRedactor::clean([
            'phone' => '+998901234567',
            'customer_phone' => '998901234567',
            'note' => 'Mijoz +998 90 123 45 67 va 901234567 raqamidan qo\'ng\'iroq qildi',
        ]);

        $this->assertSame('+99890*****67', $clean['phone']);
        $this->assertStringNotContainsString('1234', $clean['customer_phone']);
        $this->assertStringNotContainsString('123 45', $clean['note']);
        $this->assertStringNotContainsString('901234567', $clean['note']);
        $this->assertStringContainsString("qo'ng'iroq qildi", $clean['note']);
    }

    public function test_digit_runs_inside_ids_are_not_taken_for_phone_numbers(): void
    {
        $clean = AuditRedactor::clean([
            'failed_job_uuid' => '82e324be-e99c-4e72-88ac-b8e989345737',
            'note' => 'Vazifa 82e324be-e99c-4e72-88ac-998901234567 yiqildi, mijoz (901234567) kutmoqda',
        ]);

        $this->assertSame('82e324be-e99c-4e72-88ac-b8e989345737', $clean['failed_job_uuid']);
        $this->assertStringContainsString('82e324be-e99c-4e72-88ac-998901234567', $clean['note']);
        $this->assertStringNotContainsString('(901234567)', $clean['note']);
    }

    // Review W3 S#5: integration events show request headers and URLs through the redactor
    public function test_header_style_secret_names_are_redacted(): void
    {
        $clean = AuditRedactor::clean([
            'headers' => ['Authorization' => 'Bearer ' . 'sk' . '_live_abcdef123456', 'Cookie' => 'session=abc', 'Set-Cookie' => 'a=b', 'X-Auth' => 'zzz', 'X-XSRF-TOKEN' => 'x', 'Content-Type' => 'application/json'],
            'sign_string' => 'deadbeef',
            'signString' => 'deadbeef',
            'sign' => 'cafebabe',
            'bearer' => 'abc',
            'session' => 'sess-1',
            'session_id' => 'sess-2',
            'xsrf' => 'x',
            'api_key' => 'k1',
            'store_id' => 12,
            'method' => 'POST',
        ]);

        foreach (['Authorization', 'Cookie', 'Set-Cookie', 'X-Auth', 'X-XSRF-TOKEN'] as $header) {
            $this->assertSame(AuditRedactor::REDACTED, $clean['headers'][$header], $header);
        }
        $this->assertSame('application/json', $clean['headers']['Content-Type']);
        foreach (['sign_string', 'signString', 'sign', 'bearer', 'session', 'session_id', 'xsrf', 'api_key'] as $key) {
            $this->assertSame(AuditRedactor::REDACTED, $clean[$key], $key);
        }
        $this->assertSame(12, $clean['store_id']);
        $this->assertSame('POST', $clean['method']);
    }

    public function test_token_looking_values_and_token_query_strings_are_masked_in_text(): void
    {
        $clean = AuditRedactor::clean([
            'note' => 'Header Authorization: Bearer abcDEF123456789xyz yuborildi',
            'sanctum' => 'token 12|' . str_repeat('aB3', 14) . ' berildi',
            'url' => 'https://api.example.uz/hook?order=15&token=abc123SECRET&access_token=zzz999&signature=ff00&page=2',
            'jwt' => 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMiJ9.c2lnbmF0dXJl',
            // Built at run time so no key-shaped literal sits in the repository (secret scanning)
            'live' => 'kalit ' . implode('_', ['sk', 'live', 'Fake' . str_repeat('x9Q', 8)]) . ' ishlatildi',
            'bot' => 'https://api.telegram.org/bot123456789:' . 'AAFake' . str_repeat('k7Pq', 8) . 'w/sendMessage',
            'plain' => 'Buyurtma 15 qabul qilindi (page=2)',
        ]);

        $this->assertStringNotContainsString('abcDEF123456789xyz', $clean['note']);
        $this->assertStringContainsString('yuborildi', $clean['note']);
        $this->assertStringNotContainsString('aB3aB3aB3', $clean['sanctum']);
        $this->assertStringNotContainsString('abc123SECRET', $clean['url']);
        $this->assertStringNotContainsString('zzz999', $clean['url']);
        $this->assertStringNotContainsString('ff00', $clean['url']);
        $this->assertStringContainsString('order=15', $clean['url']);
        $this->assertStringContainsString('page=2', $clean['url']);
        $this->assertStringNotContainsString('eyJhbGci', $clean['jwt']);
        $this->assertStringNotContainsString('Fakex9Q', $clean['live']);
        $this->assertStringNotContainsString('AAFakek7Pq', $clean['bot']);
        $this->assertSame('Buyurtma 15 qabul qilindi (page=2)', $clean['plain']);
    }
}
