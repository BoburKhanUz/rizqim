<?php

namespace App\OpenApi;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\PropertyFetchEvent;
use Dedoc\Scramble\Support\InferExtensions\ModelExtension;
use Dedoc\Scramble\Support\ResponseExtractor\ModelInfo;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Scramble's model schemas, made to match how our models really serialize:
 *
 * - every relation is listed (as an optional property, required only when it is in the model's $with), because the
 *   endpoints eager load different relations (an order's items, user, courier, branch...) and Scramble would only
 *   list $with ones; the key is snake_cased like Model::relationsToArray() does;
 * - a model that overrides toArray() to drop its INTERNAL_ATTRIBUTES (Store) is documented without them instead of
 *   as an unknown array;
 * - PostgreSQL boolean columns without a cast are booleans (PDO returns them as bool), not strings.
 *
 * Runs before Scramble's own ModelExtension (custom extensions come first) and falls back to it for anything else.
 */
class EloquentModelExtension extends ModelExtension
{
    /** @var array<string, Collection<string, mixed>> */
    private array $models = [];

    public function getPropertyType(PropertyFetchEvent $event): ?Type
    {
        $type = parent::getPropertyType($event);

        if ($type === null || !$this->isAppModel($event->getInstance()->name)) {
            return $type;
        }

        /** @var Collection<string, array<string, mixed>> $attributes */
        $attributes = $this->modelInfo($event->getInstance()->name)->get('attributes', collect());
        $attribute = $attributes->get($event->getName());

        if (is_array($attribute) && empty($attribute['cast']) && in_array($attribute['type'] ?? null, ['boolean', 'bool'], true)) {
            return ($attribute['nullable'] ?? false) ? Union::wrap([new BooleanType, new NullType]) : new BooleanType;
        }

        return $type;
    }

    protected function getToArrayMethodReturnType(MethodCallEvent $event): ?Type
    {
        $class = $event->getInstance()->name;
        $declaringClass = (new \ReflectionMethod($class, 'toArray'))->getDeclaringClass()->getName();

        // Only Model::toArray() itself, or an override that just strips INTERNAL_ATTRIBUTES (Store)
        if (!$this->isAppModel($class) || ($declaringClass !== Model::class && !defined($class . '::INTERNAL_ATTRIBUTES'))) {
            return parent::getToArrayMethodReturnType($event);
        }

        $info = $this->modelInfo($class);
        $instance = $info->get('instance');
        if (!$instance instanceof Model) {
            return parent::getToArrayMethodReturnType($event);
        }

        $visible = $instance->getVisible();
        $hidden = $instance->getHidden();
        $internal = defined($class . '::INTERNAL_ATTRIBUTES') ? (array) constant($class . '::INTERNAL_ATTRIBUTES') : [];

        /** @var Collection<string, array<string, mixed>> $attributes */
        $attributes = $info->get('attributes', collect());
        $attributeItems = $attributes
            ->when($visible, fn (Collection $c) => $c->only($visible))
            ->except([...$hidden, ...$internal])
            ->filter(fn (array $attribute) => $attribute['appended'] !== false)
            ->map(function (array $_, string $name) use ($event) {
                $type = $event->getInstance()->getPropertyType($name);

                // Dates serialize as ISO-8601 strings
                (new TypeWalker)->replace($type, function (Type $t) {
                    return ($t->isInstanceOf(Carbon::class) || $t->isInstanceOf(CarbonImmutable::class))
                        ? tap(new StringType, fn ($type) => $type->setAttribute('format', $t->getAttribute('format') ?? 'date-time'))
                        : null;
                });

                return new ArrayItemType_($name, $type);
            });

        $eagerLoaded = $this->eagerLoadedByDefault($instance);
        $items = $attributeItems->all();

        /** @var Collection<string, array<string, mixed>> $relations */
        $relations = $info->get('relations', collect());
        $relations
            ->when($visible, fn (Collection $c) => $c->only($visible))
            ->except($hidden)
            // Our own relations only, not the ones vendor traits add (Sanctum's tokens, notifications)
            ->filter(fn (array $_, string $name) => str_starts_with((string) (new \ReflectionMethod($class, $name))->getFileName(), app_path()))
            ->each(function (array $_, string $name) use ($event, $instance, $eagerLoaded, &$items) {
                $key = $instance::$snakeAttributes ? Str::snake($name) : $name;
                $type = $event->getInstance()->getPropertyType($name);

                // A relation named like a column (cancelledBy / cancelled_by) replaces the column's value once loaded
                $items[$key] = isset($items[$key])
                    ? new ArrayItemType_($key, Union::wrap([$items[$key]->value, $type]))
                    : new ArrayItemType_($key, $type, isOptional: !in_array($name, $eagerLoaded, true));
            });

        return new KeyedArrayType(array_values($items));
    }

    private function isAppModel(string $class): bool
    {
        return str_starts_with($class, 'App\\Models\\') && is_a($class, Model::class, true);
    }

    /**
     * @return Collection<string, mixed>
     */
    private function modelInfo(string $class): Collection
    {
        return $this->models[$class] ??= (new ModelInfo($class))->handle();
    }

    /**
     * @return list<string>
     */
    private function eagerLoadedByDefault(Model $model): array
    {
        $with = (fn () => $this->with)->call($model);

        return array_values(array_filter((array) $with, 'is_string'));
    }
}
