<?php

namespace Tests\Feature\PosTips;

use App\Models\PosTipSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-22: the branch's tip settings (owner decision 29.10.2026: personal or pool, the split rule, the roles and their
 * weights) — the defaults, changes audited, the `tips` module for changing them, validation, the bootstrap section.
 */
class TipSettingsApiTest extends TestCase
{
    use PosTipsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTips();
    }

    public function test_the_defaults_a_change_and_the_bootstrap(): void
    {
        $this->owner('GET', "/owner/pos/tips/settings?branch_id={$this->branch->id}")->assertOk()
            ->assertJsonPath('data.settings', [
                'branch_id' => $this->branch->id, 'enabled' => true, 'mode' => 'personal', 'pool_rule' => 'equal', 'pool_period' => 'day',
                'roles' => ['waiter'], 'role_weights' => ['manager' => '1.00', 'cashier' => '1.00', 'waiter' => '1.00', 'cook' => '1.00', 'hostess' => '1.00'], 'updated_at' => null,
            ])
            ->assertJsonPath('data.options.pool_rules.1', ['key' => 'hours', 'label' => 'Ishlagan soatiga qarab'])
            ->assertJsonPath('data.options.roles.2', ['key' => 'waiter', 'label' => 'Ofitsiant']);

        $this->owner('PUT', '/owner/pos/tips/settings', ['branch_id' => $this->branch->id, 'mode' => 'pool', 'pool_rule' => 'role_weights', 'roles' => ['waiter', 'cook'], 'role_weights' => ['cook' => '0.5']])
            ->assertOk()
            ->assertJsonPath('data.settings.mode', 'pool')
            ->assertJsonPath('data.settings.roles', ['waiter', 'cook'])
            ->assertJsonPath('data.settings.role_weights.cook', '0.50');
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.tips_changed']);

        // Only what is sent changes
        $this->owner('PUT', '/owner/pos/tips/settings', ['branch_id' => $this->branch->id, 'pool_period' => 'shift'])->assertOk()
            ->assertJsonPath('data.settings.mode', 'pool')->assertJsonPath('data.settings.pool_period', 'shift');
        $this->assertSame(1, PosTipSetting::count());

        $this->pos('GET', '/bootstrap')->assertOk()->assertJsonPath('data.tips', ['enabled' => true, 'mode' => 'pool', 'pool_rule' => 'role_weights', 'pool_period' => 'shift']);
    }

    public function test_validation_the_module_and_who_may(): void
    {
        $put = fn (array $data, $user = null) => $this->owner('PUT', '/owner/pos/tips/settings', ['branch_id' => $this->branch->id] + $data, $user);

        $put(['mode' => 'shared'])->assertStatus(422)->assertJsonValidationErrors('mode');
        $put(['roles' => ['owner']])->assertStatus(422)->assertJsonValidationErrors('roles.0');
        $put(['roles' => []])->assertStatus(422)->assertJsonValidationErrors('roles');
        $put(['role_weights' => ['chef' => '2']])->assertStatus(422)->assertJsonValidationErrors('role_weights');
        $put(['role_weights' => ['cook' => '0']])->assertStatus(422)->assertJsonValidationErrors('role_weights.cook');

        $put(['mode' => 'pool'], $this->cashier)->assertForbidden();
        $this->owner('GET', "/owner/pos/tips?branch_id={$this->branch->id}", [], $this->waiter)->assertForbidden();

        // A choyxona has no tips module: reading stays, changing is refused
        $this->profile($this->branch, 'choyxona');
        $put(['mode' => 'pool'])->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
        $this->owner('GET', "/owner/pos/tips/settings?branch_id={$this->branch->id}")->assertOk()->assertJsonPath('data.settings.enabled', false);
        $this->owner('GET', "/owner/pos/tips?branch_id={$this->branch->id}")->assertOk()->assertJsonPath('data.totals.amount', '0.00');
        $this->owner('GET', "/owner/pos/tips?branch_id={$this->branch->id}&from=2026-01-01&to=2026-06-01")->assertStatus(422)->assertJsonValidationErrors('to');
    }
}
