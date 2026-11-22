<?php

namespace Tests\Feature\PosQr;

use App\Domain\Pos\QrPayments\UzQr\UzQrOperatorProtocol as Api;
use App\Models\Device;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * Wave P: the real UzQR driver (`uzqr_operator`) on the PosQr till. The operator's answers are built here in the
 * shape UzQrOperatorProtocol reads (a guess until the operator's API spec comes: swap both together). Keys are made
 * at runtime: no key-shaped literal anywhere.
 */
trait UzQrOperatorFixtures
{
    protected string $tillKey;

    protected function setUpOperator(): void
    {
        config([
            'qr_payments.uzqr.operator.url' => 'https://uzqr-prod.example.test/api',
            'qr_payments.uzqr.operator.test_url' => 'https://uzqr-test.example.test/api',
            'qr_payments.uzqr.operator.test' => true,
        ]);
        $this->store->forceFill(['tin' => '301234567', 'tax_regime' => 'vat', 'fiscal_settings' => ['enabled' => true, 'uzqr' => ['driver' => 'uzqr_operator']]])->save();
        $this->tillKey = 'uzk' . bin2hex(random_bytes(20));
        Http::preventStrayRequests();
    }

    protected function base(): string
    {
        return 'https://uzqr-test.example.test/api';
    }

    protected function setTillKey(?Device $device = null, ?string $key = null): TestResponse
    {
        $device ??= $this->till['device'];

        return $this->owner('PUT', "/owner/fiscal/uzqr/tills/{$device->uuid}", ['api_key' => $key ?? $this->tillKey]);
    }

    /** @var array<string, mixed> protocol path => the operator's answer now */
    private array $operatorAnswers = [];

    private bool $operatorFaked = false;

    /**
     * Sets what the operator answers on these paths (later calls replace a path's answer; Http::fake's first stub
     * would win otherwise).
     *
     * @param  array<string, mixed>  $answers  protocol path => answer (an array body, a Http response or a sequence)
     */
    protected function fakeOperator(array $answers): void
    {
        $this->operatorAnswers = array_merge($this->operatorAnswers, $answers);
        if ($this->operatorFaked) {
            return;
        }
        $this->operatorFaked = true;
        Http::fake(function (Request $request) {
            if (!str_starts_with($request->url(), $this->base())) {
                return null;
            }
            $answer = $this->operatorAnswers[substr($request->url(), strlen($this->base()))] ?? null;

            return match (true) {
                $answer === null => Http::response(['success' => false, 'error' => ['code' => 'NOT_FAKED']], 404),
                is_array($answer) => Http::response($answer),
                is_callable($answer) => $answer($request),
                default => $answer,
            };
        });
    }

    /** @return array<string, mixed> */
    protected function createdAnswer(string $invoiceId = 'inv-5001', string $qr = '00020101021226350011uz.uzqr.dyn'): array
    {
        return ['success' => true, 'data' => ['invoice_id' => $invoiceId, 'qr' => $qr, 'status' => 'created']];
    }

    /** @return array<string, mixed> */
    protected function statusAnswer(string $status, array $extra = []): array
    {
        return ['success' => true, 'data' => array_merge(['invoice_id' => 'inv-5001', 'status' => $status], $extra)];
    }

    /** @return array<string, mixed> */
    protected function paidAnswer(int $tiyin = 14300000, array $extra = []): array
    {
        return $this->statusAnswer('paid', array_merge(['pay_id' => 'pay-777001', 'rrn' => '612345678901', 'amount' => $tiyin, 'card_type' => 'HUMO', 'card_no' => '986000******4321'], $extra));
    }

    /** @return array<string, mixed> */
    protected function refundAnswer(string $status, string $refundId = 'rf-9001'): array
    {
        return ['success' => true, 'data' => ['refund_id' => $refundId, 'status' => $status]];
    }

    /** @return array<string, mixed> */
    protected function errorAnswer(string $code = 'INVOICE_NOT_FOUND', string $message = 'Not found'): array
    {
        return ['success' => false, 'error' => ['code' => $code, 'message' => $message]];
    }

    protected function sentTo(string $path): ?Request
    {
        $found = null;
        Http::assertSent(function (Request $request) use ($path, &$found) {
            if ($request->url() === $this->base() . $path) {
                $found = $request;

                return true;
            }

            return false;
        });

        return $found;
    }

    protected function countSent(string $path): int
    {
        return Http::recorded(fn (Request $request) => $request->url() === $this->base() . $path)->count();
    }

    protected function keyOf(Request $request): ?string
    {
        return $request->header(Api::AUTH_HEADER)[0] ?? null;
    }
}
