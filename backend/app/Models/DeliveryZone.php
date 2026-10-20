<?php

namespace App\Models;

use App\Domain\Delivery\Geometry;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * A delivery zone of a store: a GeoJSON polygon (jsonb) with the price, minimum order and free-delivery threshold that
 * apply inside it. The bounding box and area columns are derived from the polygon on every save.
 *
 * Cast attributes, declared because Larastan does not read casts from the casts() method:
 *
 * @property array{type: string, coordinates: list<mixed>} $polygon
 * @property numeric-string $min_lat
 * @property numeric-string $max_lat
 * @property numeric-string $min_lng
 * @property numeric-string $max_lng
 * @property numeric-string $area_km2
 * @property numeric-string $delivery_price
 * @property numeric-string $min_order_amount
 * @property numeric-string|null $free_delivery_from
 * @property int|null $eta_minutes
 * @property int $priority
 * @property bool $active
 */
class DeliveryZone extends Model
{
    protected $guarded = ['id', 'min_lat', 'max_lat', 'min_lng', 'max_lng', 'area_km2'];

    protected function casts(): array
    {
        return [
            'polygon' => 'array',
            'min_lat' => 'decimal:6',
            'max_lat' => 'decimal:6',
            'min_lng' => 'decimal:6',
            'max_lng' => 'decimal:6',
            'area_km2' => 'decimal:4',
            'delivery_price' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'free_delivery_from' => 'decimal:2',
            'eta_minutes' => 'integer',
            'priority' => 'integer',
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (DeliveryZone $zone) {
            if ($zone->branch_id !== null && !Branch::whereKey($zone->branch_id)->where('store_id', $zone->store_id)->exists()) {
                throw new InvalidArgumentException("Filial boshqa do'konga tegishli.");
            }

            if ($zone->isDirty('polygon') || !$zone->exists || !array_key_exists('min_lat', $zone->getAttributes())) {
                // Cleans the geometry too, so what is stored is always what ZoneResolver reads
                $geometry = Geometry::normalize($zone->polygon);
                $box = Geometry::boundingBox($geometry);

                $zone->polygon = $geometry;
                $zone->forceFill($box + ['area_km2' => round(Geometry::areaKm2($geometry), 4)]);
            }
        });
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function deliveryPrice(): Money
    {
        return Money::of($this->delivery_price);
    }

    public function minOrderAmount(): Money
    {
        return Money::of($this->min_order_amount);
    }

    public function freeDeliveryFrom(): ?Money
    {
        return $this->free_delivery_from === null ? null : Money::of($this->free_delivery_from);
    }

    /**
     * Whether an order of this subtotal rides free in this zone.
     */
    public function isFreeFor(Money $subtotal): bool
    {
        $threshold = $this->freeDeliveryFrom();

        return $threshold !== null && $subtotal->isGreaterThanOrEqualTo($threshold);
    }

    public function contains(float $lat, float $lng): bool
    {
        return Geometry::contains($this->polygon, $lat, $lng);
    }
}
