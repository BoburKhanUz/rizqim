<?php

namespace Tests\Feature\PosBooking;

use App\Domain\Fiscal\FiscalDocument;
use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalIssueResult;
use Illuminate\Support\Str;

/**
 * FiscalIssuer for the P3-17 tests: records every document, one receipt per idempotency key (a repeat returns it).
 */
class FakeFiscalIssuer implements FiscalIssuer
{
    /** @var list<FiscalDocument> every issue() call, repeats included */
    public array $calls = [];

    /** @var array<string, array{document: FiscalDocument, result: FiscalIssueResult}> */
    public array $receipts = [];

    public bool $fail = false;

    public function issue(FiscalDocument $document): FiscalIssueResult
    {
        $this->calls[] = $document;
        if ($this->fail) {
            throw new \RuntimeException('provider down');
        }

        return ($this->receipts[$document->idempotencyKey] ??= [
            'document' => $document,
            'result' => new FiscalIssueResult((string) Str::uuid(), 'issued'),
        ])['result'];
    }

    public function recordExternal(FiscalDocument $document, string $provider, array $providerData): FiscalIssueResult
    {
        return $this->issue($document);
    }

    public function hasPending(string $subjectType, int $subjectId): bool
    {
        return false;
    }

    /**
     * @return list<FiscalDocument> the distinct receipts registered
     */
    public function documents(): array
    {
        return array_values(array_map(fn (array $receipt) => $receipt['document'], $this->receipts));
    }
}
