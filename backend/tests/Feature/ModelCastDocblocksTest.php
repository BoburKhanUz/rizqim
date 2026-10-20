<?php

namespace Tests\Feature;

use App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

/**
 * Larastan does not read casts declared in a casts() method, so those models spell their cast attributes out as
 * @property tags (what PHPStan checks the code against). Every such cast needs a tag whose type matches the cast and
 * the column's nullability, or the analysis silently falls back to the raw column type.
 */
class ModelCastDocblocksTest extends TestCase
{
    use RefreshDatabase;

    private const MODELS = [
        Models\Branch::class,
        Models\Order::class,
        Models\OrderStatusHistory::class,
        Models\Payment::class,
        Models\PaymentProvider::class,
        Models\ProductVariant::class,
        Models\Store::class,
        Models\User::class,
        // P1-23
        Models\StoreSubscription::class,
        Models\SubscriptionInvoice::class,
        Models\TariffPlan::class,
    ];

    public function test_every_cast_from_a_casts_method_has_a_matching_property_tag(): void
    {
        foreach (self::MODELS as $class) {
            $model = new $class();
            $tags = $this->propertyTags($class);
            $columns = collect(Schema::getColumns($model->getTable()))->keyBy('name');

            foreach ($this->castsMethod($model) as $attribute => $cast) {
                if ($cast === 'hashed') {
                    continue; // a string, as the column already says
                }

                $this->assertArrayHasKey($attribute, $tags, "{$class}: no @property tag for the '{$attribute}' cast");
                $type = $tags[$attribute];
                $types = array_map(fn (string $part) => preg_replace('/<.*>$/', '', $part), explode('|', $type));

                $this->assertContains($this->expectedType($cast), $types, "{$class}::\${$attribute} is cast as {$cast} but documented as {$type}");
                $this->assertSame(
                    (bool) $columns[$attribute]['nullable'],
                    in_array('null', $types, true),
                    "{$class}::\${$attribute}: '|null' in {$type} must match the column's nullability"
                );
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function castsMethod(Model $model): array
    {
        $method = new \ReflectionMethod($model, 'casts');

        return $method->invoke($model);
    }

    /**
     * @return array<string, string> attribute => type, from @property / @property-read tags
     */
    private function propertyTags(string $class): array
    {
        preg_match_all('/@property(?:-read)?\s+(.+?)\s+\$(\w+)/', (string) (new ReflectionClass($class))->getDocComment(), $matches, PREG_SET_ORDER);

        return collect($matches)->mapWithKeys(fn (array $match) => [$match[2] => ltrim($match[1], '\\')])->all();
    }

    private function expectedType(string $cast): string
    {
        return match (true) {
            enum_exists($cast) => $cast,
            str_starts_with($cast, 'decimal') => 'numeric-string',
            in_array($cast, ['array', 'encrypted:array', 'json'], true) => 'array',
            in_array($cast, ['datetime', 'date'], true) => 'Illuminate\Support\Carbon',
            in_array($cast, ['float', 'double', 'real'], true) => 'float',
            in_array($cast, ['bool', 'boolean'], true) => 'bool',
            in_array($cast, ['int', 'integer'], true) => 'int',
            default => $cast,
        };
    }
}
