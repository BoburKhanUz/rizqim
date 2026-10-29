<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cast attributes, declared because Larastan does not read casts from the casts() method:
 *
 * @property array<array-key, mixed>|null $attributes_json
 * @property \App\Enums\MenuUnit $unit
 * @property numeric-string|null $quantity_step
 * @property numeric-string|null $min_quantity
 * @property numeric-string $batch_consumption
 * @property int|float $stock a decimal column (sold by weight), read as an int when whole (Quantity::normalize)
 * @property string|null $unit_label P3-11: the receipt's unit ("kg (guruch)"); null: the unit's own label
 */
class ProductVariant extends Model
{
    use SoftDeletes;
    use \App\Traits\HasPublicUuid;
    
    protected $guarded = ['id'];

    /** Column defaults, so a variant just created without them reads the same as one loaded back (P1-21). */
    protected $attributes = [
        'unit' => 'piece',
        'batch_consumption' => '1.000',
    ];
    
    protected function casts(): array
    {
        return [
            'attributes_json' => 'array',
            // P1-21: sold by the piece, kg or litre; price is per unit (docs/MENU.md)
            'unit' => \App\Enums\MenuUnit::class,
            'quantity_step' => 'decimal:3',
            'min_quantity' => 'decimal:3',
            'batch_consumption' => 'decimal:3',
        ];
    }

    /**
     * The stock column is a decimal (0.5 kg can be sold from it); whole amounts read back as an int, as before.
     */
    protected function stock(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => \App\Support\Quantity::normalize($value) ?? 0,
            set: fn ($value) => $value === null ? 0 : \App\Support\Quantity::of($value)->toString(),
        );
    }

    /**
     * The step a measured quantity moves in (0.1 kg unless set); null for pieces, which are whole.
     */
    public function quantityStep(): ?\App\Support\Quantity
    {
        if (!$this->unit->isMeasured()) {
            return null;
        }

        $step = \App\Support\Quantity::of($this->quantity_step ?? '0.1');

        return $step->isPositive() ? $step : \App\Support\Quantity::of('0.1');
    }

    /**
     * The least one order line may take: min_quantity for a measured unit (else one step), 1 for pieces.
     */
    public function minimumQuantity(): \App\Support\Quantity
    {
        if (!$this->unit->isMeasured()) {
            return \App\Support\Quantity::of(1);
        }

        $minimum = \App\Support\Quantity::of($this->min_quantity ?? 0);

        return $minimum->isPositive() ? $minimum : $this->quantityStep();
    }
    
    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<ChannelPrice, $this>
     */
    public function channelPrices(): HasMany
    {
        return $this->hasMany(ChannelPrice::class, 'product_variant_id');
    }
}
