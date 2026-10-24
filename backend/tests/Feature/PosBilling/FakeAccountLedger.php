<?php

namespace Tests\Feature\PosBilling;

use App\Domain\Pos\Billing\PosActionException;
use App\Domain\Pos\Contracts\CustomerAccountLedger;
use App\Support\Money;

/**
 * CustomerAccountLedger for the P3-18 tests: records every charge; the account BLOCKED is refused (as over its limit).
 */
class FakeAccountLedger implements CustomerAccountLedger
{
    public const BLOCKED = '00000000-0000-4000-8000-000000000bad';

    /** @var list<array{account: string, amount: string, bill_uuid: string, approved_by: int}> */
    public array $charges = [];

    /** @var list<array{account: string, amount: string, bill_uuid: string, reference: string, approved_by: int}> */
    public array $reversals = [];

    public function charge(string $accountUuid, Money $amount, string $billUuid, int $approvedByUserId): void
    {
        if ($accountUuid === self::BLOCKED) {
            throw PosActionException::invalid('credit_limit_exceeded', 'Nasiya limiti yetmaydi.');
        }

        $this->charges[] = ['account' => $accountUuid, 'amount' => $amount->toString(), 'bill_uuid' => $billUuid, 'approved_by' => $approvedByUserId];
    }

    public function reverse(string $accountUuid, Money $amount, string $billUuid, string $reference, int $approvedByUserId): void
    {
        $this->reversals[] = ['account' => $accountUuid, 'amount' => $amount->toString(), 'bill_uuid' => $billUuid, 'reference' => $reference, 'approved_by' => $approvedByUserId];
    }
}
