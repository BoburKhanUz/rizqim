<?php

namespace Tests\Feature;

use App\Filament\Resources\ModifierGroups\ModifierGroupResource;
use App\Filament\Resources\ModifierGroups\Pages\CreateModifierGroup;
use App\Filament\Resources\ModifierGroups\Pages\ListModifierGroups;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\ModifierGroup;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P1-21 store panel: modifier groups (menu.manage to edit, menu.view to read, own store only) and the product form's
 * unit, modifier groups and channel prices.
 */
class MenuPanelTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use MenuFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    protected function inPanel(\App\Models\User $user): void
    {
        $this->actingAs($user, 'web');
        Filament::setCurrentPanel('admin');
        Filament::setTenant($this->store);
    }

    public function test_the_owner_creates_a_modifier_group_with_its_modifiers(): void
    {
        $this->inPanel($this->owner);

        Livewire::test(CreateModifierGroup::class)
            ->fillForm([
                'name' => 'Sous',
                'min_select' => 1,
                'max_select' => 2,
                'is_required' => true,
                'modifiers' => [
                    ['name' => 'Ketchup', 'price_delta' => 0, 'max_quantity' => 1, 'is_active' => true],
                    ['name' => 'Pishloqli', 'price_delta' => 3000, 'max_quantity' => 1, 'is_active' => true],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $group = ModifierGroup::where('name', 'Sous')->firstOrFail();
        $this->assertSame($this->store->id, (int) $group->store_id);
        $this->assertTrue($group->is_required);
        $this->assertSame(['Ketchup', 'Pishloqli'], $group->modifiers->pluck('name')->all());
        $this->assertSame('3000.00', $group->modifiers[1]->price_delta);

        $this->panelAs($this->owner, 'modifier-groups')->assertOk()->assertSee('Sous');
    }

    public function test_a_manager_reads_groups_and_another_store_sees_none(): void
    {
        $group = $this->makeGroup('Sous', ['Ketchup' => 0]);
        $otherStore = $this->makeStore('Other Burger');
        $foreign = ModifierGroup::create(['store_id' => $otherStore->id, 'name' => 'Begona guruh']);

        $this->panelAs($this->manager, 'modifier-groups')->assertOk()->assertSee('Sous')->assertDontSee('Begona guruh');
        $this->panelAs($this->manager, 'modifier-groups/create')->assertForbidden();
        $this->panelAs($this->manager, "modifier-groups/{$group->id}/edit")->assertForbidden();
        $this->panelAs($this->owner, "modifier-groups/{$foreign->id}/edit")->assertNotFound();

        $this->inPanel($this->manager);
        Livewire::test(ListModifierGroups::class)
            ->assertCanSeeTableRecords([$group])
            ->assertCanNotSeeTableRecords([$foreign])
            ->assertActionHidden('create');
        $this->assertFalse(ModifierGroupResource::canCreate());
    }

    public function test_the_product_form_sets_units_modifier_groups_and_channel_prices(): void
    {
        $group = $this->makeGroup('Sous', ['Ketchup' => 0], [], null);
        $this->panelAs($this->owner, "products/{$this->product->getRouteKey()}/edit")->assertOk()
            ->assertSee('Modifikatorlar')->assertSee('Kanal narxlari');

        $this->inPanel($this->owner);
        $component = Livewire::test(EditProduct::class, ['record' => $this->product->getRouteKey()]);
        $variants = $component->get('data.variants');
        $key = array_key_first($variants);
        $variants[$key]['unit'] = 'kg';
        $variants[$key]['quantity_step'] = '0.5';
        $variants[$key]['min_quantity'] = '1';

        $component
            ->set('data.variants', $variants)
            ->set('data.modifierGroupLinks', ['new-1' => ['modifier_group_id' => $group->id, 'min_select' => null, 'max_select' => 1, 'is_required' => 1]])
            ->set('data.channelPrices', ['new-2' => ['channel' => 'telegram', 'product_variant_id' => $this->variant->id, 'price' => 45000, 'is_hidden' => false]])
            ->set('data.batch_tracked', true)
            ->call('save')
            ->assertHasNoFormErrors();

        $variant = $this->variant->fresh();
        $this->assertSame('kg', $variant->unit->value);
        $this->assertSame('0.500', $variant->quantity_step);
        $this->assertTrue($this->product->fresh()->batch_tracked);
        $this->assertDatabaseHas('product_modifier_groups', ['product_id' => $this->product->id, 'modifier_group_id' => $group->id, 'max_select' => 1, 'is_required' => true]);
        $this->assertDatabaseHas('channel_prices', ['store_id' => $this->store->id, 'product_variant_id' => $this->variant->id, 'channel' => 'telegram', 'price' => '45000.00']);
    }
}
