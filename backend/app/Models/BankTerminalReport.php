<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6-16: a shift's bank terminal day total compared with the card money Rizqim recorded in it
 * (App\Domain\Pos\TerminalFlow\BankTerminalReconciler). Review P6 C#M2: a `shared` report (a branch whose tills
 * share one bank terminal) covers every shift of its branch and business day without a report of its own
 * (coveredShifts); `shift_id` is the shift it was first entered on.
 *
 * @property bool $shared
 * @property numeric-string|null $alerted_difference the difference the owner was told about
 * @property list<array{source: string, bank_total: string, bank_count: int|null, difference: string, over_threshold: bool, entered_by: int|null, entered_at: string|null}>|null $history
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
            'shared' => 'boolean',
            'alerted_difference' => 'decimal:2',
            'history' => 'array',
        ];
    }

    /**
     * The shifts this report compares: its own shift, or — shared — every shift of the branch's business day that has
     * no per-shift report of its own (oldest first).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PosShift>
     */
    public function coveredShifts(): \Illuminate\Database\Eloquent\Collection
    {
        if (!$this->shared) {
            return PosShift::whereKey($this->shift_id)->get();
        }

        return PosShift::query()
            ->where('branch_id', $this->branch_id)
            ->whereDate('business_date', $this->business_date->toDateString())
            ->whereNotIn('id', self::query()->where('branch_id', $this->branch_id)->where('shared', false)->select('shift_id'))
            ->orderBy('opened_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * The report a shift is compared in: its own, else (a shared terminal) its branch and business day's.
     */
    public static function forShift(PosShift $shift): ?self
    {
        return self::where('shift_id', $shift->id)->first()
            ?? self::query()->where('shared', true)->where('branch_id', $shift->branch_id)
                ->whereDate('business_date', $shift->business_date->toDateString())->first();
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
