<?php

namespace App\Domain\Pos\Accounts;

use App\Domain\Pos\Booking\UzPhone;
use App\Domain\Pos\Setup\PosSettings;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Models\Branch;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerAccountMember;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\Store;
use App\Support\Money;
use App\Support\StoreTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * P6-44 (D4): the rules of a corporate account (a company whose staff eat on its account), shared by the charge at the
 * till (CustomerAccountService::charge) and the till's member lookup.
 *
 * Every charge of a corporate account is stamped with the business day of the bill's branch (BusinessDay: Asia/Tashkent
 * by default, the day starting at the branch's business_day_starts_at, 05:00 by default: a lunch at 04:59 is still
 * yesterday's), and a reversal of it (a refund at the till) with the same day, so the limits count what was eaten net
 * of refunds:
 *
 *  - member_daily_limit: the member's charges of the day plus this one over their `daily_limit`;
 *  - member_meals_used: the member's meals of the day (bills charged and not fully refunded) already `meals_per_day`;
 *  - account_monthly_limit: the account's charges of the calendar month of that business day over `monthly_limit`;
 *  - the credit limit stays the account's (`account_credit_limit` for a corporate account).
 *
 * Read under the account's row lock (the charge locks it), so two tills cannot both take a member's last meal.
 */
final class CorporateAccounts
{
    public const CODE_PATTERN = '/^[A-Z0-9-]{4,32}$/';

    /**
     * The business date a charge of `billUuid` counts in: the bill's branch's business day now; a bill not found (a
     * direct ledger call) falls back to the account's branch, else the store's time zone with the default day start.
     */
    public function businessDateOf(string $billUuid, CustomerAccount $account, ?\DateTimeInterface $at = null): string
    {
        $bill = OrderBill::where('uuid', $billUuid)->first(['id', 'order_id']);
        $branchId = $bill !== null ? Order::whereKey($bill->order_id)->value('branch_id') : null;

        return $this->dateIn($branchId !== null ? (int) $branchId : null, $account, $at);
    }

    /**
     * The business date for the till's lookup: the caller's branch, else as businessDateOf.
     */
    public function businessDateAt(?int $branchId, CustomerAccount $account): string
    {
        return $this->dateIn($branchId, $account, null);
    }

    private function dateIn(?int $branchId, CustomerAccount $account, ?\DateTimeInterface $at): string
    {
        $branch = Branch::where('store_id', $account->store_id)->find($branchId ?? $account->branch_id);
        if ($branch !== null) {
            return BusinessDay::of($branch, $at);
        }

        return PosSettings::businessDate($at ?? now(), StoreTimezone::of(Store::find($account->store_id)), PosSettings::DEFAULT_BUSINESS_DAY_START);
    }

    public static function normalizeCode(?string $code): ?string
    {
        $code = $code !== null ? strtoupper(trim($code)) : '';

        return $code !== '' ? $code : null;
    }

    /**
     * A member of the store by their code or phone (one of them); null when none matches.
     */
    public function findInStore(int $storeId, ?string $code, ?string $phone): ?CustomerAccountMember
    {
        $code = self::normalizeCode($code);
        $phone = $phone !== null && trim($phone) !== '' ? UzPhone::normalize($phone) : null;
        if ($code === null && $phone === null) {
            return null;
        }

        return CustomerAccountMember::where('store_id', $storeId)
            ->when($code !== null, fn ($query) => $query->where('code', $code), fn ($query) => $query->where('phone', $phone))
            ->first();
    }

    /**
     * The member a charge names, of this account; 404 member_not_found when the code or phone is of no member of it.
     */
    public function memberOf(CustomerAccount $account, ?string $code, ?string $phone): ?CustomerAccountMember
    {
        if (self::normalizeCode($code) === null && ($phone === null || trim($phone) === '')) {
            return null;
        }
        $member = $this->findInStore((int) $account->store_id, $code, $phone);
        if ($member === null || (int) $member->customer_account_id !== (int) $account->id) {
            throw new CustomerAccountException("Xodim topilmadi: talon kodi yoki telefonni tekshiring.", 'member_not_found', 404);
        }

        return $member;
    }

    /**
     * What a member ate on a business day: the net sum and the meals (bills charged and not fully refunded).
     *
     * @return array{amount: Money, meals: int}
     */
    public function memberDay(CustomerAccountMember $member, string $date): array
    {
        $entries = CustomerAccountEntry::where('member_id', $member->id)
            ->whereDate('business_date', $date)
            ->get(['type', 'amount', 'bill_uuid', 'idempotency_key']);

        return ['amount' => self::net($entries), 'meals' => self::meals($entries)];
    }

    /**
     * The account's net charges in the calendar month of the business date `date`.
     */
    public function accountMonth(CustomerAccount $account, string $date): Money
    {
        [$first, $last] = self::monthRange(substr($date, 0, 7));

        return Money::of((string) CustomerAccountEntry::where('customer_account_id', $account->id)
            ->whereNotNull('business_date')
            ->whereDate('business_date', '>=', $first)
            ->whereDate('business_date', '<=', $last)
            ->sum('amount'));
    }

