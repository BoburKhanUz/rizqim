<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\Admin\Marketing\MarketingException;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * P4-31: shared parts of the Marketing controllers (banners, promo codes, categories): the refusal envelope for a
 * MarketingException, the image rule, a request moment in UTC and an image answer.
 */
abstract class AdminMarketingController extends AdminController
{
    /** Banner and category images: 4 MB (the old panel took whatever the form allowed; this is the cap), no SVG. */
    protected const IMAGE_KB = 4096;

    protected function refuseWith(MarketingException $e): JsonResponse
    {
        return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
    }

    /**
     * @return list<mixed>
     */
    protected function imageRules(bool $required): array
    {
        return [$required ? 'required' : 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . self::IMAGE_KB];
    }

    /**
     * @return array<string, string>
     */
    protected function imageMessages(): array
    {
        return [
            'image.image' => 'Rasm fayli kerak (JPG, PNG yoki WebP).',
            'image.mimes' => 'Rasm JPG, PNG yoki WebP bo\'lishi kerak.',
            'image.max' => 'Rasm 4 MB dan katta bo\'lmasligi kerak.',
            'image.uploaded' => 'Rasm yuklanmadi (hajmi 4 MB dan oshmasligi kerak).',
            'image.file' => 'Rasm fayli kerak.',
        ];
    }

    /**
     * A store id that exists (and is not removed).
     *
     * @return list<mixed>
     */
    protected function storeRules(): array
    {
        return ['nullable', 'integer', 'min:1', 'max:999999999999999999', Rule::exists('stores', 'id')->whereNull('deleted_at')];
    }

    /**
     * A request moment (ISO 8601, with its offset or in the app's UTC) as UTC, how the columns keep it.
     */
    protected function utc(mixed $value): ?Carbon
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value)->utc() : null;
    }

    /**
     * @param  array{content: string, type: string}|null  $file
     */
    protected function imageResponse(?array $file): Response|JsonResponse
    {
        if ($file === null) {
            return $this->refuse('Rasm topilmadi.', 'no_image', 404);
        }

        return response($file['content'], 200, [
            'Content-Type' => $file['type'],
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
