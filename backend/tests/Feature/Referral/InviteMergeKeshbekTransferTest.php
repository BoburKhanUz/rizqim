<?php

namespace Tests\Feature\Referral;

use App\Enums\UserRole;
use App\Jobs\SendTelegramRequest;
use App\Models\KeshbekTransfer;
use App\Models\StoreTransaction;
use App\Models\User;
use App\Services\Admin\Customers\KeshbekHistory;
use App\Services\Finance\LedgerService;
use App\Services\Finance\Reports\KeshbekLiabilityReport;
use App\Services\Order\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * Owner decision A: an unclaimed employee invite row merged into the account that accepted it (User::mergeInviteInto)
 * hands its keshbek to that account: a non-zero balance (a debt too) moves to the account with a KeshbekTransfer row,
 * the friends who named the invite row as referrer name the account instead (the account itself: no referrer, never
 * a self-referral), and the invite row, holding nothing any more, is deleted. KeshbekLiabilityReport shows the move
 * (`transferred`), which nets to zero platform-wide: the difference stays 0. The account's keshbek history shows
 * "Taklif hisobidan ko'chirildi" in place of the moved friends' earlier bonuses (which the invite row received), so
 * the history adds up to the balance.
 */
class InviteMergeKeshbekTransferTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use ReferralOrders;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([SendTelegramRequest::class]);
        $this->setUpLifecycleStore();
        $this->store->forceFill(['credit_limit' => -1000000])->save();
    }

    private function tashkent(string $at): Carbon
    {
        return Carbon::parse($at, 'Asia/Tashkent')->setTimezone((string) config('app.timezone'));
    }

    private function invite(): User
    {
        return User::create([
            'name' => 'Yangi menejer', 'telegram_id' => 'temp_' . uniqid(), 'store_id' => $this->store->id,
            'role' => UserRole::MANAGER, 'telegram_invite_token' => 'emp_' . uniqid(),
        ]);
    }

    private function account(array $attributes = []): User
    {
        return User::create(array_merge(['name' => 'Kassir', 'telegram_id' => (string) random_int(7000000, 7999999), 'store_id' => $this->store->id, 'role' => UserRole::CASHIER], $attributes));
    }

    /** @return array<string, mixed> */
    private function report(): array
    {
        return app(KeshbekLiabilityReport::class)->build(now()->setTimezone('Asia/Tashkent')->format('Y-m'));
    }

    /** @param array<string, mixed> $history */
    private function historySum(array $history): string
    {
        return \App\Support\Money::sum(...array_column($history['entries'], 'amount'))->toString();
    }

    public function test_the_invite_rows_bonus_moves_to_the_account_with_a_record_and_the_friend_follows(): void
    {
        $this->travelTo($this->tashkent('2026-10-05 12:00'));
        $invite = $this->invite();
        $this->referrer = $invite;
        $friend = $this->makeCustomer(0, ['referred_by' => $invite->id]);
        $friend->forceFill(['referred_by' => $invite->id])->save();
        $earned = $this->deliveredOrder('platform_click', ['user_id' => $friend->id]);
        $this->assertSame('5000.00', (string) $invite->fresh()->balance, 'the real grant credited the invite row');
        $this->assertSame('0.00', $this->report()['reconciliation']['difference']->toString());

        $this->travelTo($this->tashkent('2026-10-06 12:00'));
        $account = $this->account();
        $invite->fresh()->mergeInviteInto($account, ['role' => UserRole::MANAGER, 'branch_id' => $this->branch->id, 'name' => 'Menejer']);

        $this->assertNull(User::find($invite->id), 'holding nothing any more, the invite row is deleted');
        $this->assertSame([UserRole::MANAGER, '5000.00'], [$account->fresh()->role, (string) $account->fresh()->balance]);
        $this->assertSame($account->id, (int) $friend->fresh()->referred_by, 'the friend names the account that can sign in');
        $transfer = KeshbekTransfer::sole();
        $this->assertSame([$invite->id, $account->id, '5000.00', KeshbekTransfer::REASON_INVITE_MERGE, [$friend->id]], [
            (int) $transfer->from_user_id, (int) $transfer->to_user_id, (string) $transfer->amount, $transfer->reason, $transfer->moved_friend_ids,
        ]);

        $report = $this->report();
        $this->assertSame(['count' => 1, 'amount' => '5000.00'], ['count' => $report['transferred']['count'], 'amount' => $report['transferred']['amount']->toString()]);
        $this->assertSame(1, $report['issued']['count']);
        $this->assertSame(['5000.00', '5000.00', '0.00'], [$report['reconciliation']['expected_now']->toString(), $report['reconciliation']['live']->toString(), $report['reconciliation']['difference']->toString()]);

        $history = app(KeshbekHistory::class)->for($account->fresh());
        $kinds = array_column($history['entries'], 'kind');
        $this->assertSame(['transfer'], $kinds, "the friend's earlier bonus went to the invite row: it is in the transfer");
        $this->assertSame("Taklif hisobidan ko'chirildi", $history['entries'][0]['label']);
        $this->assertSame('5000.00', $history['entries'][0]['amount']);
        $this->assertSame('5000.00', $this->historySum($history));

        // The friend's order refunded in full: its bonus is taken back from the account that now holds it
        $this->travelTo($this->tashkent('2026-10-07 12:00'));
        $admin = $this->makeStaff(UserRole::SUPER_ADMIN, null, null, ['store_id' => null]);
        $this->assertTrue(app(OrderStatusService::class)->refund($earned->fresh(), $admin, 'Mijoz shikoyati')->changed);
        $this->assertSame('0.00', (string) $account->fresh()->balance);
        $this->assertSame('0.00', $this->report()['reconciliation']['difference']->toString());
        $history = app(KeshbekHistory::class)->for($account->fresh());
        $this->assertSame(['referral', 'transfer'], array_column($history['entries'], 'kind'));
        $this->assertSame('0.00', $this->historySum($history));
    }

    public function test_a_debt_moves_too_and_the_account_that_was_the_friend_gets_no_referrer(): void
    {
        $this->travelTo($this->tashkent('2026-10-05 12:00'));
        $invite = $this->invite();
        $account = $this->account();
        $account->forceFill(['referred_by' => $invite->id])->save();
        $other = $this->makeCustomer(0, ['referred_by' => $invite->id]);
        $other->forceFill(['referred_by' => $invite->id])->save();
        // A recorded grant and clawback left the invite row at −5 000 (its bonus already gone): the report is square
        $row = fn (string $amount, string $notes) => StoreTransaction::forceCreate([
            'store_id' => $this->store->id, 'type' => 'referral_bonus', 'amount' => $amount, 'balance_before' => '0.00', 'balance_after' => '0.00', 'notes' => $notes,
        ]);
        $row('-5000.00', LedgerService::referralClawbackNote($other->id, 'AB12'));
        DB::table('users')->where('id', $invite->id)->update(['balance' => '-5000.00']);
        DB::table('users')->where('id', $account->id)->update(['balance' => '12000.00']);
        $row('5000.00', LedgerService::referralBonusNote($other->id));
        $row('5000.00', LedgerService::referralBonusNote($account->id));
        $row('2000.00', LedgerService::referralBonusNote(999001));
        $this->assertSame('0.00', $this->report()['reconciliation']['difference']->toString());

        $invite->fresh()->mergeInviteInto($account, ['role' => UserRole::MANAGER]);

        $this->assertNull(User::find($invite->id));
        $this->assertSame('7000.00', (string) $account->fresh()->balance, 'the debt moved with the row');
        $this->assertNull($account->fresh()->referred_by, 'never a self-referral');
        $this->assertSame($account->id, (int) $other->fresh()->referred_by);
        $transfer = KeshbekTransfer::sole();
        $this->assertSame(['-5000.00', [$other->id]], [(string) $transfer->amount, $transfer->moved_friend_ids]);
        $report = $this->report();
        $this->assertSame([1, '-5000.00', '0.00'], [$report['transferred']['count'], $report['transferred']['amount']->toString(), $report['reconciliation']['difference']->toString()]);
    }

    public function test_an_invite_row_with_friends_but_no_balance_hands_over_the_friends_and_writes_no_transfer(): void
    {
        $invite = $this->invite();
        $account = $this->account();
        $friend = $this->makeCustomer(0);
        $friend->forceFill(['referred_by' => $invite->id])->save();

        $invite->fresh()->mergeInviteInto($account, ['role' => UserRole::MANAGER]);

        $this->assertNull(User::find($invite->id));
        $this->assertSame($account->id, (int) $friend->fresh()->referred_by);
        $this->assertSame(0, KeshbekTransfer::count());
        $this->assertSame(0, $this->report()['transferred']['count']);
    }

    /** The migration rolls back and forward again (a plain new table; DDL, so as the schema owner). */
    #[\Tests\Concerns\AsSchemaOwner]
    public function test_the_migration_rolls_back_and_forward(): void
    {
        $migration = require database_path('migrations/2026_12_12_100000_create_keshbek_transfers_table.php');
        $migration->down();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('keshbek_transfers'));
        $migration->up();
        $this->assertSame(['id', 'from_user_id', 'to_user_id', 'amount', 'reason', 'moved_friend_ids', 'created_at'], \Illuminate\Support\Facades\Schema::getColumnListing('keshbek_transfers'));
    }

    public function test_a_transfer_record_cannot_be_changed_or_deleted(): void
    {
        $invite = $this->invite();
        $account = $this->account();
        DB::table('users')->where('id', $invite->id)->update(['balance' => '5000.00']);
        $invite->fresh()->mergeInviteInto($account, ['role' => UserRole::MANAGER]);

        $transfer = KeshbekTransfer::sole();
        $this->assertThrows(fn () => $transfer->forceFill(['amount' => '1.00'])->save(), \LogicException::class);
        $this->assertThrows(fn () => $transfer->delete(), \LogicException::class);
    }
}
