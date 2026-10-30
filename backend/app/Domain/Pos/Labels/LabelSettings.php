<?php

namespace App\Domain\Pos\Labels;

use App\Models\Branch;
use App\Models\BranchLabelSettings;
use App\Models\Category;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * P6-32 (B7): a branch's sticker and label settings (branch_label_settings, owner screen "Stiker va yorliq"):
 *
 *  - `size`: the label roll (LabelSize: 40x30 or 58x40 mm);
 *  - `scope`: which items get a cup sticker (LabelScope: all, or only the dishes of `category_ids`);
 *  - `per_unit`: one sticker per piece (3 cups on a line: 3 stickers) or one per line;
 *  - `bag_label`: a takeaway order also gets a bag label.
 *
 * The till prints the stickers (pos_core templates, pos_hardware TSPL driver) from GET /pos/bootstrap `labels`;
 * the server only keeps the settings, so printing never blocks a sale, a payment or a close. Settings gate setup only.
 */
final class LabelSettings
{
    public const VIEW = 'array{branch_id: int, configured: bool, size: string, size_label: string, width_mm: int, height_mm: int, scope: string, scope_label: string, category_ids: list<int>, per_unit: bool, bag_label: bool, updated_at: string|null}';

    public const FIELDS = ['size', 'scope', 'category_ids', 'per_unit', 'bag_label'];

    /** At most this many categories can be picked. */
    public const MAX_CATEGORIES = 100;

    public function __construct(private readonly AuditLogger $audit) {}

    public function row(Branch $branch): ?BranchLabelSettings
    {
        return BranchLabelSettings::where('branch_id', $branch->id)->first();
    }

    /**
     * The settings as the owner screen and the till read them: every key, the stored row over the defaults
     * (40x30, every item, one sticker per piece, a bag label).
     *
     * @return array{branch_id: int, configured: bool, size: string, size_label: string, width_mm: int, height_mm: int, scope: string, scope_label: string, category_ids: list<int>, per_unit: bool, bag_label: bool, updated_at: string|null}
     */
    public function view(Branch $branch): array
    {
        /** @var BranchLabelSettings|null $row */
        $row = $this->row($branch);
        $size = $row !== null ? $row->size : LabelSize::MM_40X30;
        $scope = $row !== null ? $row->scope : LabelScope::ALL;
        $ids = $scope === LabelScope::CATEGORIES ? array_values(array_unique(array_map('intval', $row->category_ids ?? []))) : [];
        sort($ids);

        return [
            'branch_id' => (int) $branch->id,
            'configured' => $row !== null,
            'size' => $size->value,
            'size_label' => $size->label(),
            'width_mm' => $size->widthMm(),
            'height_mm' => $size->heightMm(),
            'scope' => $scope->value,
            'scope_label' => $scope->label(),
            'category_ids' => $ids,
            'per_unit' => $row !== null ? $row->per_unit : true,
            'bag_label' => $row !== null ? $row->bag_label : true,
            'updated_at' => $row?->updated_at?->toJSON(),
        ];
    }

    /**
     * Whether an item of the category gets a cup sticker under these settings.
     *
     * @param  array{scope: string, category_ids: list<int>}  $view
     */
    public static function appliesTo(array $view, ?int $categoryId): bool
    {
        if ($view['scope'] === LabelScope::ALL->value) {
            return true;
        }

        return $categoryId !== null && in_array($categoryId, $view['category_ids'], true);
    }

    /**
     * Saves the fields sent (audited); the others keep their value. `category_ids` must be existing categories (they are
     * shared by all stores) and is kept only for the `categories` scope.
     *
     * @param  array<string, mixed>  $data  validated, only FIELDS
     * @return array<string, mixed> view()
     *
     * @throws ValidationException
     */
    public function update(Branch $branch, array $data, User $actor): array
    {
        $before = $this->view($branch);
        $attributes = array_intersect_key($data, array_flip(self::FIELDS));

        $scope = isset($attributes['scope']) ? LabelScope::from((string) $attributes['scope']) : LabelScope::from($before['scope']);
        if (array_key_exists('category_ids', $attributes) || $scope === LabelScope::CATEGORIES) {
            $ids = array_key_exists('category_ids', $attributes) ? (array) $attributes['category_ids'] : $before['category_ids'];
            $ids = array_values(array_unique(array_map('intval', $ids)));
            sort($ids);
            if ($ids !== [] && Category::whereIn('id', $ids)->count() !== count($ids)) {
                throw ValidationException::withMessages(['category_ids' => 'Kategoriya topilmadi.']);
            }
            if ($scope === LabelScope::CATEGORIES && $ids === []) {
                throw ValidationException::withMessages(['category_ids' => 'Kamida bitta kategoriya tanlang yoki "Hamma taomlarga"ni yoqing.']);
            }
            $attributes['category_ids'] = $scope === LabelScope::CATEGORIES ? $ids : null;
        }
        if ($scope === LabelScope::ALL && !array_key_exists('category_ids', $attributes)) {
            $attributes['category_ids'] = null;
        }

        $row = $this->row($branch);
        if ($row === null) {
            BranchLabelSettings::create($attributes + ['store_id' => $branch->store_id, 'branch_id' => $branch->id]);
        } else {
            $row->update($attributes);
        }

        $after = $this->view($branch);
        $this->audit->record('settings.label_settings_changed', $branch, $before, $after, [], ['store_id' => (int) $branch->store_id, 'branch_id' => (int) $branch->id, 'actor' => $actor]);

        return $after;
    }
}
