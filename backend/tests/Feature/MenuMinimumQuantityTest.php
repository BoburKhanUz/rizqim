<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Support\QuantityRules;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The least quantity of a weighed variant must be a multiple of its step: a line is accepted only when it is at least
 * the minimum AND a multiple of the step (OrderLineBuilder::checkQuantity), so 0.3 kg in steps of 0.5 would leave the
 * customer nothing but 0.5, 1, ... — refused at the forms, with a clear Uzbek reason.
 */
class MenuMinimumQuantityTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use MenuFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    private function createProduct(array $variant)
    {
        return $this->actingAs($this->owner, 'sanctum')->post('/api/v1/owner/products', [
            'name' => 'Qazi',
            'category_id' => $this->product->category_id,
            'is_active' => true,
            'variants' => json_encode([['name' => 'kg', 'price' => 120000] + $variant]),
        ], ['Accept' => 'application/json']);
    }

    public function test_the_rule(): void
    {
        $this->assertNull(QuantityRules::minimumProblem('1', '0.5'));
        $this->assertNull(QuantityRules::minimumProblem('0.5', '0.500'));
        $this->assertNull(QuantityRules::minimumProblem('0.3', null)); // the default step 0.1
        $this->assertNull(QuantityRules::minimumProblem(null, '0.5'));
        $this->assertNull(QuantityRules::minimumProblem('', '0.5'));
        $this->assertNull(QuantityRules::minimumProblem('abc', '0.5')); // the numeric rule reports that

        $this->assertSame(
            "Eng kam miqdor (0.3) qadamga (0.5) karrali bo'lishi kerak, aks holda bu taomni buyurtma qilib bo'lmaydi. Masalan: 0.5.",
            QuantityRules::minimumProblem('0.3', '0.5'),
        );
        $this->assertStringContainsString('Masalan: 0.75.', (string) QuantityRules::minimumProblem(0.6, 0.25));
        $this->assertNotNull(QuantityRules::minimumProblem('0.25', null)); // not a multiple of 0.1
    }

    public function test_the_owner_api_refuses_a_minimum_that_is_not_a_multiple_of_the_step(): void
    {
        $this->createProduct(['unit' => 'kg', 'quantity_step' => 0.5, 'min_quantity' => 0.3])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['variants.0.min_quantity' => "qadamga (0.5) karrali bo'lishi kerak"]);

        $this->createProduct(['unit' => 'kg', 'quantity_step' => 0.5, 'min_quantity' => 1])->assertOk();
        // A piece has no step or minimum: whatever was sent is dropped, nothing to refuse
        $this->createProduct(['unit' => 'piece', 'quantity_step' => 0.5, 'min_quantity' => 0.3])->assertOk();
    }

    public function test_an_update_is_checked_against_the_saved_step_when_it_sends_only_the_minimum(): void
    {
        $variant = $this->makeVariant($this->product, '120000.00', ['name' => 'kg', 'unit' => 'kg', 'quantity_step' => '0.5', 'min_quantity' => '0.5']);
        $update = fn (array $row) => $this->actingAs($this->owner, 'sanctum')->post("/api/v1/owner/products/{$this->product->id}", [
            'name' => $this->product->name,
            'category_id' => $this->product->category_id,
            'variants' => json_encode([
                ['id' => $this->variant->id, 'name' => $this->variant->name, 'price' => $this->variant->price],
                ['id' => $variant->id, 'name' => 'kg', 'price' => 120000] + $row,
            ]),
        ], ['Accept' => 'application/json']);

        $update(['min_quantity' => 0.3])->assertStatus(422)->assertJsonValidationErrors(['variants.1.min_quantity']);
        $this->assertSame('0.500', $variant->fresh()->min_quantity);

        $update(['min_quantity' => 1.5])->assertOk();
        $this->assertSame('1.500', $variant->fresh()->min_quantity);
    }

    public function test_the_store_panel_refuses_it_too(): void
    {
        $this->actingAs($this->owner, 'web');
        Filament::setCurrentPanel('admin');
        Filament::setTenant($this->store);

        $component = Livewire::test(EditProduct::class, ['record' => $this->product->getRouteKey()]);
        $variants = $component->get('data.variants');
        $key = array_key_first($variants);
        $variants[$key]['unit'] = 'kg';
        $variants[$key]['quantity_step'] = '0.5';
        $variants[$key]['min_quantity'] = '0.3';

        $component->set('data.variants', $variants)->call('save')
            ->assertHasFormErrors(["variants.{$key}.min_quantity"]);
        $this->assertSame('piece', $this->variant->fresh()->unit->value);

        $variants[$key]['min_quantity'] = '1';
        $component->set('data.variants', $variants)->call('save')->assertHasNoFormErrors();
        $this->assertSame('1.000', $this->variant->fresh()->min_quantity);
    }
}
