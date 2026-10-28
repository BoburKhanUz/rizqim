<?php

namespace App\Services\Admin\Marketing;

use App\Models\Category;
use App\Services\Catalog\MenuImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * P4-31: Marketing -> Kategoriyalar (what CategoryResource of the old /superadmin panel did). Categories are global to
 * the marketplace, two levels deep (a parent and its children), shown in the Mini App in `sort_order`. The icon is the
 * category's `image`, stored through MenuImageService like every menu image (same disk, WebP, thumbnails made by a
 * queued job). "Hide" is `is_active` = false: the category leaves the catalog, its products stay.
 *
 * Ordering is saved as the whole list at once (`reorder`): Category's own model hooks shift neighbours one step at a
 * time, which is wrong for a drag or a batch of moves, so the order is written straight to the rows.
 */
class MarketingCategories
{
    public const MAX = 500;

    public function __construct(
        private readonly MenuImageService $images,
    ) {}

    /**
     * Every category (a few dozen) in the Mini App's order.
     *
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $rows = Category::query()
            ->with('parent:id,name')
            ->withCount(['products', 'children'])
            ->orderBy('sort_order')->orderBy('id')
            ->limit(self::MAX)
            ->get();

        return $rows->map(fn (Category $category) => $this->present($category))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Category $category): array
    {
        $category->loadMissing('parent:id,name');
        $category->loadCount(['products', 'children']);

        return [
            'id' => (int) $category->id,
            'uuid' => (string) $category->uuid,
            'name' => (string) $category->name,
            'slug' => (string) $category->slug,
            'parent' => $category->parent ? ['id' => (int) $category->parent->id, 'name' => (string) $category->parent->name] : null,
            'image_url' => $category->image_url,
            'image_preview' => $this->previewPath($category),
            'sort_order' => (int) $category->sort_order,
            'is_active' => (bool) $category->is_active,
            'products_count' => (int) $category->products_count,
            'children_count' => (int) $category->children_count,
            'updated_at' => MarketingTime::iso($category->updated_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Category $category): array
    {
        return [
            'name' => (string) $category->name,
            'slug' => (string) $category->slug,
            'parent_id' => $category->parent_id,
            'image' => $category->image,
            'is_active' => (bool) $category->is_active,
            'sort_order' => (int) $category->sort_order,
        ];
    }

    /**
     * @param  array{name: string, parent_id?: int|null, is_active?: bool}  $data
     *
     * @throws MarketingException
     */
    public function create(array $data, ?UploadedFile $image): Category
    {
        $this->assertParent(null, $data['parent_id'] ?? null);

        $category = DB::transaction(function () use ($data) {
            $name = trim($data['name']);

            $category = new Category();
            $category->forceFill([
                'name' => $name,
                'slug' => Str::slug($name) ?: 'kategoriya',
                'parent_id' => $data['parent_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                // At the end of the list
                'sort_order' => (int) Category::query()->withTrashed()->max('sort_order') + 1,
            ])->save();

            return $category;
        });

        if ($image !== null) {
            $this->images->replace($category, $image, 'categories');
        }

        return $category->refresh();
    }

    /**
     * @param  array<string, mixed>  $data  name, parent_id (null: a top-level category), is_active, remove_image
     *
     * @throws MarketingException
     */
    public function update(Category $category, array $data, ?UploadedFile $image): Category
    {
        if (array_key_exists('parent_id', $data)) {
            $this->assertParent($category, $data['parent_id']);
        }

        $fill = array_intersect_key($data, array_flip(['parent_id', 'is_active']));
        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            $fill['name'] = $name;
            $fill['slug'] = Str::slug($name) ?: $category->slug;
        }
        if ($fill !== []) {
            $category->fill($fill)->save();
        }

        if ($image !== null) {
            $this->images->replace($category, $image, 'categories');
        } elseif (!empty($data['remove_image']) && $category->image !== null) {
            $this->images->delete($category);
            $category->forceFill(['image' => null, 'image_disk' => null, 'image_thumbs' => null])->save();
        }

        return $category->refresh();
    }

