<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthReferralServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ReferralService $referrals;
    protected User $referrer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->referrals = app(ReferralService::class);
        $this->referrer = User::create(['name' => 'Referrer', 'telegram_id' => '1010101', 'role' => UserRole::CUSTOMER]);
    }

    private function newUser(string $telegramId): User
    {
        return User::create(['name' => 'New', 'telegram_id' => $telegramId, 'role' => UserRole::CUSTOMER]);
    }

    public function test_referrer_id_from_param_accepts_only_valid_referral_payloads(): void
    {
        $id = $this->referrer->id;

        foreach (["rizqim_{$id}", "ref_{$id}", "REF_{$id}", "referrer_{$id}"] as $param) {
            $this->assertSame($id, $this->referrals->referrerIdFromParam($param, '7777001'), $param);
        }

        foreach ([null, '', "store_{$id}", "invite_emp_{$id}", 'rizqim_', 'ref_1.5', 'ref_1e1', 'ref_999999'] as $param) {
            $this->assertNull($this->referrals->referrerIdFromParam($param, '7777001'), (string) $param);
        }

        // Same Telegram account as the referrer
        $this->assertNull($this->referrals->referrerIdFromParam("rizqim_{$id}", '1010101'));
    }

    public function test_pending_referral_is_stored_and_consumed_once(): void
    {
        $this->referrals->storePendingReferral('7777002', $this->referrer->id);

        $this->assertSame($this->referrer->id, Cache::get($this->referrals->pendingKey('7777002')));
        $this->assertSame($this->referrer->id, $this->referrals->pullPendingReferrerId('7777002'));
        $this->assertNull($this->referrals->pullPendingReferrerId('7777002'));
    }

    public function test_apply_pending_referral_sets_referrer_on_a_user_without_one(): void
    {
        $user = $this->newUser('7777003');
        $this->referrals->storePendingReferral('7777003', $this->referrer->id);

        $this->referrals->applyPendingReferral($user, '7777003');

        $this->assertSame($this->referrer->id, $user->fresh()->referred_by);
        $this->assertFalse(Cache::has($this->referrals->pendingKey('7777003')));
    }

    public function test_apply_pending_referral_never_overwrites_and_ignores_invalid_or_self_referrals(): void
    {
        $original = User::create(['name' => 'Original', 'telegram_id' => '1010102', 'role' => UserRole::CUSTOMER]);
        $alreadyReferred = $this->newUser('7777004');
        $alreadyReferred->referred_by = $original->id;
        $alreadyReferred->save();

        $this->referrals->storePendingReferral('7777004', $this->referrer->id);
        $this->referrals->applyPendingReferral($alreadyReferred, '7777004');
        $this->assertSame($original->id, $alreadyReferred->fresh()->referred_by);
        $this->assertFalse(Cache::has($this->referrals->pendingKey('7777004')));

        $this->referrals->storePendingReferral('1010101', $this->referrer->id);
        $this->referrals->applyPendingReferral($this->referrer, '1010101');
        $this->assertNull($this->referrer->fresh()->referred_by);

        $bogus = $this->newUser('7777005');
        Cache::put($this->referrals->pendingKey('7777005'), 'abc', now()->addDay());
        $this->referrals->applyPendingReferral($bogus, '7777005');
        $this->assertNull($bogus->fresh()->referred_by);

        $nothingParked = $this->newUser('7777006');
        $this->referrals->applyPendingReferral($nothingParked, '7777006');
        $this->assertNull($nothingParked->fresh()->referred_by);
    }
}
