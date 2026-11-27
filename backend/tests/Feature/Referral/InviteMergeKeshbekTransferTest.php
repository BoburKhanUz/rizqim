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

    /**
     * Review M2: the account A was itself referred by the invite row P, and A's own 60 000 order earned P the 5 000
     * bonus, which still stands. Merged, A would hold a bonus for its own order that no refund could take back (A's
     * referred_by is cleared). The merge first takes it back as a recorded clawback (a −5 000 referral_bonus row on
     * that order, −5 000 on P), then moves what is left (0: no transfer). A later full refund takes nothing again.
     */
    public function test_a_standing_bonus_the_invite_row_earned_for_the_accounts_own_order_is_taken_back_at_the_merge(): void
    {
        $this->travelTo($this->tashkent('2026-10-05 12:00'));
        $invite = $this->invite();
        $this->referrer = $invite;
        $account = $this->account();
        $account->forceFill(['referred_by' => $invite->id])->save();
        $own = $this->deliveredOrder('platform_click', ['user_id' => $account->id, 'total_price' => 60000, 'commission_amount' => 6000]);
        $this->assertSame(['5000.00', '0.00'], [(string) $invite->fresh()->balance, (string) $account->fresh()->balance]);
        $this->assertSame([['order_id' => $own->id, 'store_id' => $this->store->id, 'amount' => '5000.00']], $this->rewards());

        $this->travelTo($this->tashkent('2026-10-06 12:00'));
        $invite->fresh()->mergeInviteInto($account, ['role' => UserRole::MANAGER]);

        $this->assertNull(User::find($invite->id));
        $this->assertSame('0.00', (string) $account->fresh()->balance, 'no bonus for its own order');
        $this->assertNull($account->fresh()->referred_by);
        $this->assertSame(0, KeshbekTransfer::count(), "the invite row's 0 left: nothing moved");
        $this->assertSame([
            ['order_id' => $own->id, 'store_id' => $this->store->id, 'amount' => '5000.00'],
            ['order_id' => $own->id, 'store_id' => $this->store->id, 'amount' => '-5000.00'],
        ], $this->rewards());
        $clawback = StoreTransaction::where('order_id', $own->id)->where('type', 'referral_bonus')->where('amount', '<', 0)->sole();
        $this->assertStringStartsWith(LedgerService::REFERRAL_CLAWBACK_NOTE . " for referred user #{$account->id}: order #{$own->short_code}", (string) $clawback->notes);
        $report = $this->report();
        $this->assertSame([1, '5000.00', 1, '5000.00', 0, '0.00'], [
            $report['issued']['count'], $report['issued']['amount']->toString(),
            $report['clawed_back']['count'], $report['clawed_back']['amount']->toString(),
            $report['transferred']['count'], $report['reconciliation']['difference']->toString(),
        ]);
        $this->assertSame(0, \App\Services\Auth\ReferralService::monthlyBonusesOf($invite->id, now()), 'counted as a clawback');

        // A later full refund of the order changes nothing and takes nothing back twice
        $this->travelTo($this->tashkent('2026-10-07 12:00'));
        $admin = $this->makeStaff(UserRole::SUPER_ADMIN, null, null, ['store_id' => null]);
        $this->assertTrue(app(OrderStatusService::class)->refund($own->fresh(), $admin, 'Mijoz shikoyati')->changed);
        $this->assertSame('0.00', (string) $account->fresh()->balance);
        $this->assertCount(2, $this->rewards());
        $this->assertSame('0.00', $this->report()['reconciliation']['difference']->toString());
    }

    /**
     * Review (merge lock order): the stores of the account's own bonus orders are read before the users locks. A bonus
     * that commits in between (here: granted by a hook right after that read, at a second store) would make the
     * clawback lock its store after the users rows. The merge reads the orders again under the users locks, finds a
     * store it did not lock first, and starts over: the store first, then the users rows. It completes, the bonus is
     * taken back once, and no store is ever locked after the users rows that was not locked before them.
     */
    public function test_a_bonus_committed_between_the_read_and_the_locks_makes_the_merge_start_over_with_the_store_first(): void
    {
        $this->travelTo($this->tashkent('2026-10-05 12:00'));
        $invite = $this->invite();
        $this->referrer = $invite;
        $account = $this->account();
        $other = $this->makeStore('Ikkinchi do\'kon');
        // Delivered before the account named the invite row: no bonus yet
        $own = $this->deliveredOrder('platform_click', ['user_id' => $account->id], $other);
        $account->forceFill(['referred_by' => $invite->id])->save();
        $this->assertSame([], $this->rewards());

        $level = DB::transactionLevel();
        $granted = false;
        $attempts = 0;
        $locked = [];
        $usersLocked = false;
        $late = [];
        $inHook = false;
        DB::listen(function (\Illuminate\Database\Events\QueryExecuted $query) use (&$granted, &$locked, &$usersLocked, &$late, &$inHook, $own, $invite) {
            $sql = $query->sql;
            if ($inHook) {
                // The concurrent grant's own locks are not the merge's
                return;
            }
            if (preg_match('/from "stores" where ("stores"\.)?"id" (=|in) .*for (no key )?update/', $sql) === 1) {
                // whereKey() inlines integer ids into the SQL; Store::where('id', …) binds one
                $ids = preg_match('/"id" in \(([\d, ]+)\)/', $sql, $inlined) === 1 ? explode(',', $inlined[1]) : $query->bindings;
                foreach ($ids as $id) {
                    if ($usersLocked && !in_array((int) $id, $locked, true)) {
                        $late[] = (int) $id;
                    }
                    $locked[] = (int) $id;
                }
            } elseif (preg_match('/from "users" where ("users"\.)?"id" in .*for update/', $sql) === 1) {
                $usersLocked = true;
            } elseif (!$granted && $sql === 'select "id" from "users" where "referred_by" = ?' && (int) $query->bindings[0] === $invite->id) {
                // Right after the merge read the own-bonus stores, before its users locks: the bonus commits
                $granted = true;
                $inHook = true;
                try {
                    $this->assertTrue(app(LedgerService::class)->grantReferralReward(\App\Models\Order::query()->withoutGlobalScopes()->findOrFail($own->id)));
                } finally {
                    $inHook = false;
                }
            }
        });
        DB::getEventDispatcher()->listen(\Illuminate\Database\Events\TransactionBeginning::class, function () use (&$attempts, &$locked, &$usersLocked, &$inHook, $level) {
            if (!$inHook && DB::transactionLevel() === $level + 1) {
                // A new attempt of the merge's own transaction
                $attempts++;
                $locked = [];
                $usersLocked = false;
            }
        });

        $this->travelTo($this->tashkent('2026-10-06 12:00'));
        $invite->fresh()->mergeInviteInto($account, ['role' => UserRole::MANAGER]);

        $this->assertTrue($granted);
        $this->assertSame([], $late, 'no store locked after the users rows that was not locked before them');
        $this->assertSame(2, $attempts, 'the first attempt saw the new store under the users locks and started over');
        $this->assertNull(User::find($invite->id));
        $this->assertSame(['0.00', UserRole::MANAGER], [(string) $account->fresh()->balance, $account->fresh()->role]);
        $this->assertSame([
            ['order_id' => $own->id, 'store_id' => $other->id, 'amount' => '5000.00'],
            ['order_id' => $own->id, 'store_id' => $other->id, 'amount' => '-5000.00'],
        ], $this->rewards(), 'taken back once');
        $this->assertSame(0, KeshbekTransfer::count());
        $this->assertSame('0.00', $this->report()['reconciliation']['difference']->toString());
    }

    /**
     * Review (history label): a bonus taken back at an invite merge (referralMergeClawbackNote) is not shown as the
     * order having been refunded; a refund's clawback still is.
     */
    public function test_the_keshbek_history_labels_a_merge_clawback_as_such(): void
    {
        $this->travelTo($this->tashkent('2026-10-05 12:00'));
        $referrer = $this->makeCustomer(0);
        $merged = $this->makeCustomer(0, ['name' => 'Aziz']);
        $merged->forceFill(['referred_by' => $referrer->id])->save();
        $refunded = $this->makeCustomer(0, ['name' => 'Bobur']);
        $refunded->forceFill(['referred_by' => $referrer->id])->save();
        $mergedOrder = $this->makeOrder(\App\Enums\OrderStatus::DELIVERED, ['user_id' => $merged->id]);
        $refundedOrder = $this->makeOrder(\App\Enums\OrderStatus::REFUNDED, ['user_id' => $refunded->id]);
        $row = fn (\App\Models\Order $order, string $amount, string $notes, string $at) => StoreTransaction::forceCreate([
            'store_id' => $this->store->id, 'type' => 'referral_bonus', 'amount' => $amount, 'balance_before' => '0.00', 'balance_after' => '0.00',
            'order_id' => $order->id, 'reference_id' => $order->uuid, 'notes' => $notes, 'created_at' => $this->tashkent($at), 'updated_at' => $this->tashkent($at),
        ]);
        $row($mergedOrder, '5000.00', LedgerService::referralBonusNote($merged->id), '2026-10-05 12:00');
        $row($mergedOrder, '-5000.00', LedgerService::referralMergeClawbackNote($merged->id, (string) $mergedOrder->short_code), '2026-10-06 12:00');
        $row($refundedOrder, '5000.00', LedgerService::referralBonusNote($refunded->id), '2026-10-05 13:00');
        $row($refundedOrder, '-5000.00', LedgerService::referralClawbackNote($refunded->id, (string) $refundedOrder->short_code), '2026-10-07 12:00');

        $entries = app(KeshbekHistory::class)->for($referrer->fresh())['entries'];

        $this->assertSame([
            ['-5000.00', "Do'st taklifi bonusi qaytarib olindi (Bobur, #{$refundedOrder->short_code} qaytarildi)"],
            ['-5000.00', "Taklif hisobi birlashtirilgani uchun bonus qaytarib olindi (Aziz, #{$mergedOrder->short_code})"],
            ['5000.00', "Do'st taklifi uchun bonus (Bobur)"],
            ['5000.00', "Do'st taklifi uchun bonus (Aziz)"],
        ], array_map(fn (array $entry) => [$entry['amount'], $entry['label']], $entries));
        $this->assertTrue(LedgerService::isReferralMergeClawbackNote(LedgerService::referralMergeClawbackNote(1, 'AB12')));
        $this->assertFalse(LedgerService::isReferralMergeClawbackNote(LedgerService::referralClawbackNote(1, 'AB12')));
        $this->assertFalse(LedgerService::isReferralMergeClawbackNote(null));
    }

    /**
     * Review M2: only the bonus of the account's own order goes back; the invite row's other friends' bonuses move to
     * the account as before.
     */
    public function test_only_the_accounts_own_bonus_is_taken_back_and_the_rest_moves(): void
    {
        $this->travelTo($this->tashkent('2026-10-05 12:00'));
        $invite = $this->invite();
        $this->referrer = $invite;
        $account = $this->account();
        $account->forceFill(['referred_by' => $invite->id])->save();
        $friend = $this->makeCustomer(0);
        $friend->forceFill(['referred_by' => $invite->id])->save();
        $own = $this->deliveredOrder('platform_click', ['user_id' => $account->id]);
        $this->deliveredOrder('platform_click', ['user_id' => $friend->id]);
        $this->assertSame('10000.00', (string) $invite->fresh()->balance);

        $invite->fresh()->mergeInviteInto($account, ['role' => UserRole::MANAGER]);

        $this->assertSame('5000.00', (string) $account->fresh()->balance);
        $transfer = KeshbekTransfer::sole();
        $this->assertSame(['5000.00', [$friend->id]], [(string) $transfer->amount, $transfer->moved_friend_ids]);
        $this->assertSame('0.00', \App\Support\Money::of((string) StoreTransaction::where('order_id', $own->id)->where('type', 'referral_bonus')->sum('amount'))->toString(), "the account's own bonus taken back");
        $this->assertSame('0.00', $this->report()['reconciliation']['difference']->toString());
    }

    /**
     * Review L5: the account A was referred by F, and F by the invite row: re-pointing F to A would make A and F each
     * other's referrer. F gets no referrer instead; A keeps F; the invite row's other friends name A.
     */
    public function test_a_friend_who_referred_the_account_gets_no_referrer_instead_of_a_cycle(): void
    {
        $invite = $this->invite();
        $account = $this->account();
        $friend = $this->makeCustomer(0);
        $friend->forceFill(['referred_by' => $invite->id])->save();
        $account->forceFill(['referred_by' => $friend->id])->save();
        $other = $this->makeCustomer(0);
        $other->forceFill(['referred_by' => $invite->id])->save();

        $invite->fresh()->mergeInviteInto($account, ['role' => UserRole::MANAGER]);

        $this->assertNull($friend->fresh()->referred_by, 'never a cycle of two');
        $this->assertSame($friend->id, (int) $account->fresh()->referred_by);
        $this->assertSame($account->id, (int) $other->fresh()->referred_by);
        $this->assertNull(User::find($invite->id));
    }

    /**
     * Note: an invite row that has an order (orders.user_id is ON DELETE CASCADE) is never deleted by the merge: it is
     * retired (no store, role, invite token or session), the order stays.
     */
    public function test_an_invite_row_with_an_order_is_retired_not_deleted(): void
    {
        $invite = $this->invite();
        $account = $this->account();
        $order = $this->makeOrder(\App\Enums\OrderStatus::DELIVERED, ['user_id' => $invite->id]);

        $invite->fresh()->mergeInviteInto($account, ['role' => UserRole::MANAGER]);

        $row = User::find($invite->id);
        $this->assertNotNull($row, 'kept: its order would go with it');
        $this->assertSame([UserRole::CUSTOMER, null, null], [$row->role, $row->store_id, $row->telegram_invite_token]);
        $this->assertNotNull(\App\Models\Order::find($order->id));
        $this->assertSame(UserRole::MANAGER, $account->fresh()->role);
    }
}
