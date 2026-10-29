<?php

namespace App\Models;

use App\Domain\Pos\StaffPay\StatementStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * P6-23: a branch's pay statement for one period (App\Domain\Pos\StaffPay\StaffPayStatements).
 *
 * @property StatementStatus $status
 * @property string $period App\Domain\Pos\StaffPay\PayPeriod value
 * @property \Illuminate\Support\Carbon $period_from
 * @property \Illuminate\Support\Carbon $period_to
 * @property numeric-string $gross_total
 * @property numeric-string $payable_total
 * @property numeric-string $paid_total
 * @property list<array{type: string, user_id: int|null, name: string|null, count?: int}>|null $warnings
 * @property \Illuminate\Support\Carbon|null $computed_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property int $version
 */
class StaffPayStatement extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => StatementStatus::class,
            'period_from' => 'date',
            'period_to' => 'date',
            'gross_total' => 'decimal:2',
            'payable_total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'warnings' => 'array',
            'computed_at' => 'datetime',
            'closed_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    /**
     * @return HasMany<StaffPayStatementLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StaffPayStatementLine::class, 'statement_id');
    }
}
