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
 * @property list<array<string, mixed>>|null $set_parts P6-15: the parts of a "kompleks" line as sold (MealSets::partsFor)
 * @property numeric-string $modifiers_total
 * @property \App\Enums\KitchenItemStatus $kitchen_status P2-11: the kitchen display's per line "ready"
 * @property \Illuminate\Support\Carbon|null $ready_at
 *
 * P3-12: POS lines (rounds, firing, void)
 * @property int|null $round_no
 * @property \App\Enums\FireMode|null $fire_mode
 * @property int|null $station_id
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $due_at
 * @property int|null $added_by
 * @property \Illuminate\Support\Carbon|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 * @property int|null $seat_no
 * @property string|null $note
 *
 * P6-21 / P6-25: courses and "weight later"
 * @property int|null $course_no
 * @property \App\Domain\Pos\Orders\WeightStatus|null $weight_status
 * @property \App\Domain\Pos\Orders\WeightBasis|null $weight_basis
 * @property int|null $weighed_by
 * @property \Illuminate\Support\Carbon|null $weighed_at
 *
 * P6-31: a combo line keeps its parts as sold (Combos::resolve) and has one zero-priced kitchen line per part
 * @property list<array<string, mixed>>|null $combo_parts
 * @property int|null $combo_parent_id
 */
class OrderItem extends Model
{
    use SoftDeletes;
    
    protected $guarded = ['id'];

    protected $casts = [
        'unit' => \App\Enums\MenuUnit::class,
        'modifiers' => 'array',
        'set_parts' => 'array',
        'modifiers_total' => 'decimal:2',
        // P2-11: kitchen display bump (docs/API.md)
        'kitchen_status' => \App\Enums\KitchenItemStatus::class,
        'ready_at' => 'datetime',
        // P3-12: POS lines (App\Domain\Pos\Orders)
        'round_no' => 'integer',
        'fire_mode' => \App\Enums\FireMode::class,
        'station_id' => 'integer',
        'sent_at' => 'datetime',
        'due_at' => 'datetime',
        'added_by' => 'integer',
        'voided_at' => 'datetime',
        'voided_by' => 'integer',
        'seat_no' => 'integer',
        // P6-21 / P6-25
        'course_no' => 'integer',
        'weight_status' => \App\Domain\Pos\Orders\WeightStatus::class,
        'weight_basis' => \App\Domain\Pos\Orders\WeightBasis::class,
        'weighed_by' => 'integer',
        'weighed_at' => 'datetime',
        // P6-31
        'combo_parts' => 'array',
        'combo_parent_id' => 'integer',
    ];

    protected $attributes = [
        'unit' => 'piece',
        'modifiers_total' => '0.00',
        'kitchen_status' => 'pending',
    ];

    /** P6-31: the global scope that hides the kitchen lines of combo parts (App\Domain\Pos\Menu\ComboKitchen). */
    public const COMBO_PARTS_SCOPE = 'combo_parts';

    /**
     * P6-31: a combo's part lines exist for the kitchen only (their station, bump and timing); every other query, the
     * order's lines, bills, receipts, refunds, reports and payloads, sees the one priced combo line. The kitchen reads
     * them with withComboParts() or comboChildren(). ComboKitchen keeps them in step with their combo line.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(self::COMBO_PARTS_SCOPE, fn (\Illuminate\Database\Eloquent\Builder $query) => $query->whereNull($query->qualifyColumn('combo_parent_id')));
        static::saving(fn (self $item) => app(\App\Domain\Pos\Menu\ComboKitchen::class)->saving($item));
        static::saved(fn (self $item) => app(\App\Domain\Pos\Menu\ComboKitchen::class)->saved($item));
    }

    /**
     * P6-31: the lines with the kitchen lines of combo parts too.
     *
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public static function withComboParts(): \Illuminate\Database\Eloquent\Builder
    {
        return static::query()->withoutGlobalScope(self::COMBO_PARTS_SCOPE);
    }

    /**
     * P6-31: the kitchen lines of this combo line's parts.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<self, $this>
     */
    public function comboChildren(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'combo_parent_id')->withoutGlobalScope(self::COMBO_PARTS_SCOPE)->orderBy('id');
    }

    /**
     * P6-31: the combo line of a part's kitchen line.
     *
     * @return BelongsTo<self, $this>
     */
    public function comboParent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'combo_parent_id');
    }

    /**
     * P6-31: a combo line (it has parts) or a part's kitchen line.
     */
    public function isComboLine(): bool
    {
        return is_array($this->combo_parts) && $this->combo_parts !== [];
    }

    public function isComboPart(): bool
    {
        return $this->combo_parent_id !== null;
    }

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

    // --- P3-12: POS lines -----------------------------------------------------------------------------------------

    /**
     * @return BelongsTo<User, $this>
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * P6-25: who entered the real weight of a "weight later" line.
     *
     * @return BelongsTo<User, $this>
     */
    public function weighedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'weighed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * P6-25: a "weight later" line whose real weight is not entered yet (no bill while one waits).
     */
    public function awaitingWeight(): bool
    {
        return $this->weight_status === \App\Domain\Pos\Orders\WeightStatus::AWAITING;
    }

    public function isVoid(): bool
    {
        return $this->kitchen_status === \App\Enums\KitchenItemStatus::VOID;
    }

    /**
     * Whether the kitchen has this line: sent by the POS, or (an online line, P2-11) from the start.
     */
    public function reachedKitchen(): bool
    {
        return $this->sent_at !== null || $this->fire_mode === null;
    }
}
