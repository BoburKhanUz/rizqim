<?php

namespace App\Models;

use App\Enums\DiningTableKind;
use App\Enums\MinSpendMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A place on a branch's floor map (P3-12, R1): a table, a room (kabinka) or a tapchan, in a hall, with its seats, its
 * spot on the map and an optional minimum spend (R4). Orders point at it with orders.table_id (and keep its name in
 * table_ref).
 *
 * @property DiningTableKind $kind
 * @property int|null $seats
 * @property numeric-string|null $layout_x
 * @property numeric-string|null $layout_y
 * @property numeric-string|null $layout_w
 * @property numeric-string|null $layout_h
 * @property int $sort_order
 * @property bool $is_active
 * @property numeric-string|null $min_spend
 * @property MinSpendMode $min_spend_mode
 * @property string|null $service_state P6-26: the hostess' mark, `cleaning` (App\Domain\Pos\Hall\TableStates)
 * @property \Illuminate\Support\Carbon|null $service_state_at
 */
class DiningTable extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'kind' => DiningTableKind::class,
        'seats' => 'integer',
        'layout_x' => 'decimal:2',
        'layout_y' => 'decimal:2',
        'layout_w' => 'decimal:2',
        'layout_h' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'min_spend' => 'decimal:2',
        'min_spend_mode' => MinSpendMode::class,
        'service_state_at' => 'datetime',
    ];

    protected $attributes = [
        'kind' => 'table',
        'sort_order' => 0,
        'is_active' => true,
        'min_spend_mode' => 'per_table',
    ];

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

    /**
     * @return BelongsTo<Hall, $this>
     */
    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class)->withTrashed();
    }

    /**
     * The orders still open at this place (any status but the finished ones).
     *
     * @return HasMany<Order, $this>
     */
    public function openOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id')
            ->whereNotIn('status', array_map(fn ($status) => $status->value, \App\Services\Order\OrderQueryService::TERMINAL_STATUSES));
    }
}
