<?php

namespace App\Models;

use App\Enums\PrinterConnection;
use App\Enums\PrinterPurpose;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A receipt, kitchen or label printer of a branch (P3-13). The terminal prints; this row is what it reads from
 * GET /pos/bootstrap. A USB or built-in printer is tied to one terminal (`device_id`).
 *
 * @property PrinterPurpose $purpose
 * @property PrinterConnection $connection
 * @property bool $is_active
 * @property bool $print_as_image
 */
class Printer extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'purpose' => PrinterPurpose::class,
        'connection' => PrinterConnection::class,
        'port' => 'integer',
        'paper_width' => 'integer',
        'is_active' => 'boolean',
        'print_as_image' => 'boolean',
    ];

    protected $attributes = [
        'purpose' => 'receipt',
        'paper_width' => 80,
        'is_active' => true,
        'print_as_image' => false,
    ];

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<KitchenStation, $this>
     */
    public function station(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class, 'station_id');
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
