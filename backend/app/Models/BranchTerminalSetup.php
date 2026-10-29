<?php

namespace App\Models;

use App\Domain\Pos\TerminalFlow\CardMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6-16: how a branch takes cards and its installation checklist (App\Domain\Pos\TerminalFlow\TerminalSetup reads it
 * with the defaults; a branch without a row reads as them).
 *
 * @property CardMode|null $card_mode
 * @property array<string, array{done: bool, done_at: string|null, done_by: int|null}>|null $checklist
 * @property numeric-string $difference_threshold
 * @property bool $ask_bank_total
 */
class BranchTerminalSetup extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'card_mode' => CardMode::class,
            'checklist' => 'array',
            'difference_threshold' => 'decimal:2',
            'ask_bank_total' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
