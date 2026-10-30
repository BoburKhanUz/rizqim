<?php

namespace App\Models;

use App\Domain\Pos\Labels\LabelScope;
use App\Domain\Pos\Labels\LabelSize;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P6-32: a branch's sticker and label settings (App\Domain\Pos\Labels\LabelSettings reads and changes it). One row per
 * branch; a branch without one gets the defaults.
 *
 * @property LabelSize $size
 * @property LabelScope $scope
 * @property list<int>|null $category_ids
 * @property bool $per_unit
 * @property bool $bag_label
 */
class BranchLabelSettings extends Model
{
    protected $table = 'branch_label_settings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'size' => LabelSize::class,
            'scope' => LabelScope::class,
            'category_ids' => 'array',
            'per_unit' => 'boolean',
            'bag_label' => 'boolean',
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
