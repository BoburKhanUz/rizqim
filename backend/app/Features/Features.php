<?php

namespace App\Features;

use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Laravel\Pennant\Feature;

/**
 * Single place where feature flags are declared (Laravel Pennant, database driver).
 *
 * Flags are scoped to a Store: a feature can be switched on for one restaurant first and rolled out later. A store
 * without an explicit override gets the flag's default below. Overrides are stored in the `features` table and are
 * toggled in Superadmin > Feature flaglar. Without a store (guest, super admin) the default applies.
 *
 * To add a flag: add it to FLAGS, check it with `Feature::active('name')` (scope = the signed-in user's store) or
 * `Feature::for($store)->active('name')`. Remove it from FLAGS (and `php artisan pennant:purge name`) once rolled out.
 */
final class Features
{
    /** Copy tweak behind a flag; changes no behaviour. Used to demonstrate and test the mechanism. */
    public const NEW_CHECKOUT_COPY = 'new-checkout-copy';

    /**
     * name => label (Uzbek, shown in the panel), description and the value for stores without an override.
     *
     * @var array<string, array{label: string, description: string, default: bool}>
     */
    public const FLAGS = [
        self::NEW_CHECKOUT_COPY => [
            'label' => 'Yangi buyurtma matni',
            'description' => 'Buyurtmani rasmiylashtirish sahifasidagi yangi matnlar (namunaviy flag, xatti-harakatni o\'zgartirmaydi).',
            'default' => false,
        ],
    ];

    /**
     * Registers every flag with Pennant and resolves the default scope to the signed-in user's store.
     */
    public static function register(): void
    {
        Feature::resolveScopeUsing(fn (): ?Store => Auth::user()?->store);

        foreach (self::FLAGS as $name => $definition) {
            // The resolver runs once per (flag, store) and the result is persisted, so a changed default only
            // reaches stores that have no stored value yet; use `pennant:purge` to re-resolve.
            Feature::define($name, fn (?Store $store): bool => $definition['default']);
        }
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::FLAGS);
    }

    /**
     * Every flag's current value for one store (or the defaults when null). Safe for the API: only booleans.
     *
     * @return array<string, bool>
     */
    public static function forStore(?Store $store): array
    {
        // No store, no scope to persist a value for: skip the database
        if ($store === null) {
            return self::defaults();
        }

        $values = Feature::for($store)->values(self::names());

        return array_map(fn (mixed $value): bool => $value === true, $values);
    }

    /**
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        return array_map(fn (array $definition): bool => $definition['default'], self::FLAGS);
    }
}
