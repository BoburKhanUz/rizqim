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
}
