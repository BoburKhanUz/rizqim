<?php

namespace Tests\Feature\PosFastFood;

use App\Domain\Pos\Setup\FormatProfile;
use App\Domain\Pos\Setup\MenuVersion;
use App\Models\AuditLog;
use App\Models\ModifierGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\PosRestaurant\PosRestaurantFixtures;
use Tests\TestCase;

/**
 * P6-32 (B2): modifier group display hints (`display_mode`: dialog, inline, size) and the preselected option
 * (`is_default`): the owner API round trip, the rules between them, the quick_modifiers module gate and the POS catalog
 * payload (hints only while the branch has the module; the customer catalog and the order rules are unchanged).
 */
class QuickModifiersTest extends TestCase
{
    use PosRestaurantFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRestaurant(FormatProfile::QAHVAXONA);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function body(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Hajm',
            'max_select' => 1,
            'is_required' => true,
            'display_mode' => 'size',
            'modifiers' => [
                ['name' => 'Kichik', 'price_delta' => '0', 'is_default' => false],
                ['name' => "O'rta", 'price_delta' => '5000', 'is_default' => true],
                ['name' => 'Katta', 'price_delta' => '9000'],
            ],
        ], $overrides);
    }

    private function save(array $body, ?int $id = null): \Illuminate\Testing\TestResponse
    {
        return $id === null
            ? $this->pos($this->owner, 'POST', '/owner/modifier-groups', $body)
            : $this->pos($this->owner, 'PUT', "/owner/modifier-groups/{$id}", $body);
    }

    private function catalogGroup(int $groupId): array
    {
        $products = $this->pos($this->cashier, 'GET', '/pos/catalog')->assertOk()->json('data.products');
        foreach ($products as $product) {
            foreach ($product['modifier_groups'] as $group) {
                if ($group['id'] === $groupId) {
                    return $group;
                }
            }
        }
        $this->fail('The group is not in the POS catalog.');
    }

    public function test_hints_round_trip_through_the_owner_api(): void
    {
        $created = $this->save($this->body())->assertCreated();
        $this->assertSame('size', $created->json('data.display_mode'));
        $this->assertSame([false, true, false], array_column($created->json('data.modifiers'), 'is_default'));

        $list = $this->pos($this->owner, 'GET', '/owner/modifier-groups')->assertOk();
        $row = collect($list->json('data'))->firstWhere('id', $created->json('data.id'));
        $this->assertSame('size', $row['display_mode']);
        $this->assertTrue($row['modifiers'][1]['is_default']);
    }

    public function test_a_save_that_leaves_the_hints_out_keeps_them(): void
    {
        $id = (int) $this->save($this->body())->json('data.id');
        $modifiers = $this->pos($this->owner, 'GET', '/owner/modifier-groups')->json('data.0.modifiers');

        // The old shape of the form: no display_mode, no is_default
        $plain = array_map(fn (array $m) => ['id' => $m['id'], 'name' => $m['name'], 'price_delta' => $m['price_delta']], $modifiers);
        $response = $this->save(['name' => 'Hajm (yangi)', 'max_select' => 1, 'is_required' => true, 'modifiers' => $plain], $id)->assertOk();

        $this->assertSame('size', $response->json('data.display_mode'));
        $this->assertSame([false, true, false], array_column($response->json('data.modifiers'), 'is_default'));
    }

    public function test_a_new_group_without_hints_is_a_plain_dialog(): void
    {
        $response = $this->save(['name' => 'Sous', 'modifiers' => [['name' => 'Achchiq']]])->assertCreated();

        $this->assertSame('dialog', $response->json('data.display_mode'));
        $this->assertFalse($response->json('data.modifiers.0.is_default'));
    }

    public function test_a_size_selector_takes_exactly_one_option(): void
    {
        $this->save($this->body(['max_select' => 2]))->assertStatus(422)->assertJsonValidationErrors('display_mode');
        $this->save($this->body(['max_select' => null]))->assertStatus(422)->assertJsonValidationErrors('display_mode');
        $this->assertSame(0, ModifierGroup::count());
    }

    public function test_defaults_never_outnumber_the_picks_and_must_be_active(): void
    {
        $two = [
            ['name' => 'A', 'is_default' => true],
            ['name' => 'B', 'is_default' => true],
        ];
        $this->save($this->body(['display_mode' => 'inline', 'modifiers' => $two]))
            ->assertStatus(422)->assertJsonValidationErrors('modifiers');
        // Two defaults fit a group that takes two
        $this->save($this->body(['display_mode' => 'inline', 'max_select' => 2, 'modifiers' => $two]))->assertCreated();

        $this->save($this->body(['modifiers' => [['name' => 'A', 'is_default' => true, 'is_active' => false], ['name' => 'B']]]))
            ->assertStatus(422)->assertJsonValidationErrors('modifiers.0.is_default');
    }

    public function test_an_unknown_display_mode_is_rejected(): void
    {
        $this->save($this->body(['display_mode' => 'popup']))->assertStatus(422)->assertJsonValidationErrors('display_mode');
    }

    public function test_hints_need_the_quick_modifiers_module(): void
    {
        $this->profile(FormatProfile::CHOYXONA);

        $this->save($this->body())->assertStatus(403)->assertJsonPath('code', 'module_disabled');
        $this->save($this->body(['display_mode' => 'dialog', 'modifiers' => [['name' => 'A', 'is_default' => true]]]))->assertStatus(403);
        $this->assertSame(0, ModifierGroup::count());

        // A plain group is core menu: no module needed
        $plain = $this->save(['name' => 'Sous', 'modifiers' => [['name' => 'Achchiq']]])->assertCreated();

        // Going back to the plain window, or saving what is saved, never asks
        $this->profile(FormatProfile::QAHVAXONA);
        $id = (int) $this->save($this->body())->json('data.id');
        $this->profile(FormatProfile::CHOYXONA);
        $saved = $this->pos($this->owner, 'GET', '/owner/modifier-groups')->json('data');
        $row = collect($saved)->firstWhere('id', $id);
        $same = array_map(fn (array $m) => ['id' => $m['id'], 'name' => $m['name'], 'price_delta' => $m['price_delta'], 'is_default' => $m['is_default']], $row['modifiers']);
        $this->save(['name' => 'Hajm', 'max_select' => 1, 'display_mode' => 'size', 'modifiers' => $same], $id)->assertOk();
        $this->save(['name' => 'Hajm', 'max_select' => 1, 'display_mode' => 'dialog', 'modifiers' => $same], $id)->assertOk();
        $this->assertSame($plain->json('data.display_mode'), 'dialog');
    }

    public function test_the_pos_catalog_carries_the_hints_only_with_the_module(): void
    {
        $this->save($this->body())->assertCreated();
        $group = ModifierGroup::firstOrFail();
        $this->product->modifierGroups()->attach($group->id, ['sort_order' => 0]);

        $payload = $this->catalogGroup($group->id);
        $this->assertSame('size', $payload['display_mode']);
        $this->assertSame([false, true, false], array_column($payload['modifiers'], 'is_default'));

        // The branch loses the module: the old behaviour, a dialog and nothing preselected
        $this->profile(FormatProfile::CHOYXONA);
        $payload = $this->catalogGroup($group->id);
        $this->assertSame('dialog', $payload['display_mode']);
        $this->assertSame([false, false, false], array_column($payload['modifiers'], 'is_default'));

        // Back on, and a stopped default is never preselected
        $this->profile(FormatProfile::QAHVAXONA);
        $default = $group->modifiers()->where('is_default', true)->firstOrFail();
        $this->pos($this->owner, 'POST', '/owner/stop-list', ['type' => 'modifier', 'id' => $default->id, 'branch_id' => $this->branch->id])->assertSuccessful();
        $payload = $this->catalogGroup($group->id);
        $this->assertSame('size', $payload['display_mode']);
        $this->assertSame([false, false, false], array_column($payload['modifiers'], 'is_default'));
        $this->assertFalse(collect($payload['modifiers'])->firstWhere('id', $default->id)['is_available']);
    }

    public function test_a_hint_change_moves_the_menu_version_and_the_order_rules_stay(): void
    {
        $id = (int) $this->save($this->body(['display_mode' => 'dialog', 'modifiers' => [['name' => 'A'], ['name' => 'B']]]))->json('data.id');
        $before = MenuVersion::forStore((int) $this->store->id);

        $this->travel(2)->seconds();
        $this->save($this->body(['display_mode' => 'inline', 'max_select' => 1, 'modifiers' => [['name' => 'A', 'is_default' => true], ['name' => 'B']]]), $id)->assertOk();
        $this->assertNotSame($before, MenuVersion::forStore((int) $this->store->id));

        // The server never fills a default in: a required group still must be chosen at checkout
        $group = ModifierGroup::findOrFail($id);
        $group->update(['is_required' => true]);
        $this->product->modifierGroups()->attach($group->id, ['sort_order' => 0]);
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.modifiers']);
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1, 'modifiers' => [$group->modifiers()->where('is_default', true)->firstOrFail()->uuid]]])->assertCreated();
    }

    public function test_hint_changes_are_audited(): void
    {
        $id = (int) $this->save($this->body())->json('data.id');
        $log = AuditLog::where('action', 'menu.modifier_hints_changed')->firstOrFail();
        $this->assertSame('size', $log->after['display_mode']);
        $this->assertSame(["O'rta"], $log->after['defaults']);

        // A save that changes no hint adds no entry
        $modifiers = $this->pos($this->owner, 'GET', '/owner/modifier-groups')->json('data.0.modifiers');
        $same = array_map(fn (array $m) => ['id' => $m['id'], 'name' => $m['name'], 'price_delta' => $m['price_delta']], $modifiers);
        $this->save(['name' => 'Hajm 2', 'max_select' => 1, 'modifiers' => $same], $id)->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'menu.modifier_hints_changed')->count());

        $this->save($this->body(['display_mode' => 'inline']), $id)->assertOk();
        $latest = AuditLog::where('action', 'menu.modifier_hints_changed')->orderByDesc('id')->firstOrFail();
        $this->assertSame('size', $latest->before['display_mode']);
        $this->assertSame('inline', $latest->after['display_mode']);
    }
}
