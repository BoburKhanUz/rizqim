<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6-23: one staff member's line of a pay statement: what each rule component gave (`components`) and the work and
 * sales behind it (`metrics`), the wage money already given in the period (`advances`), a manual adjustment, and
 * whether (and how) it was paid.
 *
 * @property array<string, mixed> $metrics
 * @property list<array<string, mixed>> $components
 * @property numeric-string $gross
 * @property numeric-string $advances
 * @property numeric-string $adjustment
 * @property numeric-string $payable
 * @property \Illuminate\Support\Carbon|null $paid_at
 */
class StaffPayStatementLine extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'components' => 'array',
            'gross' => 'decimal:2',
            'advances' => 'decimal:2',
            'adjustment' => 'decimal:2',
            'payable' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<StaffPayStatement, $this>
     */
    public function statement(): BelongsTo
    {
        return $this->belongsTo(StaffPayStatement::class, 'statement_id');
    }
}
