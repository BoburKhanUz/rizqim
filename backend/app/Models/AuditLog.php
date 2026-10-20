<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who did what: actor user_id and/or device_id, the subject (model_type/model_id), a before/after diff of safe
 * fields and extra context in payload_json. New entries are written by App\Services\Audit\AuditLogger, which strips
 * secrets and masks phone numbers.
 */
class AuditLog extends Model
{
    use HasFactory;

    // No default timestamps columns (only created_at exists, we disable updated_at)
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'payload_json',
        'store_id',
        'branch_id',
        'device_id',
        'before',
        'after',
        'ip',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'before' => 'array',
        'after' => 'array',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * The subject's class without its namespace (Order, User, ...).
     */
    public function subjectName(): ?string
    {
        return $this->model_type ? class_basename($this->model_type) : null;
    }
}
