<?php

namespace App\Services\Admin\Marketing;

use App\Models\Product;
use App\Models\Promo;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * P4-31: Marketing -> Bannerlar va aksiyalar (what PromoResource of the old /superadmin panel did). A banner is a
 * `promos` row the Mini App's home screen lists (GET /catalog without a store): a title, an image, an optional store
 * or product it opens, a place in the row (`sort_order`), on/off and, new here, a schedule (`starts_at`, `ends_at`).
 *
 * The image is stored exactly where the old panel put it: the public disk, directory `promos` (Promo::image_url builds
 * the URL from there). The list is small (a handful of banners), so it is not paginated.
 */
class PromoBanners
{
    public const DISK = 'public';

    public const DIRECTORY = 'promos';

    public const MAX = 200;

    public const STATUSES = ['live' => 'Ko\'rinmoqda', 'scheduled' => 'Rejalashtirilgan', 'ended' => 'Tugagan', 'inactive' => 'O\'chiq'];

    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $status = null, ?string $q = null): array
    {
        $now = now();
        $rows = Promo::query()
            ->with(['store:id,name', 'product:id,name,store_id'])
            ->when($q !== null && $q !== '', fn ($query) => $query->where('title', 'ilike', '%' . addcslashes($q, '%_\\') . '%'))
            ->orderBy('sort_order')->orderBy('id')
            ->limit(self::MAX)
            ->get()
            ->map(fn (Promo $promo) => $this->present($promo, $now));

        return $rows->when($status !== null && $status !== '', fn ($all) => $all->where('status', $status))->values()->all();
    }

    /**
     * What the Mini App shows right now, in its order (the same query as GET /catalog).
     *
     * @return list<array<string, mixed>>
     */
    public function preview(): array
    {
        return Promo::with(['store', 'product.store'])
            ->live()
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn (Promo $promo) => $promo->toCatalogEntry() + ['image_preview' => $this->previewPath($promo)])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Promo $promo, ?CarbonInterface $now = null): array
    {
        $status = $this->status($promo, $now ?? now());

        return [
            'id' => (int) $promo->id,
            'title' => (string) $promo->title,
            'image_url' => $promo->image_url,
            'image_preview' => $this->previewPath($promo),
            'store' => $promo->store ? ['id' => (int) $promo->store->id, 'name' => (string) $promo->store->name] : null,
            'product' => $promo->product ? ['id' => (int) $promo->product->id, 'name' => (string) $promo->product->name, 'store_id' => (int) $promo->product->store_id] : null,
            'is_active' => (bool) $promo->is_active,
            'starts_at' => MarketingTime::iso($promo->starts_at),
            'ends_at' => MarketingTime::iso($promo->ends_at),
            'sort_order' => (int) $promo->sort_order,
            'status' => $status,
            'status_label' => self::STATUSES[$status],
            'created_at' => MarketingTime::iso($promo->created_at),
            'updated_at' => MarketingTime::iso($promo->updated_at),
        ];
    }

    /**
     * The fields an audit entry keeps (no URL: the image path is enough).
     *
     * @return array<string, mixed>
     */
    public function snapshot(Promo $promo): array
    {
        return [
            'title' => (string) $promo->title,
            'image' => $promo->image,
            'store_id' => $promo->store_id,
            'product_id' => $promo->product_id,
            'is_active' => (bool) $promo->is_active,
            'starts_at' => MarketingTime::iso($promo->starts_at),
            'ends_at' => MarketingTime::iso($promo->ends_at),
            'sort_order' => (int) $promo->sort_order,
        ];
    }

    /**
     * @param  array{title: string, store_id?: int|null, product_id?: int|null, is_active?: bool, starts_at?: string|null, ends_at?: string|null}  $data
     *
     * @throws MarketingException
     */
    public function create(array $data, UploadedFile $image): Promo
    {
        $this->assertTarget($data['store_id'] ?? null, $data['product_id'] ?? null);
        $this->assertSchedule($data['starts_at'] ?? null, $data['ends_at'] ?? null);

        $promo = DB::transaction(function () use ($data, $image) {
            // At the end of the row
            $order = (int) Promo::query()->max('sort_order') + 1;

            return Promo::create([
                'title' => trim($data['title']),
                'image' => $image->store(self::DIRECTORY, self::DISK) ?: null,
                'store_id' => $data['store_id'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'sort_order' => $order,
            ]);
        });

        return $promo->load(['store:id,name', 'product:id,name,store_id']);
    }

    /**
     * Only the fields that are present change; `store_id` / `product_id` / the schedule may be sent as null to clear them.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws MarketingException
     */
    public function update(Promo $promo, array $data, ?UploadedFile $image): Promo
    {
        $storeId = array_key_exists('store_id', $data) ? $data['store_id'] : $promo->store_id;
        $productId = array_key_exists('product_id', $data) ? $data['product_id'] : $promo->product_id;
        $this->assertTarget($storeId, $productId);

        $startsAt = array_key_exists('starts_at', $data) ? $data['starts_at'] : $promo->starts_at;
        $endsAt = array_key_exists('ends_at', $data) ? $data['ends_at'] : $promo->ends_at;
        $this->assertSchedule($startsAt, $endsAt);

        $oldImage = $promo->image;
        $fill = array_intersect_key($data, array_flip(['store_id', 'product_id', 'is_active', 'starts_at', 'ends_at']));
        if (array_key_exists('title', $data)) {
            $fill['title'] = trim((string) $data['title']);
        }
        if ($image !== null) {
            $fill['image'] = $image->store(self::DIRECTORY, self::DISK) ?: $oldImage;
        }

        $promo->fill($fill)->save();

        if ($image !== null && $oldImage !== null && $oldImage !== $promo->image) {
            $this->deleteFile($oldImage);
        }

        return $promo->refresh()->load(['store:id,name', 'product:id,name,store_id']);
    }

    public function delete(Promo $promo): void
    {
        $image = $promo->image;
        $promo->delete();
        if ($image !== null) {
            $this->deleteFile($image);
        }
    }

    /**
     * The whole row in its new order: `$ids` must be every banner, once (a stale screen is told to reload).
     *
     * @param  list<int>  $ids
     * @return array{before: array<int, int>, after: array<int, int>}
     *
     * @throws MarketingException
     */
    public function reorder(array $ids): array
    {
        return DB::transaction(function () use ($ids) {
            $current = Promo::query()->lockForUpdate()->orderBy('sort_order')->orderBy('id')->pluck('sort_order', 'id');
            $given = array_values(array_unique($ids));
            $known = $current->keys()->map(fn ($id) => (int) $id)->all();
            if (count($given) !== count($ids) || count($given) !== count($known) || array_diff($given, $known) !== []) {
                throw new MarketingException("Ro'yxat eskirgan: sahifani yangilang va qayta urining.", 'order_stale', 409);
            }

            $before = $current->mapWithKeys(fn ($order, $id) => [(int) $id => (int) $order])->all();
            $after = [];
            foreach ($given as $index => $id) {
                $after[$id] = $index + 1;
                if (($before[$id] ?? null) !== $index + 1) {
                    Promo::query()->whereKey($id)->update(['sort_order' => $index + 1]);
                }
            }

            return ['before' => $before, 'after' => $after];
        });
    }

    public function status(Promo $promo, CarbonInterface $now): string
    {
        return match (true) {
            !$promo->is_active => 'inactive',
            $promo->starts_at !== null && $promo->starts_at->gt($now) => 'scheduled',
            $promo->ends_at !== null && $promo->ends_at->lte($now) => 'ended',
            default => 'live',
        };
    }

    /**
     * The stored file for the admin preview (null when there is none on the disk, or the image is someone else's URL).
     *
     * @return array{content: string, type: string}|null
     */
    public function imageFile(Promo $promo): ?array
    {
        $path = $promo->image;
        if (!is_string($path) || $path === '' || filter_var($path, FILTER_VALIDATE_URL)) {
            return null;
        }

        $disk = Storage::disk(self::DISK);
        if (!$disk->exists($path)) {
            return null;
        }

        return ['content' => (string) $disk->get($path), 'type' => $this->imageType($path, $disk->mimeType($path))];
    }

    /**
     * A banner opens one store or one product, not a product of another store.
     *
     * @throws MarketingException
     */
    private function assertTarget(mixed $storeId, mixed $productId): void
    {
        if ($storeId === null || $productId === null) {
            return;
        }

        $product = Product::query()->whereKey($productId)->first(['id', 'store_id']);
        if ($product !== null && (int) $product->store_id !== (int) $storeId) {
            throw new MarketingException("Mahsulot tanlangan ovqatxonaga tegishli emas.", 'product_store_mismatch');
        }
    }

    /**
     * @throws MarketingException
     */
    private function assertSchedule(mixed $startsAt, mixed $endsAt): void
    {
        if ($startsAt !== null && $endsAt !== null && Carbon::parse($endsAt)->lte(Carbon::parse($startsAt))) {
            throw new MarketingException("Tugash vaqti boshlanish vaqtidan keyin bo'lishi kerak.", 'invalid_schedule');
        }
    }

    private function previewPath(Promo $promo): ?string
    {
        if (!is_string($promo->image) || $promo->image === '') {
            return null;
        }

        return "/marketing/promos/{$promo->id}/image?v=" . ($promo->updated_at?->getTimestamp() ?? 0);
    }

    private function imageType(string $path, string|false $guess): string
    {
        $type = is_string($guess) ? $guess : '';
        if (in_array($type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return $type;
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    private function deleteFile(string $path): void
    {
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return;
        }

        try {
            Storage::disk(self::DISK)->delete($path);
        } catch (\Throwable $e) {
            Log::warning('Banner image could not be deleted', ['path' => $path, 'error' => $e->getMessage()]);
        }
    }
}