    /**
     * Only a category with no products and no sub-categories may go (it is soft-deleted, as the old panel did).
     *
     * @throws MarketingException
     */
    public function delete(Category $category): void
    {
        $products = $category->products()->count();
        $children = $category->children()->count();
        if ($products > 0 || $children > 0) {
            throw new MarketingException(
                "Kategoriyada mahsulotlar yoki ichki kategoriyalar bor, uni o'chirib bo'lmaydi. Uni yashiring.",
                'category_in_use',
                409,
                ['products' => $products, 'children' => $children],
            );
        }

        $category->delete();
    }

    /**
     * The whole list in its new order: `$ids` must be every category, once (a stale screen is told to reload).
     *
     * @param  list<int>  $ids
     * @return array{before: array<int, int>, after: array<int, int>}  only the categories whose place changed
     *
     * @throws MarketingException
     */
    public function reorder(array $ids): array
    {
        return DB::transaction(function () use ($ids) {
            $current = Category::query()->lockForUpdate()->orderBy('sort_order')->orderBy('id')->pluck('sort_order', 'id');
            $given = array_values(array_unique($ids));
            $known = $current->keys()->map(fn ($id) => (int) $id)->all();
            if (count($given) !== count($ids) || count($given) !== count($known) || array_diff($given, $known) !== []) {
                throw new MarketingException("Ro'yxat eskirgan: sahifani yangilang va qayta urining.", 'order_stale', 409);
            }

            $before = [];
            $after = [];
            foreach ($given as $index => $id) {
                $place = $index + 1;
                if ((int) $current[$id] !== $place) {
                    $before[$id] = (int) $current[$id];
                    $after[$id] = $place;
                    // Straight to the row: no model hooks (they shift the neighbours), updated_at moves the menu version
                    Category::query()->whereKey($id)->update(['sort_order' => $place]);
                }
            }

            return ['before' => $before, 'after' => $after];
        });
    }

    /**
     * The stored icon for the admin preview (null when there is none on its disk, or it is someone else's URL).
     *
     * @return array{content: string, type: string}|null
     */
    public function imageFile(Category $category): ?array
    {
        $path = $category->image;
        if (!is_string($path) || $path === '' || filter_var($path, FILTER_VALIDATE_URL)) {
            return null;
        }

        $disk = Storage::disk($category->image_disk ?: 'public');
        if (!$disk->exists($path)) {
            return null;
        }

        $guess = $disk->mimeType($path);
        $type = is_string($guess) && in_array($guess, ['image/jpeg', 'image/png', 'image/webp'], true) ? $guess : match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return ['content' => (string) $disk->get($path), 'type' => $type];
    }

    /**
     * Two levels: a parent is a top-level category, and a category with children cannot become a child.
     *
     * @throws MarketingException
     */
    private function assertParent(?Category $category, mixed $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($category !== null && (int) $parentId === (int) $category->id) {
            throw new MarketingException("Kategoriya o'zining ichki kategoriyasi bo'la olmaydi.", 'invalid_parent');
        }

        $parent = Category::query()->find($parentId, ['id', 'parent_id']);
        if ($parent === null || $parent->parent_id !== null) {
            throw new MarketingException("Ota kategoriya asosiy (eng yuqori darajadagi) kategoriya bo'lishi kerak.", 'invalid_parent');
        }

        if ($category !== null && $category->children()->exists()) {
            throw new MarketingException("Ichki kategoriyalari bor kategoriyani boshqasining ichiga qo'yib bo'lmaydi.", 'invalid_parent');
        }
    }

    private function previewPath(Category $category): ?string
    {
        if (!is_string($category->image) || $category->image === '') {
            return null;
        }

        return "/marketing/categories/{$category->id}/image?v=" . ($category->updated_at?->getTimestamp() ?? 0);
    }
}
