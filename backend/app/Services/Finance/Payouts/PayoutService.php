<?php

namespace App\Services\Finance\Payouts;

use App\Enums\AdminPermission;
use App\Enums\StoreTransactionType;
use App\Models\PayoutRequest;
use App\Models\Store;
use App\Models\StoreWalletTransaction;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use App\Services\Finance\Netting\NettingService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * P4-23: the payout queue (docs/API.md "Moliya"). A store owner asks for a payout from its wallet
 * (LedgerService::requestPayout, unchanged); a member with admin.payouts.approve approves or rejects it.
 *
 * - Approving pays it out: the store's debt is netted first (NettingService: the oldest subscription invoices, then
 *   the commission debt, each its own debt ledger row), the rest goes to the card. The wallet goes down by the whole
 *   amount in two PAYOUT rows (netted, transferred), both carrying the payout. "Hisoblangan — Ushlangan qarz —
 *   O'tkaziladi" is the card's breakdown.
 * - 1 000 000 so'm or more (config('finance.four_eyes_threshold')): the first approval is recorded and the payout
 *   waits (`awaiting_second`); only a different member completes it (a superadmin is no exception). The store owner
 *   asked, so the members are the two pairs of eyes.
 * - Never twice: every decision runs under the payout's row lock; a repeat of the same decision by the same member
 *   answers the same result (replayed); the HTTP layer also honours Idempotency-Key.
 * - The superadmin panel (Filament) completes payouts through the same complete() (PayoutRequest's updating hook),
 *   but never one that needs two members.
 */
class PayoutService
{
    public const PENDING = 'pending';

    public const AWAITING_SECOND = 'awaiting_second';

    public const COMPLETED = 'completed';

    public const REJECTED = 'rejected';

    public function __construct(
        private readonly NettingService $netting,
        private readonly AdminPermissionService $permissions,
    ) {}

    public static function threshold(): Money
    {
        return Money::of((string) config('finance.four_eyes_threshold', '1000000.00'));
    }

    public function needsSecondApproval(PayoutRequest $payout): bool
    {
        return Money::of($payout->amount)->isGreaterThanOrEqualTo(self::threshold());
    }

    /**
     * pending (no approval yet), awaiting_second (approved once, waits for another member), completed, rejected.
     */
    public static function stage(PayoutRequest $payout): string
    {
        return match ((string) $payout->status) {
            'completed' => self::COMPLETED,
            'rejected' => self::REJECTED,
            default => $payout->getAttribute('first_approved_by') !== null ? self::AWAITING_SECOND : self::PENDING,
        };
    }

    public static function stageLabel(string $stage): string
    {
        return match ($stage) {
            self::PENDING => 'Tasdiq kutilmoqda',
            self::AWAITING_SECOND => 'Ikkinchi tasdiq kutilmoqda',
            self::COMPLETED => "O'tkazildi",
            self::REJECTED => 'Rad etildi',
            default => $stage,
        };
    }

    public function canApprove(PayoutRequest $payout, User $member): bool
    {
        $stage = self::stage($payout);

        return in_array($stage, [self::PENDING, self::AWAITING_SECOND], true)
            && $this->permissions->has($member, AdminPermission::PAYOUTS_APPROVE)
            && !($stage === self::AWAITING_SECOND && (int) $payout->first_approved_by === (int) $member->id);
    }

    public function canReject(PayoutRequest $payout, User $member): bool
    {
        return in_array(self::stage($payout), [self::PENDING, self::AWAITING_SECOND], true)
            && $this->permissions->has($member, AdminPermission::PAYOUTS_APPROVE);
    }

    /**
     * @return array{payout: PayoutRequest, replayed: bool, stage_before: string}
     *
     * @throws PayoutException
     */
    public function approve(PayoutRequest $payout, User $member, string $note): array
    {
        return DB::transaction(function () use ($payout, $member, $note) {
            $payout = PayoutRequest::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $before = self::stage($payout);

            if ($before === self::COMPLETED && (int) $payout->processed_by === (int) $member->id) {
                return ['payout' => $payout, 'replayed' => true, 'stage_before' => $before];
            }
            if (in_array($before, [self::COMPLETED, self::REJECTED], true)) {
                throw new PayoutException("Bu so'rov allaqachon ko'rib chiqilgan: " . mb_strtolower(self::stageLabel($before)) . '.', 'payout_decided', 409);
            }

            if ($this->needsSecondApproval($payout)) {
                if ($before === self::PENDING) {
                    PayoutRequest::whereKey($payout->id)->update([
                        'first_approved_by' => $member->id,
                        'first_approved_at' => now(),
                        'first_approval_note' => mb_substr(trim($note), 0, 500),
                        'updated_at' => now(),
                    ]);

                    return ['payout' => $payout->refresh(), 'replayed' => false, 'stage_before' => $before];
                }
                if ((int) $payout->first_approved_by === (int) $member->id) {
                    throw new PayoutException(
                        self::uzs(self::threshold()) . " va undan katta payoutni ikki xodim tasdiqlaydi: ikkinchi tasdiqni boshqa xodim beradi.",
                        'four_eyes',
                        403,
                    );
                }
            }

            return ['payout' => $this->complete($payout, (int) $member->id, $note), 'replayed' => false, 'stage_before' => $before];
        });
    }

    /**
     * @return array{payout: PayoutRequest, replayed: bool, stage_before: string}
     *
     * @throws PayoutException
     */
    public function reject(PayoutRequest $payout, User $member, string $note): array
    {
        return DB::transaction(function () use ($payout, $member, $note) {
            $payout = PayoutRequest::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $before = self::stage($payout);

            if ($before === self::REJECTED && (int) $payout->processed_by === (int) $member->id) {
                return ['payout' => $payout, 'replayed' => true, 'stage_before' => $before];
            }
            if (in_array($before, [self::COMPLETED, self::REJECTED], true)) {
                throw new PayoutException("Bu so'rov allaqachon ko'rib chiqilgan: " . mb_strtolower(self::stageLabel($before)) . '.', 'payout_decided', 409);
            }

            PayoutRequest::whereKey($payout->id)->update([
                'status' => self::REJECTED,
                'processed_by' => $member->id,
                'processed_at' => now(),
                'resolution_notes' => mb_substr(trim($note), 0, 500),
                'updated_at' => now(),
            ]);

            return ['payout' => $payout->refresh(), 'replayed' => false, 'stage_before' => $before];
        });
    }

    /**
     * Pay out a pending payout: the debt first, the rest to the card. Callers hold the payout's row lock inside
     * their transaction and have checked it may be completed (approve(), or the panel's hook).
     *
     * @throws PayoutException when the wallet cannot cover it (nothing is booked)
     */
    public function complete(PayoutRequest $payout, ?int $performedBy, ?string $note): PayoutRequest
    {
        return DB::transaction(function () use ($payout, $performedBy, $note) {
            $store = Store::where('id', $payout->store_id)->lockForUpdate()->firstOrFail();
            $amount = Money::of($payout->amount);
            $wallet = Money::of($store->wallet_balance);

            if ($wallet->isLessThan($amount)) {
                throw new PayoutException("Hamyonda yetarli mablag' yo'q: " . self::uzs($wallet) . '.', 'insufficient_wallet', 422, ['wallet_balance' => $wallet->toString()]);
            }

            $netting = $this->netting->payDebt(
                $store,
                $amount,
                "Payout #{$payout->id} netted",
                ['payout_request_id' => $payout->id, 'reference_id' => "payout:{$payout->id}"],
                $performedBy,
            );
            $transfer = $netting->remainder();

            if ($netting->netted->isPositive()) {
                $wallet = $this->walletRow($store, $payout, $wallet, $netting->netted, "Payout #{$payout->id}: netted against the store's debt", $performedBy);
            }
            if ($transfer->isPositive()) {
                $wallet = $this->walletRow($store, $payout, $wallet, $transfer, "Payout completed to card: {$payout->card_number}", $performedBy);
            }
            $store->forceFill(['wallet_balance' => $wallet->toString()])->save();

            PayoutRequest::whereKey($payout->id)->update([
                'status' => self::COMPLETED,
                'processed_by' => $performedBy,
                'processed_at' => now(),
                'resolution_notes' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : $payout->resolution_notes,
                'netted_amount' => $netting->netted->toString(),
                'transferred_amount' => $transfer->toString(),
                'netting' => json_encode($netting->linesArray(), JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

            return $payout->refresh();
        });
    }

    private function walletRow(Store $store, PayoutRequest $payout, Money $before, Money $amount, string $notes, ?int $performedBy): Money
    {
        $after = $before->minus($amount);

        StoreWalletTransaction::create([
            'store_id' => $store->id,
            'type' => StoreTransactionType::PAYOUT->value,
            'amount' => $amount->negated()->toString(),
            'balance_before' => $before->toString(),
            'balance_after' => $after->toString(),
            'payout_request_id' => $payout->id,
            'reference_id' => "payout:{$payout->id}",
            'notes' => $notes,
            'performed_by' => $performedBy,
        ]);

        return $after;
    }

    /**
     * "1 000 000 so'm"; with the tiyin when there are any.
     */
    public static function uzs(Money $amount): string
    {
        $decimals = str_ends_with($amount->toString(), '.00') ? 0 : 2;

        return number_format($amount->toFloat(), $decimals, ',', ' ') . " so'm";
    }
}