    /**
     * Refuses a corporate charge the limits do not allow (the account is locked by the caller).
     *
     * @throws CustomerAccountException
     */
    public function assertCanCharge(CustomerAccount $account, ?CustomerAccountMember $member, Money $amount, string $date): void
    {
        if ($member === null && $account->talon_mode->needsMember()) {
            throw new CustomerAccountException("Bu korxona hisobida har bir to'lov xodim talonining kodi yoki telefoni bilan yoziladi.", 'member_required');
        }

        if ($member !== null) {
            if (!$member->is_active) {
                throw new CustomerAccountException("\"{$member->name}\" taloni o'chirilgan: korxona hisobiga yozib bo'lmaydi.", 'member_inactive');
            }
            $day = $this->memberDay($member, $date);
            if ($member->daily_limit !== null && $day['amount']->plus($amount)->isGreaterThan($member->daily_limit)) {
                $left = Money::max(Money::zero(), Money::of($member->daily_limit)->minus($day['amount']));
                throw new CustomerAccountException(
                    "\"{$member->name}\" ning kunlik limiti yetmaydi: limit {$member->daily_limit} so'm, bugun {$day['amount']->toString()} so'm, yana {$left->toString()} so'm mumkin.",
                    'member_daily_limit',
                    422,
                    ['daily_limit' => $member->daily_limit, 'used' => $day['amount']->toString(), 'available' => $left->toString(), 'business_date' => $date],
                );
            }
            if ($member->meals_per_day !== null && $day['meals'] >= (int) $member->meals_per_day) {
                throw new CustomerAccountException(
                    "\"{$member->name}\" bugungi ovqatini olgan: kuniga {$member->meals_per_day} marta.",
                    'member_meals_used',
                    422,
                    ['meals_per_day' => (int) $member->meals_per_day, 'meals' => $day['meals'], 'business_date' => $date],
                );
            }
        }

        if ($account->monthly_limit !== null) {
            $month = $this->accountMonth($account, $date);
            if ($month->plus($amount)->isGreaterThan($account->monthly_limit)) {
                $left = Money::max(Money::zero(), Money::of($account->monthly_limit)->minus($month));
                throw new CustomerAccountException(
                    "\"{$account->name}\" ning oylik limiti yetmaydi: limit {$account->monthly_limit} so'm, shu oy {$month->toString()} so'm, yana {$left->toString()} so'm mumkin.",
                    'account_monthly_limit',
                    422,
                    ['monthly_limit' => $account->monthly_limit, 'used' => $month->toString(), 'available' => $left->toString()],
                );
            }
        }
    }

    /**
     * What the till shows before charging: the member's day and the account's month, with what is left.
     *
     * @return array{business_date: string, member: array{amount: string, meals: int, daily_limit: string|null, daily_available: string|null, meals_per_day: int|null, meals_left: int|null}|null, month: array{amount: string, monthly_limit: string|null, available: string|null}, credit_available: string|null}
     */
    public function usage(CustomerAccount $account, ?CustomerAccountMember $member, string $date): array
    {
        $memberUsage = null;
        if ($member !== null) {
            $day = $this->memberDay($member, $date);
            $memberUsage = [
                'amount' => $day['amount']->toString(),
                'meals' => $day['meals'],
                'daily_limit' => $member->daily_limit,
                'daily_available' => $member->daily_limit !== null ? Money::max(Money::zero(), Money::of($member->daily_limit)->minus($day['amount']))->toString() : null,
                'meals_per_day' => $member->meals_per_day !== null ? (int) $member->meals_per_day : null,
                'meals_left' => $member->meals_per_day !== null ? max(0, (int) $member->meals_per_day - $day['meals']) : null,
            ];
        }
        $month = $this->accountMonth($account, $date);

        return [
            'business_date' => $date,
            'member' => $memberUsage,
            'month' => [
                'amount' => $month->toString(),
                'monthly_limit' => $account->monthly_limit,
                'available' => $account->monthly_limit !== null ? Money::max(Money::zero(), Money::of($account->monthly_limit)->minus($month))->toString() : null,
            ],
            'credit_available' => $account->credit_limit !== null ? Money::max(Money::zero(), Money::of($account->credit_limit)->minus($account->balance))->toString() : null,
        ];
    }

