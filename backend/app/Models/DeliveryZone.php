<?php

namespace App\Models;

use App\Domain\Delivery\Geometry;
use App\Services\Audit\AuditLogger;
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
    // P2-12: the zone form's limits, shared by the panel editor and the API (DeliveryZoneController)
    public const NAME_MAX = 120;
    public const AMOUNT_MAX = 99999999;
    public const PRIORITY_MIN = -1000;
    public const PRIORITY_MAX = 1000;
    public const ETA_MIN = 1;
    public const ETA_MAX = 600;

    /** P2-12: the fields a zone change is audited with (the polygon itself is too big: `polygon_changed` instead). */
    public const AUDITED = ['name', 'branch_id', 'priority', 'active', 'delivery_price', 'min_order_amount', 'free_delivery_from', 'eta_minutes'];

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

        // P2-12: who changed a zone's price or shape and when, from the panel and the API alike (docs/ZONES.md)
        static::created(fn (DeliveryZone $zone) => app(AuditLogger::class)->record('delivery_zone.created', $zone, [], $zone->only(self::AUDITED)));
        static::updated(function (DeliveryZone $zone) {
            $changed = array_intersect(array_keys($zone->getChanges()), self::AUDITED);
            $polygonChanged = $zone->wasChanged('polygon');
            if ($changed === [] && !$polygonChanged) {
                return;
            }
            $before = array_intersect_key($zone->getOriginal(), array_flip($changed));
            app(AuditLogger::class)->record('delivery_zone.updated', $zone, $before, $zone->only($changed), $polygonChanged ? ['polygon_changed' => true] : []);
        });
        static::deleted(fn (DeliveryZone $zone) => app(AuditLogger::class)->record('delivery_zone.deleted', $zone, $zone->only(self::AUDITED), [], [], ['store_id' => (int) $zone->store_id]));
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
