<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * P1-21 menu helpers on top of OrderLifecycleFixtures: weighed variants, modifier groups and a checkout request.
 */
trait MenuFixtures
{
    protected function makeVariant(Product $product, string $price, array $attributes = []): ProductVariant
    {
        return ProductVariant::create(array_merge([
            'product_id' => $product->id,
            'name' => 'V ' . Str::random(4),
            'price' => $price,
            'stock' => 100,
            'is_active' => true,
        ], $attributes));
    }

    protected function makeProduct(string $name, array $attributes = []): Product
    {
        return Product::create(array_merge([
            'store_id' => $this->store->id,
            'category_id' => $this->product->category_id,
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(5),
            'status' => 'active',
        ], $attributes));
    }

    /**
     * A group with modifiers [name => price delta], attached to the product (with optional per-product overrides).
     *
     * @param  array<string, string|int>  $modifiers
     * @param  array<string, mixed>  $group
     * @param  array<string, mixed>|null  $pivot  null: not attached
     */
    protected function makeGroup(string $name, array $modifiers, array $group = [], ?array $pivot = [], ?Product $product = null): ModifierGroup
    {
        $model = ModifierGroup::create(array_merge(['store_id' => $this->store->id, 'name' => $name], $group));

        $position = 0;
        foreach ($modifiers as $modifierName => $delta) {
            $model->modifiers()->create(['name' => $modifierName, 'price_delta' => $delta, 'sort_order' => $position++]);
        }

        if ($pivot !== null) {
            ($product ?? $this->product)->modifierGroups()->attach($model->id, $pivot);
        }

        return $model->load('modifiers');
    }

    protected function modifier(ModifierGroup $group, string $name): Modifier
    {
        return $group->modifiers->firstWhere('name', $name) ?? $group->modifiers()->where('name', $name)->firstOrFail();
    }

    /**
     * POST /orders as a pickup at the main branch, cash.
     *
     * @param  list<array<string, mixed>>  $items
     */
    protected function order(array $items, array $overrides = [], ?User $user = null, array $headers = []): TestResponse
    {
        return $this->actingAs($user ?? $this->customer, 'sanctum')->withHeaders($headers)->postJson('/api/v1/orders', array_merge([
            'store_id' => $this->store->id,
            'type' => 'pickup',
            'branch_id' => $this->branch->id,
            'payment_method' => 'cash',
            'items' => $items,
        ], $overrides));
    }

    /**
     * GET a store panel page as $user, signed in afresh (AuthenticateSession logs out a session's other user).
     */
    protected function panelAs(User $user, string $path): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        return $this->actingAs($user, 'web')->get("/admin/{$this->store->uuid}/{$path}");
    }

    protected function makeCook(?\App\Models\Branch $branch = null): User
    {
        return $this->makeStaff(UserRole::COOK, $branch ?? $this->branch);
    }
}
