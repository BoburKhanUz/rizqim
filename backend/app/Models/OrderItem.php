<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An order line as it was sold (P1-21 snapshot): names, unit price, unit, quantity, chosen modifiers and totals
 * never follow later menu edits.
 *
 * @property int|float $quantity a decimal column (1.5 kg), read as an int when whole
 * @property list<array<string, mixed>>|null $modifiers
 * @property numeric-string $modifiers_total
 */
class OrderItem extends Model
{
    use SoftDeletes;
    
    protected $guarded = ['id'];

    protected $casts = [
        'unit' => \App\Enums\MenuUnit::class,
        'modifiers' => 'array',
        'modifiers_total' => 'decimal:2',
    ];

    protected $attributes = [
        'unit' => 'piece',
        'modifiers_total' => '0.00',
    ];

    /**
     * Decimal in the database (0.5 kg); 2.000 still reads (and serializes) as 2.
     */
    protected function quantity(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => \App\Support\Quantity::normalize($value) ?? 0,
            set: fn ($value) => \App\Support\Quantity::of($value)->toString(),
        );
    }

    /**
     * "2", "1.5 kg": the quantity as staff and receipts show it.
     */
    public function quantityLabel(): string
    {
        $quantity = \App\Support\Quantity::of($this->getRawOriginal('quantity') ?? $this->attributes['quantity'] ?? 0)->format();
        $unit = $this->unit ?? \App\Enums\MenuUnit::PIECE;

        return $unit->isMeasured() ? "{$quantity} {$unit->label()}" : $quantity;
    }

    /**
     * "2x Osh (0.7 porsiya) (Qazi)" or "1.5 kg Somsa (Mini)": one line of a Telegram card, receipt or report.
     */
    public function displayLine(): string
    {
        $variant = $this->variant_name ? " ({$this->variant_name})" : '';
        $unit = $this->unit ?? \App\Enums\MenuUnit::PIECE;
        $quantity = $unit->isMeasured() ? $this->quantityLabel() . ' ' : $this->quantityLabel() . 'x ';

        return $quantity . $this->product_name . $variant . $this->modifiersLabel();
    }

    /**
     * " (Pishloq, Achchiq sous x2)": the chosen modifiers for one-line texts (Telegram cards, reports); '' without.
     */
    public function modifiersLabel(): string
    {
        $names = [];
        foreach ($this->modifiers ?? [] as $modifier) {
            $name = (string) ($modifier['name'] ?? '');
            $quantity = (int) ($modifier['quantity'] ?? 1);
            if ($name !== '') {
                $names[] = $quantity > 1 ? "{$name} x{$quantity}" : $name;
            }
        }

        return $names ? ' (' . implode(', ', $names) . ')' : '';
    }
    
    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
    
    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->productVariant();
    }

    /**
     * @return BelongsTo<MenuBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(MenuBatch::class);
    }
}
