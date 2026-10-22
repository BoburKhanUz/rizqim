<?php

namespace Tests\Feature\PosBilling;

use App\Domain\Fiscal\FiscalDocument;
use App\Domain\Fiscal\FiscalException;
use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalIssueResult;
use App\Support\Money;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * FiscalIssuer for the P3-18 tests: one receipt per idempotency key, the answer set by `mode` (issued, pending,
 * failed, or throw), and the sums every document must meet (lines and payments add up to the total, like P3-11's
 * validator), so a wrong mapping fails the test loudly.
 */
class FakeFiscalIssuer implements FiscalIssuer
{
    public string $mode = 'issued';

    /** @var array<string, FiscalDocument> by idempotency key, the latest */
    public array $documents = [];

    /** @var array<string, string> idempotency key => receipt uuid */
    public array $uuids = [];

    /** @var array<string, string> "subject:id" => last status */
    public array $statuses = [];

    public function issue(FiscalDocument $document): FiscalIssueResult
    {
        if ($this->mode === 'throw') {
            throw FiscalException::invalid('Provayder javob bermadi.');
        }

        $lines = Money::sum(...array_map(fn ($line) => $line->amount, $document->lines));
        $payments = Money::sum(...array_map(fn ($payment) => $payment->amount, $document->payments));
        if (!$lines->equals($document->total) || !$payments->equals($document->total)) {
            throw new RuntimeException("Receipt does not add up: lines {$lines}, payments {$payments}, total {$document->total}");
        }

        $this->documents[$document->idempotencyKey] = $document;
        $uuid = $this->uuids[$document->idempotencyKey] ??= (string) Str::uuid();
        $this->statuses[$document->subjectType . ':' . $document->subjectId] = $this->mode;

        return new FiscalIssueResult($uuid, $this->mode, $this->mode === 'issued' ? '123456789012' : null, null, $this->mode === 'failed' ? 'Provayder rad etdi.' : null);
    }

    public function recordExternal(FiscalDocument $document, string $provider, array $providerData): FiscalIssueResult
    {
        throw new RuntimeException('Not used by the POS.');
    }

    public function hasPending(string $subjectType, int $subjectId): bool
    {
        return ($this->statuses["{$subjectType}:{$subjectId}"] ?? null) === 'pending';
    }

    public function document(string $key): FiscalDocument
    {
        return $this->documents[$key] ?? throw new RuntimeException("No receipt {$key}");
    }
}
