<?php

namespace Tests\Feature\OnlineFiscal;

use App\Domain\Fiscal\FiscalDocument;
use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalIssueResult;
use LogicException;

/**
 * P3-14 tests only: FiscalIssuer (P3-11 implements it) that keeps recorded receipts in memory, idempotent on the
 * document's key.
 */
class FakeOnlineFiscalIssuer implements FiscalIssuer
{
    /** @var array<string, array{document: FiscalDocument, provider: string, data: array<string, mixed>, result: FiscalIssueResult}> */
    public array $recorded = [];

    public int $calls = 0;

    public function issue(FiscalDocument $document): FiscalIssueResult
    {
        throw new LogicException('Online payments never issue receipts themselves');
    }

    public function reissue(FiscalDocument $document): FiscalIssueResult
    {
        return $this->issue($document);
    }

    public function recordExternal(FiscalDocument $document, string $provider, array $providerData): FiscalIssueResult
    {
        $this->calls++;

        if (isset($this->recorded[$document->idempotencyKey])) {
            return $this->recorded[$document->idempotencyKey]['result'];
        }

        $result = new FiscalIssueResult('receipt-' . (count($this->recorded) + 1), 'issued',
            isset($providerData['fiscal_sign']) ? (string) $providerData['fiscal_sign'] : null,
            isset($providerData['qr_code_url']) ? (string) $providerData['qr_code_url'] : null);
        $this->recorded[$document->idempotencyKey] = ['document' => $document, 'provider' => $provider, 'data' => $providerData, 'result' => $result];

        return $result;
    }

    public function hasPending(string $subjectType, int $subjectId): bool
    {
        return false;
    }
}