    /**
     * The monthly statement of a corporate account (`month` YYYY-MM): what each member ate on each business day of the
     * month (net of refunds; charges without a member in a row of their own), the totals by member, by day and in all,
     * and the repayments received in the month.
     *
     * @return array{month: string, from: string, to: string, days: list<string>, rows: list<array{member: array{uuid: string, name: string, code: string, is_active: bool}|null, cells: list<array{date: string, amount: string, meals: int}>, amount: string, meals: int}>, day_totals: list<array{date: string, amount: string, meals: int}>, total: string, meals: int, repaid_total: string, monthly_limit: string|null, balance: string}
     */
    public function statement(CustomerAccount $account, string $month): array
    {
        [$first, $last] = self::monthRange($month);
        $days = [];
        for ($day = Carbon::parse($first); $day->lte(Carbon::parse($last)); $day->addDay()) {
            $days[] = $day->toDateString();
        }

        $entries = CustomerAccountEntry::where('customer_account_id', $account->id)
            ->whereNotNull('business_date')
            ->whereDate('business_date', '>=', $first)
            ->whereDate('business_date', '<=', $last)
            ->orderBy('id')
            ->get(['id', 'type', 'amount', 'bill_uuid', 'idempotency_key', 'member_id', 'business_date']);

        $members = CustomerAccountMember::where('customer_account_id', $account->id)
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $entries->pluck('member_id')->filter()->unique()->values()))
            ->orderBy('name')->orderBy('id')
            ->get();

        $rows = [];
        $rowOf = function (?CustomerAccountMember $member, Collection $own) use ($days): array {
            $cells = [];
            foreach ($own->groupBy(fn (CustomerAccountEntry $entry) => $entry->business_date?->toDateString()) as $date => $ofDay) {
                $cells[(string) $date] = ['date' => (string) $date, 'amount' => self::net($ofDay)->toString(), 'meals' => self::meals($ofDay)];
            }
            $ordered = array_values(array_filter(array_map(fn (string $date) => $cells[$date] ?? null, $days)));

            return [
                'member' => $member !== null ? ['uuid' => (string) $member->uuid, 'name' => (string) $member->name, 'code' => (string) $member->code, 'is_active' => (bool) $member->is_active] : null,
                'cells' => $ordered,
                'amount' => self::net($own)->toString(),
                'meals' => self::meals($own),
            ];
        };
        foreach ($members as $member) {
            $rows[] = $rowOf($member, $entries->where('member_id', $member->id));
        }
        $without = $entries->whereNull('member_id');
        if ($without->isNotEmpty()) {
            $rows[] = $rowOf(null, $without);
        }

        $dayTotals = [];
        foreach ($days as $date) {
            $ofDay = $entries->filter(fn (CustomerAccountEntry $entry) => $entry->business_date?->toDateString() === $date);
            if ($ofDay->isNotEmpty()) {
                $dayTotals[] = ['date' => $date, 'amount' => self::net($ofDay)->toString(), 'meals' => self::meals($ofDay)];
            }
        }

        // Repayments are not tied to a business day: those received in the month, by the store's calendar
        $tz = StoreTimezone::of(Store::find($account->store_id));
        $repaid = Money::of((string) CustomerAccountEntry::where('customer_account_id', $account->id)
            ->where('type', CustomerAccountEntryType::REPAYMENT->value)
            ->where('created_at', '>=', Carbon::parse($first, $tz)->startOfDay()->utc())
            ->where('created_at', '<', Carbon::parse($last, $tz)->addDay()->startOfDay()->utc())
            ->sum('amount'))->abs();

        return [
            'month' => $month,
            'from' => $first,
            'to' => $last,
            'days' => $days,
            'rows' => $rows,
            'day_totals' => $dayTotals,
            'total' => self::net($entries)->toString(),
            'meals' => self::meals($entries),
            'repaid_total' => $repaid->toString(),
            'monthly_limit' => $account->monthly_limit,
            'balance' => $account->balance,
        ];
    }

    /**
     * First and last day of a YYYY-MM month.
     *
     * @return array{0: string, 1: string}
     */
    public static function monthRange(string $month): array
    {
        $first = Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfDay();

        return [$first->toDateString(), $first->copy()->endOfMonth()->toDateString()];
    }

    /**
     * A new talon code: ten digits (easy to type on the till's keypad when a chit will not scan), unique in the store.
     */
    public static function newCode(int $storeId): string
    {
        do {
            $code = (string) random_int(1_000_000_000, 9_999_999_999);
        } while (CustomerAccountMember::where('store_id', $storeId)->where('code', $code)->exists());

        return $code;
    }

    /**
     * @param  Collection<int, CustomerAccountEntry>  $entries
     */
    private static function net(Collection $entries): Money
    {
        return Money::sum(...$entries->pluck('amount')->all());
    }

    /**
     * Meals: the bills charged whose charge is not fully refunded.
     *
     * @param  Collection<int, CustomerAccountEntry>  $entries
     */
    private static function meals(Collection $entries): int
    {
        $byBill = [];
        foreach ($entries as $entry) {
            $bill = $entry->type === CustomerAccountEntryType::CHARGE
                ? $entry->bill_uuid
                : (preg_match('/^bill-reversal:([0-9a-f-]{36}):/', (string) $entry->idempotency_key, $match) === 1 ? $match[1] : null);
            if ($bill === null) {
                continue;
            }
            $byBill[$bill] = Money::of($byBill[$bill] ?? '0')->plus($entry->amount)->toString();
        }

        return count(array_filter($byBill, fn (string $net) => Money::of($net)->isPositive()));
    }
}
