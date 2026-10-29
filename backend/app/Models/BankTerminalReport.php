<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6-16: a shift's bank terminal day total compared with the card money Rizqim recorded in it
 * (App\Domain\Pos\TerminalFlow\BankTerminalReconciler).
 *
 * @property \Illuminate\Support\Carbon $business_date
 * @property string $source manual|import|provider
 * @property numeric-string $bank_total
 * @property int|null $bank_count
 * @property list<array{rrn: string|null, amount: string, matched: bool}>|null $transactions
 * @property numeric-string $rizqim_total
 * @property int $rizqim_count
 * @property array<string, string>|null $rizqim_breakdown
 * @property numeric-string $difference
 * @property numeric-string $threshold
 * @property bool $over_threshold
 * @property array{bank: list<array{rrn: string|null, amount: string}>, rizqim: list<array{rrn: string|null, amount: string, kind: string}>}|null $unmatched
 * @property \Illuminate\Support\Carbon|null $alerted_at
 */
class BankTerminalReport extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'bank_total' => 'decimal:2',
            'bank_count' => 'integer',
            'transactions' => 'array',
            'rizqim_total' => 'decimal:2',
            'rizqim_count' => 'integer',
            'rizqim_breakdown' => 'array',
            'difference' => 'decimal:2',
            'threshold' => 'decimal:2',
            'over_threshold' => 'boolean',
            'unmatched' => 'array',
            'alerted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PosShift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'shift_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
